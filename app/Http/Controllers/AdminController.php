<?php

namespace App\Http\Controllers;

use App\Models\LdapAttributeMap;
use App\Models\LdapDomain;
use App\Models\LdapOuRule;
use App\Models\ObGroup;
use App\Models\ObLogEntry;
use App\Models\ObPasswordPolicy;
use App\Models\Section;
use App\Services\Auth\LdapAuthService;
use App\Services\FeatureService;
use App\Services\HealthCheckService;
use App\Services\LoggingSettingService;
use App\Services\MailSettingService;
use App\Services\OrganisationSetupService;
use App\Services\SecuritySettingService;
use App\Services\SmsSettingService;
use App\Services\UploadSecurityService;
use App\Support\Audit;
use App\Support\ClamavScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminController extends Controller
{
    private const MONITORING_TABS = ['logs', 'health', 'settings'];

    public function monitoring(Request $request, LoggingSettingService $obs, HealthCheckService $health): View
    {
        $tab = (string) $request->string('tab', 'logs');
        if (! in_array($tab, self::MONITORING_TABS, true)) {
            $tab = 'logs';
        }

        $data = ['tab' => $tab] + match ($tab) {
            'health' => $this->monitoringHealthTab($health),
            'settings' => $this->monitoringSettingsTab($obs),
            default => $this->monitoringLogsTab($request),
        };

        return view('admin.monitoring', $data);
    }

    /**
     * Deliberately trigger an issue so an admin can verify the observability
     * pipeline (error tracking + the error/performance canaux) works end to end.
     */
    public function simulateIssue(Request $request, LoggingSettingService $obs): RedirectResponse
    {
        switch ((string) $request->string('type')) {
            case 'exception':
                // Uncaught on purpose: reported to Sentry/GlitchTip (when enabled)
                // and logged to the `error` canal. Returns a 500 to the caller.
                throw new \RuntimeException(
                    'Incident simulé depuis le diagnostic d’observabilité — '.now()->toIso8601String()
                );

            case 'log':
                Audit::write('error', 'simulated.error', [
                    'source' => 'diagnostics',
                    'note' => 'Entrée de test générée manuellement.',
                ], 'error');
                break;

            case 'slow':
                // Block past the slow-request threshold so TrackPerformance
                // records a `performance` entry for this request.
                usleep((max(1000, $obs->int('obs_perf_slow_ms')) + 250) * 1000);
                break;

            default:
                return back()->with('error', __('admin.monitoring.diag.unknown'));
        }

        return redirect()
            ->route('admin.monitoring', ['tab' => 'logs'])
            ->with('success', __('admin.monitoring.diag.done'));
    }

    /** Unified structured log / activity viewer (ob_log_entry). */
    private function monitoringLogsTab(Request $request): array
    {
        $search = trim((string) $request->string('q'));
        $level = (string) $request->string('level', 'ALL');
        $channel = (string) $request->string('channel', 'ALL');

        $query = ObLogEntry::query()
            ->with('actor:P_ID,P_NOM,P_PRENOM')
            ->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('message', 'like', "%{$search}%")
                    ->orWhere('exception_message', 'like', "%{$search}%")
                    ->orWhere('url', 'like', "%{$search}%");
            });
        }

        if (in_array($level, ObLogEntry::LEVELS, true)) {
            $query->where('level', $level);
        }

        if ($channel !== 'ALL') {
            $query->where('channel', $channel);
        }

        $items = $query->paginate(50)->withQueryString();

        return [
            'items' => $items,
            'search' => $search,
            'level' => $level,
            'channel' => $channel,
            'levels' => ObLogEntry::LEVELS,
            'channels' => array_keys(LoggingSettingService::CANALS),
            'columns' => $this->logColumns(),
        ];
    }

    /** Health probes + basic performance metrics. */
    private function monitoringHealthTab(HealthCheckService $health): array
    {
        $report = $health->report();

        // Recent performance snapshot from the structured log.
        $perf = ['count' => 0, 'avg_ms' => null, 'max_ms' => null, 'slow' => collect()];
        try {
            $since = now()->subDay();
            $base = ObLogEntry::query()
                ->where('channel', 'performance')
                ->where('created_at', '>=', $since);

            $perf['count'] = (clone $base)->count();
            $perf['avg_ms'] = (int) round((float) (clone $base)->avg('duration_ms'));
            $perf['max_ms'] = (int) (clone $base)->max('duration_ms');
            $perf['slow'] = (clone $base)->orderByDesc('duration_ms')->limit(10)->get();
        } catch (\Throwable) {
            // ob_log_entry not migrated yet — leave the empty snapshot.
        }

        return ['report' => $report, 'perf' => $perf];
    }

    /** Observability settings rows for the Paramètres tab. */
    private function monitoringSettingsTab(LoggingSettingService $obs): array
    {
        // Self-heal: ensure every setting has a row (and thus an ID to PATCH).
        $obs->ensureSeeded();

        $settings = DB::table('configuration')
            ->whereIn('NAME', LoggingSettingService::keys())
            ->get()
            ->keyBy('NAME');

        return [
            'obsSettings' => $settings,
            'levels' => ObLogEntry::LEVELS,
            'canals' => array_keys(LoggingSettingService::CANALS),
            'canalLevelKey' => fn (string $canal) => LoggingSettingService::canalLevelKey($canal),
        ];
    }

    // ── Security settings ─────────────────────────────────────────────────────

    /** IDs that live on the dedicated security page, not on the generic settings page. */
    private const SECURITY_IDS = [48, 34, 36, 49, 25, 33, 69];

    private const SECURITY_TABS = ['passwords', 'charter', 'sessions', 'auth', 'network', 'hardening'];

    public function security(Request $request, SecuritySettingService $hardeningSvc): View
    {
        $tab = $request->input('tab', 'passwords');
        if (! in_array($tab, self::SECURITY_TABS, true)) {
            $tab = 'passwords';
        }

        if ($tab === 'hardening') {
            // Self-heal: make sure every hardening setting has a row (and thus an ID
            // the per-row save forms can target) even before the seeding migration runs.
            $hardeningSvc->ensureSeeded();
        }

        $settings = DB::table('configuration')
            ->whereIn('ID', self::SECURITY_IDS)
            ->get()
            ->keyBy('ID');

        $charterUpdatedAt = DB::table('configuration')
            ->where('NAME', 'charte_updated_at')
            ->value('VALUE');

        $policies = $tab === 'passwords'
            ? ObPasswordPolicy::withCount('groups')->orderByDesc('is_default')->orderBy('name')->get()
            : collect();

        $ldapDomains = ($tab === 'auth' || $tab === 'network')
            ? LdapDomain::orderBy('priority')->get()
            : collect();

        // Hardening rows (CSP/HSTS, auth throttling, upload safety) keyed by NAME so
        // the blade reuses the same per-row save plumbing as the other tabs (each
        // row posts its ID to admin.settings.save), without hard-coding IDs.
        $hardening = $tab === 'hardening'
            ? DB::table('configuration')->whereIn('NAME', SecuritySettingService::keys())->get()->keyBy('NAME')
            : collect();

        // ClamAV is an outbound dependency, so the Réseau tab lists it as a flow.
        $clamav = $tab === 'network'
            ? [
                'enabled' => $hardeningSvc->bool('sec_upload_scan_enabled'),
                'host' => $hardeningSvc->string('sec_clamav_host'),
                'port' => $hardeningSvc->int('sec_clamav_port'),
            ]
            : null;

        return view('admin.security', compact(
            'settings', 'charterUpdatedAt', 'tab', 'policies', 'ldapDomains', 'hardening', 'clamav',
        ));
    }

    // ── Security hardening (Renforcement tab) ─────────────────────────────────

    public function testClamav(Request $request, SecuritySettingService $hardening): JsonResponse
    {
        $scanner = new ClamavScanner(
            (string) $request->input('host', $hardening->string('sec_clamav_host')),
            (int) $request->input('port', $hardening->int('sec_clamav_port')),
            5,
        );

        try {
            return $scanner->ping()
                ? response()->json(['ok' => true, 'message' => __('admin.security.clamav_reachable')])
                : response()->json(['ok' => false, 'message' => __('admin.security.clamav_unreachable')]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    // ── LDAP domain management ────────────────────────────────────────────────

    public function ldapStore(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'enabled' => 'boolean',
            'priority' => 'integer|min:0|max:999',
            'host' => 'required|string|max:255',
            'port' => 'integer|min:1|max:65535',
            'base_dn' => 'required|string|max:500',
            'username' => 'nullable|string|max:500',
            'password' => 'nullable|string|max:500',
            'timeout' => 'integer|min:1|max:60',
            'use_tls' => 'boolean',
            'use_starttls' => 'boolean',
            'auth_method' => 'required|in:bind,upn',
            'upn_suffix' => 'nullable|string|max:200',
            'user_filter' => 'nullable|string|max:500',
            'restrict_to_ou' => 'boolean',
        ]);

        $data['enabled'] = (bool) ($data['enabled'] ?? false);
        $data['use_tls'] = (bool) ($data['use_tls'] ?? false);
        $data['use_starttls'] = (bool) ($data['use_starttls'] ?? false);
        $data['restrict_to_ou'] = (bool) ($data['restrict_to_ou'] ?? false);
        $data['user_filter'] ??= '(&(objectClass=person)(|(uid={login})(mail={login})))';

        $domain = LdapDomain::create($data);

        return redirect()->route('admin.ldap.edit', $domain->id)->with('success', 'Domaine LDAP créé.');
    }

    public function ldapEdit(int $id): View
    {
        $domain = LdapDomain::with(['attributeMaps', 'ouRules.group', 'ouRules.role', 'ouRules.section'])->findOrFail($id);
        $groups = ObGroup::groups()->orderBy('name')->get();
        $roles = ObGroup::roles()->orderBy('name')->get();
        $multiSite = app(FeatureService::class)->isEnabled('multi_site');
        $sections = $multiSite
            ? Section::where('S_INACTIVE', false)->orderBy('S_CODE')->get()
            : collect();

        $localFields = [
            'P_NOM' => 'Nom',
            'P_PRENOM' => 'Prénom',
            'P_EMAIL' => 'Email',
            'P_CODE' => 'Matricule / Identifiant',
            'P_GRADE' => 'Grade',
        ];

        return view('admin.ldap-domain-edit', compact('domain', 'groups', 'roles', 'sections', 'multiSite', 'localFields'));
    }

    public function ldapUpdate(Request $request, int $id): RedirectResponse
    {
        $domain = LdapDomain::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'enabled' => 'boolean',
            'priority' => 'integer|min:0|max:999',
            'host' => 'required|string|max:255',
            'port' => 'integer|min:1|max:65535',
            'base_dn' => 'required|string|max:500',
            'username' => 'nullable|string|max:500',
            'password' => 'nullable|string|max:500',
            'timeout' => 'integer|min:1|max:60',
            'use_tls' => 'boolean',
            'use_starttls' => 'boolean',
            'auth_method' => 'required|in:bind,upn',
            'upn_suffix' => 'nullable|string|max:200',
            'user_filter' => 'nullable|string|max:500',
            'restrict_to_ou' => 'boolean',
        ]);

        $data['enabled'] = (bool) ($data['enabled'] ?? false);
        $data['use_tls'] = (bool) ($data['use_tls'] ?? false);
        $data['use_starttls'] = (bool) ($data['use_starttls'] ?? false);
        $data['restrict_to_ou'] = (bool) ($data['restrict_to_ou'] ?? false);

        // Keep existing password if not changed.
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $domain->update($data);

        return back()->with('success', 'Domaine mis à jour.');
    }

    public function ldapDestroy(int $id): RedirectResponse
    {
        LdapDomain::findOrFail($id)->delete();

        return redirect()->route('admin.security', ['tab' => 'auth'])->with('success', 'Domaine LDAP supprimé.');
    }

    public function ldapTest(int $id): JsonResponse
    {
        $domain = LdapDomain::findOrFail($id);
        $error = app(LdapAuthService::class)->testDomain($domain);

        return $error === null
            ? response()->json(['ok' => true,  'message' => 'Connexion réussie.'])
            : response()->json(['ok' => false, 'message' => $error]);
    }

    public function ldapAttrStore(Request $request, int $id): RedirectResponse
    {
        $domain = LdapDomain::findOrFail($id);

        $data = $request->validate([
            'ldap_attr' => 'required|string|max:100',
            'local_field' => 'required|string|max:50',
            'overwrite' => 'boolean',
        ]);

        $data['overwrite'] = (bool) ($data['overwrite'] ?? false);

        $domain->attributeMaps()->create($data);

        return back()->with('success', 'Correspondance ajoutée.');
    }

    public function ldapAttrDestroy(int $id, int $attrId): RedirectResponse
    {
        LdapAttributeMap::where('ldap_domain_id', $id)->findOrFail($attrId)->delete();

        return back()->with('success', 'Correspondance supprimée.');
    }

    public function ldapOuStore(Request $request, int $id): RedirectResponse
    {
        $domain = LdapDomain::findOrFail($id);

        $data = $request->validate([
            'ou_dn' => 'required|string|max:500',
            'extra_filter' => 'nullable|string|max:500',
            'action' => 'required|in:allow,deny,assign',
            'group_id' => 'nullable|integer|exists:ob_group,id',
            'role_id' => 'nullable|integer|exists:ob_group,id',
            'section_id' => 'nullable|integer|exists:section,S_ID',
            'priority' => 'integer|min:0|max:999',
        ]);

        $domain->ouRules()->create($data);

        return back()->with('success', 'Règle OU ajoutée.');
    }

    public function ldapOuDestroy(int $id, int $ruleId): RedirectResponse
    {
        LdapOuRule::where('ldap_domain_id', $id)->findOrFail($ruleId)->delete();

        return back()->with('success', 'Règle supprimée.');
    }

    public function testHibp(): JsonResponse
    {
        try {
            $response = Http::timeout(5)->withHeaders(['Add-Padding' => 'true'])->get('https://api.pwnedpasswords.com/range/00000');

            return $response->successful()
                ? response()->json(['ok' => true,  'message' => 'Connexion réussie (HTTP '.$response->status().').'])
                : response()->json(['ok' => false, 'message' => 'Réponse HTTP '.$response->status().'.']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()]);
        }
    }

    // ── Application settings ──────────────────────────────────────────────────

    public function settings(): View
    {
        // Settings that only make sense while their feature is enabled.
        // 47 = "Période de disponibilité" (depends on the disponibilites feature).
        $features = app(FeatureService::class);
        $featureDependent = $features->isEnabled('disponibilites') ? [] : [47];

        $rows = DB::table('configuration')
            ->where('HIDDEN', 0)
            ->whereNotIn('ID', DB::table('ob_feature')->whereNotNull('legacy_config_id')->pluck('legacy_config_id'))
            ->whereNotIn('ID', self::SECURITY_IDS)
            ->whereNotIn('ID', $featureDependent)
            ->orderBy('TAB')
            ->orderBy('ORDERING')
            ->get();

        $tabs = [
            // TAB 2 (the legacy « Options ») is gone: every remaining row in it
            // is obsolete-filtered, moved or hidden — tab 1 takes the name over.
            1 => ['label' => 'Options',          'icon' => 'sliders-h'],
            3 => ['label' => 'Technique',         'icon' => 'shield-alt'],
            4 => ['label' => 'Organisation',      'icon' => 'building'],
            5 => ['label' => 'Avancé',            'icon' => 'wrench'],
            6 => ['label' => 'Localisation',      'icon' => 'globe'],
        ];

        $grouped = $rows->groupBy('TAB');
        $activeTab = (int) session('_settings_tab', 0);

        // Settings whose behaviour has changed or is not yet wired in Laravel.
        // ID => ['type' => 'obsolete'|'todo', 'note' => '...']
        $annotations = [
            88 => ['type' => 'obsolete', 'note' => 'Le logo dans la navbar ne sera plus utilisé. L\'icône de maison sera utilisée à la place. Ce réglage n\'a plus d\'effet.'],
            57 => ['type' => 'obsolete', 'note' => 'Les cartes utilisent Leaflet + OpenStreetMap, sans clé d\'API. Ce réglage n\'a plus d\'effet.'],
            60 => ['type' => 'obsolete', 'note' => 'Les cartes utilisent Leaflet + OpenStreetMap. Ce réglage n\'a plus d\'effet.'],
            96 => ['type' => 'obsolete', 'note' => 'Le pays des victimes par défaut sera géré par la localisation de Laravel. Ce réglage n\'a plus d\'effet.'],
            97 => ['type' => 'obsolete', 'note' => 'Le pays par défaut pour la géolocalisation sera géré par la localisation de Laravel. Ce réglage n\'a plus d\'effet.'],
            102 => ['type' => 'obsolete', 'note' => 'Les nom de niveaux de hierarchie ne sont plus utilisés. Ce réglage n\'a plus d\'effet.'],
            103 => ['type' => 'obsolete', 'note' => 'Les nom de niveaux de hierarchie ne sont plus utilisés. Ce réglage n\'a plus d\'effet.'],
            104 => ['type' => 'obsolete', 'note' => 'Les nom de niveaux de hierarchie ne sont plus utilisés. Ce réglage n\'a plus d\'effet.'],
            105 => ['type' => 'obsolete', 'note' => 'Les nom de niveaux de hierarchie ne sont plus utilisés. Ce réglage n\'a plus d\'effet.'],
            106 => ['type' => 'obsolete', 'note' => 'Les nom de niveaux de hierarchie ne sont plus utilisés. Ce réglage n\'a plus d\'effet.'],
            107 => ['type' => 'obsolete', 'note' => 'Les nom de niveaux de hierarchie ne sont plus utilisés. Ce réglage n\'a plus d\'effet.'],
            25 => ['type' => 'obsolete', 'note' => 'Géré via Administration > Sécurité.'],
            79 => ['type' => 'obsolete', 'note' => 'Le type d\'organisation se règle via Organisation > Type d\'organisation (assistant de configuration). Ne pas modifier ici.'],
            8 => ['type' => 'todo',     'note' => 'Le mail de contact n\'est pas encore utilisé dans Laravel.'],
            39 => ['type' => 'todo',     'note' => 'Le nom de l\'organsiation n\'est pas encore utilisé dans Laravel.'],
            40 => ['type' => 'todo',     'note' => 'La description de l\'organisation n\'est pas encore utilisée dans Laravel.'],
            74 => ['type' => 'obsolete', 'note' => 'Le logo IOS de l\'application sera géré automatiquement à partir du logo principal. Ce réglage n\'a plus d\'effet.'],
            73 => ['type' => 'obsolete', 'note' => 'Le favicon de l\'application est géré par le logo principal. Ce réglage n\'a plus d\'effet.'],
            75 => ['type' => 'todo',     'note' => 'L\'image de connexion n\'est pas encore utilisée dans Laravel.'],
            2 => ['type' => 'obsolete', 'note' => 'Les sections ne sont plus limitées. Ce réglage n\'a plus d\'effet.'],
            13 => ['type' => 'obsolete', 'note' => 'Les sauvegardes automatiques sont gérées dans l\'onglet Sauvegardes. Ce réglage n\'a plus d\'effet.'],
            26 => ['type' => 'obsolete', 'note' => 'Les Cron Jobs sont gérés par Laravel Scheduler. Ce réglage n\'a plus d\'effet.'],
            55 => ['type' => 'obsolete', 'note' => 'Les flocons de neige ne seront pas réimplémentés. Ce réglage n\'a plus d\'effet.'],
            63 => ['type' => 'obsolete', 'note' => 'Les changements du personnel peuvent être bloqués par des permissions. Ce réglage n\'a plus d\'effet.'],
            64 => ['type' => 'todo',     'note' => 'Réglage actif ; les points d\'entrée de l\'API d\'import arriveront avec l\'épopée API.'],
            65 => ['type' => 'todo',     'note' => 'Réglage actif ; utilisée par la future API d\'import.'],
            66 => ['type' => 'todo',     'note' => 'Réglage actif ; utilisé par la future API d\'import.'],
            33 => ['type' => 'obsolete', 'note' => 'Géré via Administration > Sécurité.'],
            42 => ['type' => 'obsolete', 'note' => 'Remplacé par le système de contrôle d\'accès des documents.'],
            48 => ['type' => 'obsolete', 'note' => 'Géré via Administration > Sécurité.'],
            15 => ['type' => 'obsolete', 'note' => 'Géré via Administration > Sécurité.'],
            16 => ['type' => 'obsolete', 'note' => 'Géré via Administration > Sécurité.'],
            17 => ['type' => 'obsolete', 'note' => 'Géré via Administration > Sécurité.'],
            70 => ['type' => 'obsolete', 'note' => 'Géré via Administration > Sécurité.'],
            34 => ['type' => 'obsolete', 'note' => 'Géré via Administration > Sécurité.'],
            36 => ['type' => 'obsolete', 'note' => 'Géré via Administration > Sécurité.'],
            49 => ['type' => 'obsolete', 'note' => 'Géré via Administration > Sécurité.'],
            50 => ['type' => 'obsolete', 'note' => 'La clé du webservice n\'est plus utilisée. Ce réglage n\'a plus d\'effet.'],
            20 => ['type' => 'obsolete', 'note' => 'L\'URL de la page d\'identification est désormais fixe. Ce réglage n\'a plus d\'effet.'],
            51 => ['type' => 'obsolete', 'note' => 'L\'URL de redirection après connexion est désormais fixe. Ce réglage n\'a plus d\'effet.'],
            43 => ['type' => 'obsolete', 'note' => 'L\'ordre des sections n\'est plus utilisé. Ce réglage n\'a plus d\'effet.'],
            21 => ['type' => 'obsolete', 'note' => 'Les répertoires de stockage sont configurés dans config/filesystems.php. Ce réglage n\'a plus d\'effet.'],
            44 => ['type' => 'obsolete', 'note' => 'Laravel utilise bcrypt automatiquement. Les anciens hachages MD5 sont migrés à la prochaine connexion. Ce réglage n\'a plus d\'effet.'],
            54 => ['type' => 'obsolete', 'note' => 'L\'affichage des erreurs est contrôlé par APP_DEBUG dans .env. Ce réglage n\'a plus d\'effet.'],
            69 => ['type' => 'obsolete', 'note' => 'Géré via Administration > Sécurité.'],
            67 => ['type' => 'obsolete', 'note' => 'Le verrou de crontab de mailing est géré par Laravel Queue. Ce réglage n\'a plus d\'effet.'],
        ];

        // Drop obsolete settings from the page entirely — they no longer have any
        // effect. What replaced them is surfaced as informational notes in the
        // views (e.g. the Organisation tab points to .env / the setup wizard).
        $obsoleteIds = collect($annotations)
            ->filter(fn ($a) => $a['type'] === 'obsolete')
            ->keys();
        $grouped = $rows->reject(fn ($r) => $obsoleteIds->contains($r->ID))->groupBy('TAB');

        $orgTypeLabel = app(OrganisationSetupService::class)->typeLabel();

        return view('admin.settings', compact('grouped', 'tabs', 'activeTab', 'annotations', 'orgTypeLabel'));
    }

    /**
     * Administration ▸ Notifications — email + SMS gateway settings, rendered
     * from the hidden configuration rows (mail_allowed, mail_*, sms_*).
     */
    public function notifications(): View
    {
        // The .env instantiates the visible values: mail rows are created from
        // the effective config on first render, empty SMS rows are filled from
        // it. From then on the stored rows are the source of truth.
        app(MailSettingService::class)->ensureSeeded();
        app(SmsSettingService::class)->seedFromEnv();

        $rows = DB::table('configuration')
            ->whereIn('NAME', array_merge(
                ['mail_allowed', 'sms_allowed', 'sms_provider', 'sms_user', 'sms_password', 'sms_api_id'],
                MailSettingService::keys(),
            ))
            ->get()
            ->keyBy('NAME');

        return view('admin.notifications', [
            'rows' => $rows,
            'envDriver' => (string) config('sms.driver', 'log'),
        ]);
    }

    public function saveSetting(Request $request, int $id): RedirectResponse
    {
        $row = DB::table('configuration')->where('ID', $id)->first();
        abort_if($row === null, 404);

        $tab = (int) $request->input('_tab', $row->TAB ?? 0);

        $value = $request->boolean('toggle')
            ? ($request->input('VALUE', '0') === '1' ? '1' : '0')
            : $request->input('VALUE', '');

        DB::table('configuration')
            ->where('ID', $id)
            ->update(['VALUE' => $value]);

        if ($request->input('_back') === 'security') {
            $secTab = $request->input('_tab', 'passwords');

            return redirect()->route('admin.security', ['tab' => $secTab])->with('success', 'Paramètre mis à jour.');
        }

        if ($request->input('_back') === 'monitoring') {
            return redirect()->route('admin.monitoring', ['tab' => 'settings'])->with('success', 'Paramètre mis à jour.');
        }

        if ($request->input('_back') === 'notifications') {
            return redirect()->route('admin.notifications')->with('success', 'Paramètre mis à jour.');
        }

        if ($request->input('_back') === 'maintenance') {
            return redirect()->route('admin.maintenance')->with('success', 'Paramètre mis à jour.');
        }

        return redirect()->route('admin.settings')
            ->with('success', 'Paramètre mis à jour.')
            ->with('_settings_tab', $tab);
    }

    public function uploadSetting(Request $request, int $id): RedirectResponse
    {
        $row = DB::table('configuration')->where('ID', $id)->where('IS_FILE', 1)->first();
        abort_if($row === null, 404);

        $tab = (int) $request->input('_tab', $row->TAB ?? 0);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpeg,png,gif,ico,webp', 'max:4096'],
        ]);

        app(UploadSecurityService::class)->assertSafe(
            $request->file('file'),
            ['jpeg', 'jpg', 'png', 'gif', 'ico', 'webp'],
            4096,
            'file',
        );

        // Remove old file from storage
        $old = $row->VALUE ?? '';
        if ($old && str_starts_with($old, 'theme/') && Storage::disk('public')->exists($old)) {
            Storage::disk('public')->delete($old);
        }

        $path = $request->file('file')->store('theme', 'public');

        DB::table('configuration')->where('ID', $id)->update(['VALUE' => $path]);

        return redirect()->route('admin.settings')
            ->with('success', 'Image mise à jour.')
            ->with('_settings_tab', $tab);
    }

    public function deleteSetting(Request $request, int $id): RedirectResponse
    {
        $row = DB::table('configuration')->where('ID', $id)->where('IS_FILE', 1)->first();
        abort_if($row === null, 404);

        $tab = (int) $request->input('_tab', $row->TAB ?? 0);

        $path = $row->VALUE ?? '';
        if ($path && str_starts_with($path, 'theme/') && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        DB::table('configuration')->where('ID', $id)->update(['VALUE' => '']);

        return redirect()->route('admin.settings')
            ->with('success', 'Image supprimée.')
            ->with('_settings_tab', $tab);
    }

    // ── Password policy CRUD ──────────────────────────────────────────────────

    public function policyCreate(): View
    {
        $groups = ObGroup::orderBy('name')->get();

        return view('admin.password-policy-edit', [
            'policy' => null,
            'groups' => $groups,
        ]);
    }

    public function policyStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'min_length' => ['required', 'integer', 'min:6', 'max:128'],
            'expiry_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'max_attempts' => ['required', 'integer', 'min:0', 'max:100'],
            'require_uppercase' => ['sometimes', 'boolean'],
            'require_lowercase' => ['sometimes', 'boolean'],
            'require_digits' => ['sometimes', 'boolean'],
            'require_special' => ['sometimes', 'boolean'],
            'blocklist_check' => ['sometimes', 'boolean'],
            'require_2fa' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $validated['require_uppercase'] = $request->boolean('require_uppercase');
        $validated['require_lowercase'] = $request->boolean('require_lowercase');
        $validated['require_digits'] = $request->boolean('require_digits');
        $validated['require_special'] = $request->boolean('require_special');
        $validated['blocklist_check'] = $request->boolean('blocklist_check');
        $validated['require_2fa'] = $request->boolean('require_2fa');
        $validated['is_default'] = $request->boolean('is_default');

        if ($validated['is_default']) {
            ObPasswordPolicy::where('is_default', true)->update(['is_default' => false]);
        }

        $policy = ObPasswordPolicy::create($validated);
        $this->syncPolicyGroups($policy, $request->input('group_ids', []));

        return redirect()->route('admin.security', ['tab' => 'passwords'])
            ->with('success', 'Politique créée.');
    }

    public function policyEdit(int $id): View
    {
        $policy = ObPasswordPolicy::findOrFail($id);
        $groups = ObGroup::orderBy('name')->get();

        return view('admin.password-policy-edit', compact('policy', 'groups'));
    }

    public function policyUpdate(Request $request, int $id): RedirectResponse
    {
        $policy = ObPasswordPolicy::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'min_length' => ['required', 'integer', 'min:6', 'max:128'],
            'expiry_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'max_attempts' => ['required', 'integer', 'min:0', 'max:100'],
            'require_uppercase' => ['sometimes', 'boolean'],
            'require_lowercase' => ['sometimes', 'boolean'],
            'require_digits' => ['sometimes', 'boolean'],
            'require_special' => ['sometimes', 'boolean'],
            'blocklist_check' => ['sometimes', 'boolean'],
            'require_2fa' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        $validated['require_uppercase'] = $request->boolean('require_uppercase');
        $validated['require_lowercase'] = $request->boolean('require_lowercase');
        $validated['require_digits'] = $request->boolean('require_digits');
        $validated['require_special'] = $request->boolean('require_special');
        $validated['blocklist_check'] = $request->boolean('blocklist_check');
        $validated['require_2fa'] = $request->boolean('require_2fa');
        $validated['is_default'] = $request->boolean('is_default');

        if ($validated['is_default']) {
            ObPasswordPolicy::where('id', '!=', $id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $policy->update($validated);
        $this->syncPolicyGroups($policy, $request->input('group_ids', []));

        return redirect()->route('admin.security', ['tab' => 'passwords'])
            ->with('success', 'Politique mise à jour.');
    }

    public function policyDestroy(int $id): RedirectResponse
    {
        $policy = ObPasswordPolicy::findOrFail($id);
        abort_if($policy->is_default, 422, 'La politique par défaut ne peut pas être supprimée.');

        // Detach groups before deleting (nullOnDelete FK handles it, but be explicit).
        ObGroup::where('password_policy_id', $id)->update(['password_policy_id' => null]);
        $policy->delete();

        return redirect()->route('admin.security', ['tab' => 'passwords'])
            ->with('success', 'Politique supprimée.');
    }

    // ── LDAP ─────────────────────────────────────────────────────────────────

    /** @param string[] $groupIds */
    private function syncPolicyGroups(ObPasswordPolicy $policy, array $groupIds): void
    {
        $ids = array_map('intval', $groupIds);

        // Clear existing assignments for this policy, then re-assign selected groups.
        ObGroup::where('password_policy_id', $policy->id)
            ->whereNotIn('id', $ids)
            ->update(['password_policy_id' => null]);

        if ($ids !== []) {
            ObGroup::whereIn('id', $ids)
                ->update(['password_policy_id' => $policy->id]);
        }
    }

    /** Column definitions for the unified log table (ob_log_entry). */
    private function logColumns(): array
    {
        $levelBadge = [
            'debug' => 'ob-badge-archive', 'info' => 'ob-badge-int', 'notice' => 'ob-badge-int',
            'warning' => 'ob-badge-ben', 'error' => 'ob-badge-bloqued', 'critical' => 'ob-badge-bloqued',
            'alert' => 'ob-badge-bloqued', 'emergency' => 'ob-badge-bloqued',
        ];
        $levelMap = [];
        foreach ($levelBadge as $lvl => $css) {
            $levelMap[$lvl] = [ucfirst($lvl), $css];
        }
        $canalMap = [];
        foreach (array_keys(LoggingSettingService::CANALS) as $canal) {
            $canalMap[$canal] = [$canal, 'ob-badge-ext'];
        }

        return [
            ['key' => 'date', 'label' => 'Date', 'type' => 'html', 'alwaysVisible' => true, 'mobile' => true, 'exportable' => true,
                'value' => fn ($log) => $log->created_at ? '<span style="white-space:nowrap">'.e($log->created_at->format('d/m/Y H:i:s')).'</span>' : '—',
                'exportValue' => fn ($log) => $log->created_at?->format('d/m/Y H:i:s') ?? ''],
            ['key' => 'canal', 'label' => 'Canal', 'type' => 'badge', 'mobile' => true, 'exportable' => true,
                'value' => fn ($log) => $log->channel, 'badgeMap' => $canalMap,
                'exportValue' => fn ($log) => $log->channel],
            ['key' => 'level', 'label' => 'Niveau', 'type' => 'badge', 'mobile' => true, 'exportable' => true,
                'value' => fn ($log) => $log->level, 'badgeMap' => $levelMap,
                'exportValue' => fn ($log) => $log->level],
            ['key' => 'message', 'label' => 'Message', 'type' => 'html', 'alwaysVisible' => true, 'mobile' => true, 'exportable' => true,
                'value' => function ($log) {
                    $html = e(Str::limit($log->message, 160));
                    if ($log->exception_class) {
                        $html .= '<div class="text-muted" style="font-size:var(--font-size-xs);">'.e($log->exception_class).'</div>';
                    }
                    if ($log->url) {
                        $html .= '<div class="text-muted" style="font-size:var(--font-size-xs);">'.e($log->method.' '.Str::limit($log->url, 80)).'</div>';
                    }

                    return $html;
                },
                'exportValue' => fn ($log) => $log->message],
            ['key' => 'user', 'label' => 'Utilisateur', 'type' => 'text', 'mobile' => false, 'exportable' => true,
                'value' => fn ($log) => $log->actor ? $log->actor->P_PRENOM.' '.$log->actor->P_NOM : '—',
                'exportValue' => fn ($log) => $log->actor ? $log->actor->P_PRENOM.' '.$log->actor->P_NOM : ''],
            ['key' => 'details', 'label' => 'Détails', 'type' => 'html', 'mobile' => false, 'default' => false, 'exportable' => false,
                'value' => function ($log) {
                    $parts = '';
                    if (! empty($log->context)) {
                        $parts .= '<pre class="mb-1" style="font-size:var(--font-size-xs);white-space:pre-wrap;">'.e(json_encode($log->context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)).'</pre>';
                    }
                    if ($log->exception_trace) {
                        $parts .= '<pre class="mb-0" style="font-size:var(--font-size-xs);white-space:pre-wrap;max-height:280px;overflow:auto;">'.e($log->exception_message."\n\n".$log->exception_trace).'</pre>';
                    }
                    if ($parts === '') {
                        return '—';
                    }

                    return '<details><summary style="cursor:pointer;"><i class="fas fa-code"></i></summary>'.$parts.'</details>';
                }],
        ];
    }
}
