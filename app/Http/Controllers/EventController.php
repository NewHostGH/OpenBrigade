<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Personnel;
use App\Models\Section;
use App\Services\GeneralSettingService;
use App\Services\ICalExportService;
use App\Services\ReinforcementTransmissionService;
use App\Services\SectionScopeService;
use App\Services\TableExportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventController extends Controller
{
    /** Activity type of a reinforcement sent to another section's event. */
    public const RENFORT_TYPE = 'REN';

    public function __construct(
        private readonly SectionScopeService $sectionScope,
    ) {}

    // ── Event list ────────────────────────────────────────────────────────────

    public function index(Request $request): View
    {
        $period = (string) $request->string('period', 'upcoming');
        $search = trim((string) $request->string('q'));
        $type = (string) $request->string('type', 'ALL');
        $filtSect = $this->sectionScope->sectionFilter($request);

        $items = $this->buildFilteredQuery($request)->paginate(50)->withQueryString();

        // Event types for the filter dropdown
        $types = DB::table('type_evenement')
            ->where('TE_CODE', '<>', 'MC')
            ->orderBy('TE_LIBELLE')
            ->get(['TE_CODE', 'TE_LIBELLE']);

        $sections = Section::query()->orderBy('S_CODE')->get(['S_ID', 'S_CODE', 'S_DESCRIPTION']);

        return view('event.index', compact(
            'items', 'period', 'search', 'type', 'filtSect', 'types', 'sections'
        ) + ['columns' => $this->evenementColumns()]);
    }

    /**
     * Build the section-scoped, period/type/search-filtered event query shared
     * by the list and the exports. Pagination is applied by the caller.
     */
    private function buildFilteredQuery(Request $request): Builder
    {
        $period = (string) $request->string('period', 'upcoming');
        $search = trim((string) $request->string('q'));
        $type = (string) $request->string('type', 'ALL');
        $filtSect = $this->sectionScope->sectionFilter($request);

        $query = Event::query()
            ->with(['horaires', 'section'])
            ->join('evenement_horaire as eh', 'evenement.E_CODE', '=', 'eh.E_CODE')
            ->join('type_evenement as te', 'evenement.TE_CODE', '=', 'te.TE_CODE')
            ->select([
                'evenement.E_CODE',
                'evenement.E_LIBELLE',
                'evenement.E_LIEU',
                'evenement.E_CLOSED',
                'evenement.E_CANCELED',
                'evenement.E_NB',
                'evenement.S_ID',
                'evenement.TE_CODE',
                'evenement.E_EQUIPE',
                'te.TE_LIBELLE',
                'te.TE_ICON',
                DB::raw('MIN(eh.EH_DATE_DEBUT) as first_date'),
                DB::raw('MIN(eh.EH_DEBUT) as first_time'),
            ])
            ->groupBy(
                'evenement.E_CODE', 'evenement.E_LIBELLE', 'evenement.E_LIEU',
                'evenement.E_CLOSED', 'evenement.E_CANCELED', 'evenement.E_NB',
                'evenement.S_ID', 'evenement.TE_CODE', 'evenement.E_EQUIPE',
                'te.TE_LIBELLE', 'te.TE_ICON'
            )
            ->where('evenement.E_CANCELED', 0)
            ->where('evenement.E_EQUIPE', 0);

        match ($period) {
            'past' => $query->having(DB::raw('MIN(eh.EH_DATE_DEBUT)'), '<', now()->toDateString()),
            'upcoming' => $query->having(DB::raw('MAX(eh.EH_DATE_FIN)'), '>=', now()->toDateString()),
            default => null, // 'all'
        };

        if ($type !== '' && $type !== 'ALL') {
            $query->where('evenement.TE_CODE', $type);
        }

        $this->sectionScope->apply($query, 'evenement.S_ID', $filtSect);

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('evenement.E_LIBELLE', 'like', "%{$search}%")
                    ->orWhere('evenement.E_LIEU', 'like', "%{$search}%")
                    ->orWhere('evenement.E_CODE', 'like', "%{$search}%");
            });
        }

        if ($period === 'past') {
            $query->orderByDesc('first_date');
        } else {
            $query->orderBy('first_date');
        }

        return $query;
    }

    /**
     * Stream the filtered event list as an XLSX download. Honours the active
     * period / type / section / search filters and the ?cols= selection.
     */
    public function exportListXls(Request $request)
    {
        return $this->exportList($request, 'xlsx');
    }

    /**
     * Stream the filtered event list as a CSV download.
     */
    public function exportListCsv(Request $request)
    {
        return $this->exportList($request, 'csv');
    }

    private function exportList(Request $request, string $format)
    {
        $service = new TableExportService;
        // 'activite' is alwaysVisible and 'icon' is non-exportable, so prepend
        // the activity label/type explicitly to keep the sheet self-describing.
        $columns = $service->resolveColumns($this->evenementColumns(), $request, [
            ['Type',     fn ($e) => $e->TE_LIBELLE ?? ''],
            ['Activité', fn ($e) => $e->E_LIBELLE ?? $e->E_CODE],
        ]);

        $items = $this->buildFilteredQuery($request)->get();
        $filename = 'Evenements_'.date('Ymd');

        return $format === 'csv'
            ? $service->toCsv($columns, $items, $filename)
            : $service->toXlsx($columns, $items, $filename, ['sheetTitle' => 'Événements', 'freezeHeader' => true]);
    }

    private static function typeIcon(string $code): string
    {
        return match ($code) {
            'GAR' => 'shield-alt',
            'FOR' => 'graduation-cap',
            'REU' => 'users',
            'CER' => 'award',
            'SPO' => 'running',
            'TEC' => 'tools',
            'COM' => 'bullhorn',
            'MLA' => 'tasks',
            'WEB' => 'video',
            'MC' => 'clipboard-list',
            'DPS' => 'medkit',
            'MAN', 'EXE' => 'dumbbell',
            'NAU' => 'water',
            'MAR' => 'walking',
            'MED' => 'heartbeat',
            'COH', 'DIV' => 'calendar-alt',
            'ALR' => 'bell',
            'HUM', 'HEB', 'AID' => 'hands-helping',
            'DFCI', 'FOR_INC' => 'fire',
            'LOG', 'TRANS' => 'truck',
            'RAD', 'RADIO' => 'broadcast-tower',
            default => 'calendar-alt',
        };
    }

    private function evenementColumns(): array
    {
        return [
            ['key' => 'icon', 'label' => '', 'type' => 'html', 'value' => fn ($e) => '<i class="fas fa-'.self::typeIcon($e->TE_CODE ?? '').'" style="color:var(--text-muted-soft)" title="'.e($e->TE_LIBELLE ?? '').'"></i>', 'alwaysVisible' => true, 'exportable' => false, 'mobile' => true],
            ['key' => 'activite', 'label' => 'Activité', 'type' => 'html', 'value' => fn ($e) => '<a href="'.route('event.show', $e->E_CODE).'" class="text-decoration-none fw-semibold">'.e($e->E_LIBELLE ?? $e->E_CODE).'</a>', 'alwaysVisible' => true, 'exportable' => true, 'exportValue' => fn ($e) => $e->E_LIBELLE ?? $e->E_CODE, 'sortField' => 'E_INTITULE', 'mobile' => true],
            ['key' => 'lieu', 'label' => 'Lieu', 'type' => 'text', 'value' => fn ($e) => $e->E_LIEU ?? '—', 'mobile' => false, 'exportable' => true, 'exportValue' => fn ($e) => $e->E_LIEU ?? ''],
            ['key' => 'date', 'label' => 'Date', 'type' => 'html', 'value' => fn ($e) => $e->first_date ? Carbon::parse($e->first_date)->locale('fr')->isoFormat('ddd D MMM YYYY').($e->first_time ? ' <span class="text-muted">'.substr($e->first_time, 0, 5).'</span>' : '') : '—', 'mobile' => false, 'exportable' => true, 'exportValue' => fn ($e) => $e->first_date ? Carbon::parse($e->first_date)->format('d/m/Y') : ''],
            ['key' => 'statut', 'label' => 'Statut', 'type' => 'badge', 'value' => fn ($e) => $e->E_CANCELED ? 'ANNULEE' : ($e->E_CLOSED ? 'CLOSE' : 'OPEN'), 'badgeMap' => ['ANNULEE' => ['Annulée', 'ob-badge-bloqued'], 'CLOSE' => ['Clôturée', 'ob-badge-archive'], 'OPEN' => ['Ouverte', 'ob-badge-actif']], 'exportable' => true, 'exportValue' => fn ($e) => $e->E_CANCELED ? 'Annulée' : ($e->E_CLOSED ? 'Clôturée' : 'Ouverte'), 'mobile' => false],
        ];
    }

    // ── Event detail ──────────────────────────────────────────────────────────

    public function show(string $code): View
    {
        $event = Event::with(['section', 'horaires', 'chef'])->findOrFail($code);

        $typeLabel = DB::table('type_evenement')
            ->where('TE_CODE', $event->TE_CODE)
            ->value('TE_LIBELLE');

        // Participant functions for this event type (shown in add/edit modals).
        $functions = DB::table('type_participation')
            ->where('TE_CODE', $event->TE_CODE)
            ->orderBy('TP_NUM')
            ->get(['TP_ID', 'TP_LIBELLE']);

        // Teams with member/vehicle counts (also needed by participant modals).
        $equipes = $this->loadTeams($code);

        // Enrolled participants — one row per person.
        $participants = DB::table('evenement_participation as ep')
            ->join('pompier as p', 'ep.P_ID', '=', 'p.P_ID')
            ->leftJoin('type_participation as tp', 'tp.TP_ID', '=', 'ep.TP_ID')
            ->leftJoin('evenement_equipe as ee', function ($j) use ($code) {
                $j->on('ee.EE_ID', '=', 'ep.EE_ID')->where('ee.E_CODE', '=', $code);
            })
            ->where('ep.E_CODE', $code)
            ->groupBy(
                'p.P_ID', 'p.P_NOM', 'p.P_PRENOM', 'p.P_PHOTO',
                'p.P_GRADE', 'p.P_STATUT', 'ep.TP_ID', 'tp.TP_LIBELLE',
                'ep.EE_ID', 'ee.EE_NAME', 'ep.EP_COMMENT', 'ep.EP_ABSENT'
            )
            ->orderBy('p.P_NOM')
            ->orderBy('p.P_PRENOM')
            ->select(
                'p.P_ID', 'p.P_NOM', 'p.P_PRENOM', 'p.P_PHOTO',
                'p.P_GRADE', 'p.P_STATUT', 'ep.TP_ID', 'tp.TP_LIBELLE',
                'ep.EE_ID', 'ee.EE_NAME', 'ep.EP_COMMENT', 'ep.EP_ABSENT'
            )
            ->get();

        // Members not yet enrolled — candidates for the add-participant modal.
        $candidates = DB::table('pompier as p')
            ->where('p.P_OLD_MEMBER', 0)
            ->whereNotExists(function ($q) use ($code) {
                $q->select(DB::raw(1))
                    ->from('evenement_participation as ep')
                    ->whereColumn('ep.P_ID', 'p.P_ID')
                    ->where('ep.E_CODE', $code);
            })
            ->orderBy('p.P_NOM')
            ->orderBy('p.P_PRENOM')
            ->get(['p.P_ID', 'p.P_NOM', 'p.P_PRENOM', 'p.P_GRADE']);

        // Vehicles assigned to this event.
        $vehicules = DB::table('evenement_vehicule as ev')
            ->join('vehicule as v', 'ev.V_ID', '=', 'v.V_ID')
            ->where('ev.E_CODE', $code)
            ->select('v.V_ID', 'v.V_IMMATRICULATION', 'v.V_INDICATIF', 'ev.EV_KM')
            ->get();

        // All vehicles (for assignment modal).
        $allVehicles = DB::table('vehicule')
            ->orderBy('V_IMMATRICULATION')
            ->orderBy('V_INDICATIF')
            ->get(['V_ID', 'V_IMMATRICULATION', 'V_INDICATIF']);

        // Materials assigned to this event.
        $materiels = DB::table('evenement_materiel as em')
            ->join('materiel as m', 'em.MA_ID', '=', 'm.MA_ID')
            ->where('em.E_CODE', $code)
            ->orderBy('m.MA_MODELE')
            ->select('m.MA_ID', 'm.MA_MODELE', 'm.MA_NUMERO_SERIE', 'em.EM_NB', 'em.EE_ID')
            ->get();

        // All materials (for the assign modal).
        $allMateriels = DB::table('materiel')
            ->orderBy('MA_MODELE')
            ->get(['MA_ID', 'MA_MODELE', 'MA_NUMERO_SERIE']);

        // Reinforcement sub-events.
        $renforts = DB::table('evenement as e')
            ->leftJoin('evenement_participation as ep', function ($j) {
                $j->on('ep.E_CODE', '=', 'e.E_CODE')->where('ep.EP_ABSENT', '=', 0);
            })
            ->where('e.E_PARENT', $code)
            ->groupBy('e.E_CODE', 'e.E_LIBELLE', 'e.E_LIEU', 'e.E_CANCELED')
            ->orderBy('e.E_CODE')
            ->select(
                'e.E_CODE', 'e.E_LIBELLE', 'e.E_LIEU', 'e.E_CANCELED',
                DB::raw('COUNT(DISTINCT ep.P_ID) as participant_count')
            )
            ->get();

        // Reinforcement request summary (for the show card preview).
        $renfortCandidates = $this->renfortAttachCandidates((int) $event->E_CODE);

        $renfortRequest = DB::table('demande_renfort_vehicule')
            ->where('E_CODE', $code)
            ->where('TV_CODE', '0')
            ->first(['NB_VEHICULES', 'POINT_REGROUPEMENT', 'DEMANDE_SPECIFIQUE']);

        $renfortVehicleTypes = DB::table('demande_renfort_vehicule as drv')
            ->join('type_vehicule as tv', 'tv.TV_CODE', '=', 'drv.TV_CODE')
            ->where('drv.E_CODE', $code)
            ->where('drv.TV_CODE', '<>', '0')
            ->where('drv.NB_VEHICULES', '>', 0)
            ->select('drv.TV_CODE', 'drv.NB_VEHICULES', 'tv.TV_LIBELLE')
            ->get();

        $renfortMaterials = DB::table('demande_renfort_materiel as drm')
            ->leftJoin('type_materiel as tm', 'tm.TM_ID', '=', DB::raw('CAST(drm.TYPE_MATERIEL AS UNSIGNED)'))
            ->leftJoin('categorie_materiel as cm', 'cm.TM_USAGE', '=', 'drm.TYPE_MATERIEL')
            ->where('drm.E_CODE', $code)
            ->select('drm.TYPE_MATERIEL', 'tm.TM_CODE', 'tm.TM_DESCRIPTION', 'cm.CM_DESCRIPTION as CAT_DESCRIPTION')
            ->get();

        // Required positions (postes requis) — with actual qualified headcount.
        $activeCount = DB::table('evenement_participation')
            ->where('E_CODE', $code)
            ->where('EP_ABSENT', 0)
            ->distinct()
            ->count('P_ID');

        $requiredPositions = DB::table('evenement_competences as ec')
            ->leftJoin('poste as p', 'p.PS_ID', '=', 'ec.PS_ID')
            ->where('ec.E_CODE', $code)
            ->where('ec.EH_ID', 1)
            ->orderByRaw('ec.PS_ID = 0 DESC')
            ->orderBy('p.TYPE')
            ->select('ec.PS_ID', 'ec.NB', 'p.TYPE', 'p.DESCRIPTION')
            ->get()
            ->map(function ($row) use ($code, $activeCount) {
                if ($row->PS_ID == 0) {
                    $row->actual = $activeCount;
                    $row->label = 'Total participants';
                } else {
                    $row->actual = DB::table('evenement_participation as ep')
                        ->where('ep.E_CODE', $code)
                        ->where('ep.EP_ABSENT', 0)
                        ->whereExists(function ($q) use ($row) {
                            $q->select(DB::raw(1))
                                ->from('qualification as q')
                                ->whereColumn('q.P_ID', 'ep.P_ID')
                                ->where('q.PS_ID', $row->PS_ID);
                        })
                        ->distinct()
                        ->count('ep.P_ID');
                    $row->label = $row->TYPE.' – '.$row->DESCRIPTION;
                }

                return $row;
            });

        // Available positions not yet listed as required (for the add form).
        $availablePositions = DB::table('poste')
            ->whereNotIn('PS_ID', $requiredPositions->pluck('PS_ID'))
            ->orderBy('TYPE')
            ->get(['PS_ID', 'TYPE', 'DESCRIPTION']);

        // Event options (groups + options with per-type response counts).
        $optionGroups = DB::table('evenement_option_group')
            ->where('E_CODE', $code)
            ->orderBy('EOG_ORDER')
            ->get(['EOG_ID', 'EOG_TITLE', 'EOG_ORDER']);

        $eventOptions = DB::table('evenement_option as eo')
            ->leftJoin('evenement_option_group as eog', 'eog.EOG_ID', '=', 'eo.EOG_ID')
            ->where('eo.E_CODE', $code)
            ->orderByRaw('COALESCE(eog.EOG_ORDER, 999)')
            ->orderBy('eo.EO_ORDER')
            ->orderBy('eo.EO_TITLE')
            ->select('eo.EO_ID', 'eo.EO_TITLE', 'eo.EO_COMMENT', 'eo.EO_TYPE', 'eo.EO_ORDER', 'eo.EOG_ID', 'eog.EOG_TITLE')
            ->get()
            ->map(function ($opt) {
                $opt->dropdown_choices = $opt->EO_TYPE === 'dropdown'
                    ? DB::table('evenement_option_dropdown')
                        ->where('EO_ID', $opt->EO_ID)
                        ->orderBy('EOD_ORDER')
                        ->get(['EOD_ID', 'EOD_ORDER', 'EOD_TEXTE'])
                    : collect();

                $q = DB::table('evenement_option_choix')->where('EO_ID', $opt->EO_ID);
                if ($opt->EO_TYPE === 'checkbox') {
                    $q->where('EOC_VALUE', '1');
                } elseif ($opt->EO_TYPE === 'dropdown') {
                    $q->where('EOC_VALUE', '>', 0);
                } elseif ($opt->EO_TYPE === 'textnum') {
                    $q->where('EOC_VALUE', '<>', '');
                } elseif (in_array($opt->EO_TYPE, ['text', 'date', 'hour'])) {
                    $q->where('EOC_VALUE', '<>', '');
                }
                $opt->response_count = $q->count();

                return $opt;
            });

        // Main courante (incident log)
        $eventLog = DB::table('evenement_log as el')
            ->leftJoin('type_evenement_log as tel', 'tel.TEL_CODE', '=', 'el.TEL_CODE')
            ->leftJoin('pompier as p', 'p.P_ID', '=', 'el.EL_AUTHOR')
            ->where('el.E_CODE', $code)
            ->orderByDesc('el.EL_DEBUT')
            ->select(
                'el.EL_ID', 'el.TEL_CODE', 'el.EL_DEBUT', 'el.EL_FIN', 'el.EL_SLL',
                'el.EL_TITLE', 'el.EL_COMMENTAIRE', 'el.EL_IMPORTANT',
                'el.EL_DATE_ADD', 'el.EL_AUTHOR',
                'tel.TEL_DESCRIPTION',
                'p.P_NOM', 'p.P_PRENOM'
            )
            ->get();

        $logTypes = DB::table('type_evenement_log')->orderBy('TEL_CODE')->get(['TEL_CODE', 'TEL_DESCRIPTION']);

        return view('event.show', compact(
            'event', 'typeLabel', 'participants', 'candidates', 'vehicules', 'allVehicles',
            'functions', 'equipes', 'renforts', 'materiels', 'allMateriels',
            'requiredPositions', 'availablePositions', 'activeCount',
            'renfortRequest', 'renfortVehicleTypes', 'renfortMaterials', 'renfortCandidates',
            'optionGroups', 'eventOptions',
            'eventLog', 'logTypes'
        ));
    }

    /** Teams (equipes) for an event with member + vehicle counts. */
    private function loadTeams(string|int $code): Collection
    {
        return DB::table('evenement_equipe as ee')
            ->where('ee.E_CODE', $code)
            ->orderBy('ee.EE_ORDER')
            ->orderBy('ee.EE_NAME')
            ->select('ee.EE_ID', 'ee.EE_NAME', 'ee.EE_DESCRIPTION', 'ee.EE_ORDER', 'ee.EE_ID_RADIO')
            ->get()
            ->map(function ($e) use ($code) {
                $e->member_count = DB::table('evenement_participation')
                    ->where('E_CODE', $code)->where('EE_ID', $e->EE_ID)
                    ->distinct()->count('P_ID');
                $e->vehicle_count = DB::table('evenement_vehicule')
                    ->where('E_CODE', $code)->where('EE_ID', $e->EE_ID)
                    ->distinct()->count('V_ID');

                return $e;
            });
    }

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(): View
    {
        [$groupedTypes, $sections, $chefs] = $this->formLookups();

        return view('event.form', [
            'event' => null,
            'horaire' => null,
            'groupedTypes' => $groupedTypes,
            'sections' => $sections,
            'chefs' => $chefs,
            'parentCandidates' => $this->renfortParentCandidates(null),
            'renfortParent' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateEventRequest($request, isCreate: true);

        $code = DB::transaction(function () use ($validated, $request) {
            $code = (int) DB::table('evenement')->max('E_CODE') + 1;

            DB::table('evenement')->insert([
                'E_CODE' => $code,
                'TE_CODE' => $validated['TE_CODE'],
                'E_PARENT' => $this->resolveRenfortParent($validated['TE_CODE'], $validated['E_PARENT'] ?? null, null),
                'S_ID' => $validated['S_ID'],
                'E_LIBELLE' => $validated['E_LIBELLE'],
                'E_LIEU' => $validated['E_LIEU'] ?? '',
                'E_ADDRESS' => $validated['E_ADDRESS'] ?? null,
                'E_LIEU_RDV' => $validated['E_LIEU_RDV'] ?? null,
                'E_HEURE_RDV' => $validated['E_HEURE_RDV'] ?? null,
                'E_NB' => $validated['E_NB'] ?? 0,
                'E_CHEF' => $validated['E_CHEF'] ?? null,
                'E_TEL' => $validated['E_TEL'] ?? null,
                'E_COMMENT' => $validated['E_COMMENT'] ?? '',
                'E_CONSIGNES' => $validated['E_CONSIGNES'] ?? null,
                'E_CONTACT_LOCAL' => $validated['E_CONTACT_LOCAL'] ?? null,
                'E_CONTACT_TEL' => $validated['E_CONTACT_TEL'] ?? null,
                'E_WHATSAPP' => $validated['E_WHATSAPP'] ?? null,
                'E_WEBEX_URL' => $validated['E_WEBEX_URL'] ?? null,
                'E_WEBEX_PIN' => $validated['E_WEBEX_PIN'] ?? null,
                'E_WEBEX_START' => $validated['E_WEBEX_START'] ?? null,
                'E_CLOSED' => 0,
                'E_CANCELED' => 0,
                'E_EQUIPE' => 0,
                'E_ANOMALIE' => 0,
                'E_FLAG1' => 0,
                'E_OPEN_TO_EXT' => (int) $request->boolean('E_OPEN_TO_EXT'),
                'E_VISIBLE_OUTSIDE' => (int) $request->boolean('E_VISIBLE_OUTSIDE'),
                'E_EXTERIEUR' => (int) $request->boolean('E_EXTERIEUR'),
                'E_VISIBLE_INSIDE' => (int) ! $request->boolean('E_HIDDEN'),
                'E_ALLOW_REINFORCEMENT' => (int) $request->boolean('E_ALLOW_REINFORCEMENT'),
                'E_AUTOCLOSE_BEFORE' => $validated['E_AUTOCLOSE_BEFORE'] ?? null,
                'E_CREATED_BY' => auth()->id(),
                'E_CREATE_DATE' => now(),
            ]);

            foreach ($validated['horaires'] as $i => $h) {
                $dateDebut = Carbon::parse($h['EH_DATE_DEBUT'])->toDateString();
                $dateFin = ! empty($h['EH_DATE_FIN'])
                    ? Carbon::parse($h['EH_DATE_FIN'])->toDateString()
                    : $dateDebut;

                DB::table('evenement_horaire')->insert([
                    'E_CODE' => $code,
                    'EH_ID' => $i + 1,
                    'EH_DATE_DEBUT' => $dateDebut,
                    'EH_DATE_FIN' => $dateFin,
                    'EH_DEBUT' => ! empty($h['EH_DEBUT']) ? $h['EH_DEBUT'] : '00:00:00',
                    'EH_FIN' => ! empty($h['EH_FIN']) ? $h['EH_FIN'] : '00:00:00',
                    'EH_DUREE' => 0,
                    'EH_DESCRIPTION' => '',
                ]);
            }

            return $code;
        });

        return redirect()->route('event.show', $code)
            ->with('success', 'Activité créée avec succès.');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function edit(string $code): View
    {
        $event = Event::with(['horaires'])->findOrFail($code);
        $horaires = $event->horaires->sortBy('EH_ID')->values();

        [$groupedTypes, $sections, $chefs] = $this->formLookups();

        $parentCandidates = $this->renfortParentCandidates((int) $event->E_CODE);
        $renfortParent = $event->E_PARENT ? Event::find($event->E_PARENT) : null;

        return view('event.form', compact('event', 'horaires', 'groupedTypes', 'sections', 'chefs', 'parentCandidates', 'renfortParent'));
    }

    public function update(Request $request, string $code): RedirectResponse
    {
        $event = Event::findOrFail($code);
        $validated = $this->validateEventRequest($request, isCreate: false);

        DB::transaction(function () use ($event, $validated, $request) {
            // A renfort can pick its main event only while it isn't attached yet;
            // once attached, detaching happens from the main event.
            if ($event->E_PARENT === null) {
                $parent = $this->resolveRenfortParent($validated['TE_CODE'], $validated['E_PARENT'] ?? null, (int) $event->E_CODE);
                if ($parent !== null) {
                    $event->E_PARENT = $parent;
                }
            }

            $event->update([
                'TE_CODE' => $validated['TE_CODE'],
                'S_ID' => $validated['S_ID'],
                'E_LIBELLE' => $validated['E_LIBELLE'],
                'E_LIEU' => $validated['E_LIEU'] ?? '',
                'E_ADDRESS' => $validated['E_ADDRESS'] ?? null,
                'E_LIEU_RDV' => $validated['E_LIEU_RDV'] ?? null,
                'E_HEURE_RDV' => $validated['E_HEURE_RDV'] ?? null,
                'E_NB' => $validated['E_NB'] ?? 0,
                'E_CHEF' => $validated['E_CHEF'] ?? null,
                'E_TEL' => $validated['E_TEL'] ?? null,
                'E_COMMENT' => $validated['E_COMMENT'] ?? '',
                'E_CONSIGNES' => $validated['E_CONSIGNES'] ?? null,
                'E_CONTACT_LOCAL' => $validated['E_CONTACT_LOCAL'] ?? null,
                'E_CONTACT_TEL' => $validated['E_CONTACT_TEL'] ?? null,
                'E_WHATSAPP' => $validated['E_WHATSAPP'] ?? null,
                'E_WEBEX_URL' => $validated['E_WEBEX_URL'] ?? null,
                'E_WEBEX_PIN' => $validated['E_WEBEX_PIN'] ?? null,
                'E_WEBEX_START' => $validated['E_WEBEX_START'] ?? null,
                'E_CLOSED' => (int) $request->boolean('E_CLOSED'),
                'E_CANCELED' => (int) $request->boolean('E_CANCELED'),
                'E_OPEN_TO_EXT' => (int) $request->boolean('E_OPEN_TO_EXT'),
                'E_VISIBLE_OUTSIDE' => (int) $request->boolean('E_VISIBLE_OUTSIDE'),
                'E_EXTERIEUR' => (int) $request->boolean('E_EXTERIEUR'),
                'E_VISIBLE_INSIDE' => (int) ! $request->boolean('E_HIDDEN'),
                'E_ALLOW_REINFORCEMENT' => (int) $request->boolean('E_ALLOW_REINFORCEMENT'),
                'E_AUTOCLOSE_BEFORE' => $validated['E_AUTOCLOSE_BEFORE'] ?? null,
            ]);

            $submittedIds = [];
            foreach ($validated['horaires'] as $i => $h) {
                $ehId = $i + 1;
                $submittedIds[] = $ehId;

                $dateDebut = Carbon::parse($h['EH_DATE_DEBUT'])->toDateString();
                $dateFin = ! empty($h['EH_DATE_FIN'])
                    ? Carbon::parse($h['EH_DATE_FIN'])->toDateString()
                    : $dateDebut;

                DB::table('evenement_horaire')->updateOrInsert(
                    ['E_CODE' => $event->E_CODE, 'EH_ID' => $ehId],
                    [
                        'EH_DATE_DEBUT' => $dateDebut,
                        'EH_DATE_FIN' => $dateFin,
                        'EH_DEBUT' => ! empty($h['EH_DEBUT']) ? $h['EH_DEBUT'] : '00:00:00',
                        'EH_FIN' => ! empty($h['EH_FIN']) ? $h['EH_FIN'] : '00:00:00',
                        'EH_DUREE' => 0,
                        'EH_DESCRIPTION' => '',
                    ]
                );
            }

            // Remove horaires that were deleted in the form
            DB::table('evenement_horaire')
                ->where('E_CODE', $event->E_CODE)
                ->whereNotIn('EH_ID', $submittedIds)
                ->delete();
        });

        return redirect()->route('event.show', $code)
            ->with('success', 'Activité mise à jour.');
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function destroy(string $code): RedirectResponse
    {
        $event = Event::findOrFail($code);

        DB::transaction(function () use ($event) {
            $c = $event->E_CODE;
            DB::table('evenement_participation')->where('E_CODE', $c)->delete();
            DB::table('evenement_vehicule')->where('E_CODE', $c)->delete();
            DB::table('evenement_materiel')->where('E_CODE', $c)->delete();
            DB::table('evenement_chef')->where('E_CODE', $c)->delete();
            DB::table('evenement_horaire')->where('E_CODE', $c)->delete();
            $event->delete();
        });

        return redirect()->route('event.index')
            ->with('success', 'Activité supprimée.');
    }

    // ── Participant management ────────────────────────────────────────────────

    public function participantStore(Request $request, string $code): RedirectResponse
    {
        $event = Event::findOrFail($code);

        $validated = $request->validate([
            'P_ID' => ['required', 'integer', 'exists:pompier,P_ID'],
            'EH_ID' => ['required', 'integer', 'exists:evenement_horaire,EH_ID'],
            'TP_ID' => ['nullable', 'integer'],
            'EE_ID' => ['nullable', 'integer'],
            'EP_COMMENT' => ['nullable', 'string', 'max:150'],
        ]);

        $already = DB::table('evenement_participation')
            ->where('E_CODE', $code)
            ->where('P_ID', $validated['P_ID'])
            ->where('EH_ID', $validated['EH_ID'])
            ->exists();

        if ($already) {
            return redirect()->route('event.show', [$code, 'tab' => 'personnel'])
                ->with('error', 'Ce membre est déjà inscrit sur ce créneau.');
        }

        // Photo obligatoire (setting 68): a member without a profile photo
        // cannot register THEMSELF; registering someone else is never blocked.
        if ($this->selfRegistrationBlockedByPhoto((int) $validated['P_ID'])) {
            return redirect()->route('event.show', [$code, 'tab' => 'personnel'])
                ->with('error', __('event.photo_required'));
        }

        $duree = DB::table('evenement_horaire')
            ->where('E_CODE', $code)
            ->where('EH_ID', $validated['EH_ID'])
            ->value('EH_DUREE');

        DB::table('evenement_participation')->insert([
            'E_CODE' => $code,
            'EH_ID' => $validated['EH_ID'],
            'P_ID' => $validated['P_ID'],
            'EP_DATE' => now(),
            'EP_BY' => auth()->id(),
            'TP_ID' => $validated['TP_ID'] ?? 0,
            'EE_ID' => $validated['EE_ID'] ?? null,
            'EP_COMMENT' => $validated['EP_COMMENT'] ?? null,
            'EP_DUREE' => $duree ?? 0,
            'EP_ABSENT' => 0,
            'EP_FLAG1' => 0,
        ]);

        return redirect()->route('event.show', [$code, 'tab' => 'personnel'])
            ->with('success', 'Participant ajouté.');
    }

    /**
     * True when the mandatory-photo setting is on, the target member is the
     * authenticated user themself and their profile photo is missing.
     * Registration BY someone else (manager, admin) is never blocked — see
     * the setting's description. Guarded: an unreadable setting must never
     * block registrations.
     */
    private function selfRegistrationBlockedByPhoto(int $targetPid): bool
    {
        $user = auth()->user();
        if ($user === null || (int) $user->P_ID !== $targetPid) {
            return false;
        }

        try {
            return app(GeneralSettingService::class)->photoRequired()
                && trim((string) ($user->P_PHOTO ?? '')) === '';
        } catch (\Throwable) {
            return false;
        }
    }

    public function participantUpdate(Request $request, string $code, int $pid): RedirectResponse
    {
        Event::findOrFail($code);

        $validated = $request->validate([
            'TP_ID' => ['nullable', 'integer'],
            'EE_ID' => ['nullable', 'integer'],
            'EP_COMMENT' => ['nullable', 'string', 'max:150'],
            'EP_ABSENT' => ['boolean'],
        ]);

        DB::table('evenement_participation')
            ->where('E_CODE', $code)
            ->where('P_ID', $pid)
            ->update([
                'TP_ID' => $validated['TP_ID'] ?? 0,
                'EE_ID' => $validated['EE_ID'] ?? null,
                'EP_COMMENT' => $validated['EP_COMMENT'] ?? null,
                'EP_ABSENT' => $request->boolean('EP_ABSENT') ? 1 : 0,
            ]);

        return redirect()->route('event.show', [$code, 'tab' => 'personnel'])
            ->with('success', 'Participation mise à jour.');
    }

    public function participantDestroy(string $code, int $pid): RedirectResponse
    {
        Event::findOrFail($code);

        DB::table('evenement_participation')
            ->where('E_CODE', $code)
            ->where('P_ID', $pid)
            ->delete();

        return redirect()->route('event.show', [$code, 'tab' => 'personnel'])
            ->with('success', 'Participant retiré.');
    }

    // ── Équipes CRUD ──────────────────────────────────────────────────────────

    public function teamStore(Request $request, string $code): RedirectResponse
    {
        Event::findOrFail($code);

        $validated = $request->validate([
            'EE_NAME' => ['required', 'string', 'max:30'],
            'EE_ORDER' => ['nullable', 'integer', 'min:1', 'max:50'],
            'EE_DESCRIPTION' => ['nullable', 'string', 'max:300'],
            'EE_ID_RADIO' => ['nullable', 'string', 'max:12'],
        ]);

        $newId = (int) DB::table('evenement_equipe')
            ->where('E_CODE', $code)
            ->max('EE_ID') + 1;

        DB::table('evenement_equipe')->insert([
            'E_CODE' => $code,
            'EE_ID' => $newId ?: 1,
            'EE_NAME' => $validated['EE_NAME'],
            'EE_ORDER' => $validated['EE_ORDER'] ?? 1,
            'EE_DESCRIPTION' => $validated['EE_DESCRIPTION'] ?? '',
            'EE_ID_RADIO' => $validated['EE_ID_RADIO'] ?? null,
            'EE_SIGNATURE' => 0,
        ]);

        return redirect()->route('event.show', [$code, 'tab' => 'equipes'])
            ->with('success', 'Équipe créée.');
    }

    public function teamUpdate(Request $request, string $code, int $ee): RedirectResponse
    {
        Event::findOrFail($code);

        $validated = $request->validate([
            'EE_NAME' => ['required', 'string', 'max:30'],
            'EE_ORDER' => ['nullable', 'integer', 'min:1', 'max:50'],
            'EE_DESCRIPTION' => ['nullable', 'string', 'max:300'],
            'EE_ID_RADIO' => ['nullable', 'string', 'max:12'],
        ]);

        DB::table('evenement_equipe')
            ->where('E_CODE', $code)
            ->where('EE_ID', $ee)
            ->update([
                'EE_NAME' => $validated['EE_NAME'],
                'EE_ORDER' => $validated['EE_ORDER'] ?? 1,
                'EE_DESCRIPTION' => $validated['EE_DESCRIPTION'] ?? '',
                'EE_ID_RADIO' => $validated['EE_ID_RADIO'] ?? null,
            ]);

        return redirect()->route('event.show', [$code, 'tab' => 'equipes'])
            ->with('success', 'Équipe mise à jour.');
    }

    public function teamDestroy(string $code, int $ee): RedirectResponse
    {
        Event::findOrFail($code);

        DB::transaction(function () use ($code, $ee) {
            DB::table('evenement_equipe')
                ->where('E_CODE', $code)->where('EE_ID', $ee)->delete();
            DB::table('evenement_participation')
                ->where('E_CODE', $code)->where('EE_ID', $ee)
                ->update(['EE_ID' => null]);
            DB::table('evenement_vehicule')
                ->where('E_CODE', $code)->where('EE_ID', $ee)
                ->update(['EE_ID' => null]);
        });

        return redirect()->route('event.show', [$code, 'tab' => 'equipes'])
            ->with('success', 'Équipe supprimée.');
    }

    // ── Renforts (reinforcement sub-events) ──────────────────────────────────

    public function reinforcementAttach(Request $request, string $code): RedirectResponse
    {
        $event = Event::findOrFail($code);

        $validated = $request->validate([
            'renfort' => ['required', 'integer', 'different:'.$code],
        ]);

        $renfort = Event::findOrFail((int) $validated['renfort']);

        if ($renfort->E_PARENT) {
            return redirect()->route('event.show', [$code, 'tab' => 'renforts'])
                ->with('error', 'Cet événement est déjà rattaché à un autre événement principal.');
        }
        if ($renfort->E_CANCELED) {
            return redirect()->route('event.show', [$code, 'tab' => 'renforts'])
                ->with('error', 'Cet événement est annulé et ne peut pas être rattaché.');
        }

        $renfort->update(['E_PARENT' => $event->E_CODE]);

        return redirect()->route('event.show', [$code, 'tab' => 'renforts'])
            ->with('success', 'Renfort rattaché.');
    }

    public function reinforcementDetach(string $code, string $renfort): RedirectResponse
    {
        Event::findOrFail($code);

        Event::where('E_CODE', $renfort)
            ->where('E_PARENT', $code)
            ->update(['E_PARENT' => null]);

        return redirect()->route('event.show', [$code, 'tab' => 'renforts'])
            ->with('success', 'Renfort détaché.');
    }

    // ── Vehicle assignment ─────────────────────────────────────────────────────

    public function vehicleAttach(Request $request, string $code): RedirectResponse
    {
        $event = Event::findOrFail($code);

        $validated = $request->validate([
            'V_ID' => ['required', 'integer', 'exists:vehicule,V_ID'],
        ]);

        $already = $event->vehicules()->where('vehicule.V_ID', $validated['V_ID'])->exists();
        if ($already) {
            return redirect()->route('event.show', $code)
                ->with('error', 'Ce véhicule est déjà assigné à cette activité.');
        }

        $event->vehicules()->attach($validated['V_ID']);

        return redirect()->route('event.show', $code)
            ->with('success', 'Véhicule assigné.');
    }

    public function vehicleDetach(string $code, int $vehicule): RedirectResponse
    {
        $event = Event::findOrFail($code);
        $event->vehicules()->detach($vehicule);

        return redirect()->route('event.show', $code)
            ->with('success', 'Véhicule désassigné.');
    }

    // ── Form lookups ─────────────────────────────────────────────────────────

    /**
     * Main events a "Renfort" activity can be attached to: ongoing/upcoming,
     * not cancelled, not themselves attached nor renforts, and either open to
     * reinforcements or carrying a reinforcement request.
     *
     * Each item is an object with `E_CODE` (int) and a display `label`.
     */
    private function renfortParentCandidates(?int $exclude): Collection
    {
        $hours = DB::table('evenement_horaire')
            ->select('E_CODE', DB::raw('MIN(EH_DATE_DEBUT) as first_day'), DB::raw('MAX(EH_DATE_FIN) as last_day'))
            ->groupBy('E_CODE');

        return DB::table('evenement as e')
            ->joinSub($hours, 'h', 'h.E_CODE', '=', 'e.E_CODE')
            ->leftJoin('section as s', 'e.S_ID', '=', 's.S_ID')
            ->where('e.E_CANCELED', 0)
            ->whereNull('e.E_PARENT')
            ->where('e.TE_CODE', '<>', self::RENFORT_TYPE)
            ->where('h.last_day', '>=', now()->toDateString())
            ->where(function ($q) {
                $q->where('e.E_ALLOW_REINFORCEMENT', 1)
                    ->orWhereExists(fn ($sub) => $sub->from('demande_renfort_vehicule as d')->whereColumn('d.E_CODE', 'e.E_CODE'));
            })
            ->when($exclude !== null, fn ($q) => $q->where('e.E_CODE', '<>', $exclude))
            ->orderBy('h.first_day')
            ->get(['e.E_CODE', 'e.E_LIBELLE', 'h.first_day', 's.S_CODE'])
            ->map(fn ($e) => (object) [
                'E_CODE' => (int) $e->E_CODE,
                'label' => ($e->E_LIBELLE ?: $e->E_CODE).' — '.Carbon::parse($e->first_day)->format('d/m/Y')
                    .($e->S_CODE ? ' ('.$e->S_CODE.')' : ''),
            ]);
    }

    /**
     * Activities that can be attached as a renfort to a main event: ongoing or
     * upcoming, not cancelled, not already attached, not the event itself and
     * not a main event with renforts of its own. Grouped so "Renfort"-type
     * activities come first.
     *
     * @return array{renforts: Collection, others: Collection}
     */
    private function renfortAttachCandidates(int $mainCode): array
    {
        $hours = DB::table('evenement_horaire')
            ->select('E_CODE', DB::raw('MIN(EH_DATE_DEBUT) as first_day'), DB::raw('MAX(EH_DATE_FIN) as last_day'))
            ->groupBy('E_CODE');

        $rows = DB::table('evenement as e')
            ->joinSub($hours, 'h', 'h.E_CODE', '=', 'e.E_CODE')
            ->leftJoin('section as s', 'e.S_ID', '=', 's.S_ID')
            ->where('e.E_CANCELED', 0)
            ->whereNull('e.E_PARENT')
            ->where('e.E_CODE', '<>', $mainCode)
            ->where('e.TE_CODE', '<>', 'MC')
            ->where('h.last_day', '>=', now()->toDateString())
            ->whereNotExists(fn ($q) => $q->from('evenement as c')->whereColumn('c.E_PARENT', 'e.E_CODE'))
            ->orderBy('h.first_day')
            ->get(['e.E_CODE', 'e.E_LIBELLE', 'e.TE_CODE', 'h.first_day', 's.S_CODE'])
            ->map(fn ($e) => (object) [
                'E_CODE' => (int) $e->E_CODE,
                'TE_CODE' => $e->TE_CODE,
                'label' => '#'.$e->E_CODE.' — '.($e->E_LIBELLE ?: $e->E_CODE).' — '
                    .Carbon::parse($e->first_day)->format('d/m/Y').($e->S_CODE ? ' ('.$e->S_CODE.')' : ''),
            ]);

        return [
            'renforts' => $rows->where('TE_CODE', self::RENFORT_TYPE)->values(),
            'others' => $rows->where('TE_CODE', '<>', self::RENFORT_TYPE)->values(),
        ];
    }

    /** The main event to attach, when the activity is a renfort and the choice is valid. */
    private function resolveRenfortParent(string $type, mixed $parent, ?int $self): ?int
    {
        if ($type !== self::RENFORT_TYPE || empty($parent)) {
            return null;
        }

        $parent = (int) $parent;

        return $this->renfortParentCandidates($self)->contains('E_CODE', $parent) ? $parent : null;
    }

    private function formLookups(): array
    {
        $categories = DB::table('categorie_evenement')
            ->orderBy('CEV_DESCRIPTION')
            ->pluck('CEV_DESCRIPTION', 'CEV_CODE');

        $groupedTypes = DB::table('type_evenement as te')
            ->where('te.TE_CODE', '<>', 'MC')
            ->orderBy('te.TE_LIBELLE')
            ->get(['te.TE_CODE', 'te.TE_LIBELLE', 'te.CEV_CODE'])
            ->groupBy('CEV_CODE')
            ->map(fn ($types, $cev) => [
                'label' => $categories[$cev] ?? $cev,
                'types' => $types,
            ])
            ->sortBy('label');

        $sections = Section::orderBy('S_CODE')->get(['S_ID', 'S_CODE', 'S_DESCRIPTION']);

        $chefs = Personnel::where('P_OLD_MEMBER', 0)
            ->where('P_STATUT', '!=', 'EXT')
            ->orderBy('P_NOM')
            ->orderBy('P_PRENOM')
            ->get(['P_ID', 'P_NOM', 'P_PRENOM', 'P_SECTION']);

        return [$groupedTypes, $sections, $chefs];
    }

    // ── Equipe member / material management ──────────────────────────────────

    public function teamAddParticipant(Request $request, string $code, int $ee): RedirectResponse
    {
        Event::findOrFail($code);

        $validated = $request->validate(['P_ID' => ['required', 'integer', 'exists:pompier,P_ID']]);

        DB::table('evenement_participation')
            ->where('E_CODE', $code)
            ->where('P_ID', $validated['P_ID'])
            ->update(['EE_ID' => $ee]);

        return back()->with('success', 'Participant ajouté à l\'équipe.');
    }

    public function teamAddEquipment(Request $request, string $code, int $ee): RedirectResponse
    {
        Event::findOrFail($code);

        $validated = $request->validate([
            'MA_ID' => ['required', 'integer', 'exists:materiel,MA_ID'],
            'EM_NB' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $existing = DB::table('evenement_materiel')
            ->where('E_CODE', $code)
            ->where('MA_ID', $validated['MA_ID'])
            ->first();

        if ($existing) {
            DB::table('evenement_materiel')
                ->where('E_CODE', $code)
                ->where('MA_ID', $validated['MA_ID'])
                ->update(['EE_ID' => $ee, 'EM_NB' => $validated['EM_NB'] ?? $existing->EM_NB]);
        } else {
            DB::table('evenement_materiel')->insert([
                'E_CODE' => $code,
                'MA_ID' => $validated['MA_ID'],
                'EM_NB' => $validated['EM_NB'] ?? 1,
                'EE_ID' => $ee,
            ]);
        }

        return back()->with('success', 'Matériel ajouté à l\'équipe.');
    }

    // ── Participant team quick-assign ────────────────────────────────────────

    public function participantTeam(Request $request, string $code, int $pid): RedirectResponse
    {
        Event::findOrFail($code);

        $validated = $request->validate(['EE_ID' => ['nullable', 'integer']]);

        DB::table('evenement_participation')
            ->where('E_CODE', $code)
            ->where('P_ID', $pid)
            ->update(['EE_ID' => $validated['EE_ID'] ?: null]);

        return back()->with('success', 'Équipe mise à jour.');
    }

    // ── Matériel assignment ───────────────────────────────────────────────────

    public function equipmentAttach(Request $request, string $code): RedirectResponse
    {
        Event::findOrFail($code);

        $validated = $request->validate([
            'MA_ID' => ['required', 'integer', 'exists:materiel,MA_ID'],
            'EM_NB' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'EE_ID' => ['nullable', 'integer'],
        ]);

        $already = DB::table('evenement_materiel')
            ->where('E_CODE', $code)
            ->where('MA_ID', $validated['MA_ID'])
            ->exists();

        if ($already) {
            return back()->with('error', 'Ce matériel est déjà assigné à cette activité.');
        }

        DB::table('evenement_materiel')->insert([
            'E_CODE' => $code,
            'MA_ID' => $validated['MA_ID'],
            'EM_NB' => $validated['EM_NB'] ?? 1,
            'EE_ID' => $validated['EE_ID'] ?: null,
        ]);

        return back()->with('success', 'Matériel assigné.');
    }

    public function equipmentUpdateQty(Request $request, string $code, int $ma): RedirectResponse
    {
        Event::findOrFail($code);

        $validated = $request->validate(['EM_NB' => ['required', 'integer', 'min:1', 'max:9999']]);

        DB::table('evenement_materiel')
            ->where('E_CODE', $code)
            ->where('MA_ID', $ma)
            ->update(['EM_NB' => $validated['EM_NB']]);

        return back()->with('success', 'Quantité mise à jour.');
    }

    public function equipmentDetach(string $code, int $ma): RedirectResponse
    {
        Event::findOrFail($code);

        DB::table('evenement_materiel')
            ->where('E_CODE', $code)
            ->where('MA_ID', $ma)
            ->delete();

        return back()->with('success', 'Matériel retiré.');
    }

    // ── Exports ───────────────────────────────────────────────────────────────

    public function exportParticipants(string $code)
    {
        $event = Event::findOrFail($code);

        $participants = DB::table('evenement_participation as ep')
            ->join('pompier as p', 'ep.P_ID', '=', 'p.P_ID')
            ->leftJoin('type_participation as tp', 'tp.TP_ID', '=', 'ep.TP_ID')
            ->leftJoin('evenement_equipe as ee', function ($j) use ($code) {
                $j->on('ee.EE_ID', '=', 'ep.EE_ID')->where('ee.E_CODE', '=', $code);
            })
            ->where('ep.E_CODE', $code)
            ->orderBy('p.P_NOM')
            ->orderBy('p.P_PRENOM')
            ->select(
                'p.P_NOM', 'p.P_PRENOM', 'p.P_GRADE',
                'tp.TP_LIBELLE', 'ee.EE_NAME',
                'ep.EP_COMMENT', 'ep.EP_ABSENT'
            )
            ->get();

        $columns = [
            ['Nom',         fn ($p) => strtoupper($p->P_NOM)],
            ['Prénom',      fn ($p) => ucfirst(mb_strtolower($p->P_PRENOM))],
            ['Grade',       fn ($p) => $p->P_GRADE ?? ''],
            ['Fonction',    fn ($p) => $p->TP_LIBELLE ?? ''],
            ['Équipe',      fn ($p) => $p->EE_NAME ?? ''],
            ['Commentaire', fn ($p) => $p->EP_COMMENT ?? ''],
            ['Absent',      fn ($p) => $p->EP_ABSENT ? 'Oui' : ''],
        ];

        return (new TableExportService)->toXlsx(
            $columns,
            $participants,
            'Activite_'.$event->E_CODE.'_Participants_'.date('Ymd'),
            ['sheetTitle' => 'Participants', 'freezeHeader' => true]
        );
    }

    public function exportVehicles(string $code)
    {
        $event = Event::findOrFail($code);

        $vehicules = DB::table('evenement_vehicule as ev')
            ->join('vehicule as v', 'ev.V_ID', '=', 'v.V_ID')
            ->leftJoin('type_vehicule as tv', 'tv.TV_CODE', '=', 'v.TV_CODE')
            ->where('ev.E_CODE', $code)
            ->orderBy('v.V_INDICATIF')
            ->orderBy('v.V_IMMATRICULATION')
            ->select(
                'v.V_INDICATIF', 'v.V_IMMATRICULATION', 'v.V_MODELE',
                'tv.TV_LIBELLE', 'ev.EV_KM'
            )
            ->get();

        $columns = [
            ['Indicatif',       fn ($v) => $v->V_INDICATIF ?? ''],
            ['Immatriculation', fn ($v) => $v->V_IMMATRICULATION ?? ''],
            ['Type',            fn ($v) => $v->TV_LIBELLE ?? ''],
            ['Modèle',          fn ($v) => $v->V_MODELE ?? ''],
            ['Km',              fn ($v) => $v->EV_KM ?? ''],
        ];

        return (new TableExportService)->toXlsx(
            $columns,
            $vehicules,
            'Activite_'.$event->E_CODE.'_Vehicules_'.date('Ymd'),
            ['sheetTitle' => 'Véhicules', 'freezeHeader' => true]
        );
    }

    public function exportIcal(string $code): Response
    {
        $event = Event::with('horaires')->findOrFail($code);

        $vevents = [];
        foreach ($event->horaires as $h) {
            $startDate = Carbon::parse($h->EH_DATE_DEBUT);
            $endDate = Carbon::parse($h->EH_DATE_FIN ?? $h->EH_DATE_DEBUT);

            $hasTime = $h->EH_DEBUT && substr((string) $h->EH_DEBUT, 0, 5) !== '00:00';

            if ($hasTime) {
                [$sh, $sm] = explode(':', substr((string) $h->EH_DEBUT, 0, 5));
                $startDate->setTime((int) $sh, (int) $sm)->timezone('Europe/Paris');
                if ($h->EH_FIN) {
                    [$eh, $em] = explode(':', substr((string) $h->EH_FIN, 0, 5));
                    $endDate->setTime((int) $eh, (int) $em)->timezone('Europe/Paris');
                }
            }

            $vevents[] = [
                'summary' => $event->E_LIBELLE ?? $event->E_CODE,
                'location' => $event->E_LIEU ?? '',
                'description' => $event->E_COMMENT ?? '',
                'uid' => 'ob-evt-'.$event->E_CODE.'-'.$h->EH_ID.'@'.request()->getHost(),
                'allDay' => ! $hasTime,
                'dtstart' => $startDate,
                'dtend' => $hasTime ? $endDate : $endDate->addDay(),
            ];
        }

        return (new ICalExportService)->toResponse(
            config('app.name'),
            $vevents,
            'activite-'.$event->E_CODE
        );
    }

    // ── Duplication ───────────────────────────────────────────────────────────

    public function duplicate(Request $request, string $code): RedirectResponse
    {
        $source = Event::with(['horaires'])->findOrFail($code);

        $validated = $request->validate([
            'new_date' => ['required', 'date'],
            'copy_participants' => ['boolean'],
            'copy_vehicles' => ['boolean'],
        ]);

        $newDate = Carbon::parse($validated['new_date']);

        $firstSchedule = $source->horaires->sortBy('EH_DATE_DEBUT')->first();
        $dayShift = $firstSchedule
            ? (int) Carbon::parse($firstSchedule->EH_DATE_DEBUT)->startOfDay()->diffInDays($newDate->copy()->startOfDay(), false)
            : 0;

        $newCode = DB::transaction(function () use ($source, $dayShift, $validated) {
            $newCode = (int) DB::table('evenement')->max('E_CODE') + 1;

            $row = DB::table('evenement')->where('E_CODE', $source->E_CODE)->first();
            $data = (array) $row;
            unset($data['E_CODE']);
            $data['E_CODE'] = $newCode;
            $data['E_CLOSED'] = 0;
            $data['E_CANCELED'] = 0;
            $data['E_CREATED_BY'] = auth()->id();
            $data['E_CREATE_DATE'] = now();

            DB::table('evenement')->insert($data);

            foreach ($source->horaires as $h) {
                $debut = Carbon::parse($h->EH_DATE_DEBUT)->addDays($dayShift)->toDateString();
                $fin = $h->EH_DATE_FIN
                    ? Carbon::parse($h->EH_DATE_FIN)->addDays($dayShift)->toDateString()
                    : $debut;

                DB::table('evenement_horaire')->insert([
                    'E_CODE' => $newCode,
                    'EH_ID' => $h->EH_ID,
                    'EH_DATE_DEBUT' => $debut,
                    'EH_DATE_FIN' => $fin,
                    'EH_DEBUT' => $h->EH_DEBUT ?? '00:00:00',
                    'EH_FIN' => $h->EH_FIN ?? '00:00:00',
                    'EH_DUREE' => $h->EH_DUREE ?? 0,
                    'EH_DESCRIPTION' => $h->EH_DESCRIPTION ?? '',
                ]);
            }

            if ($validated['copy_participants'] ?? false) {
                $rows = DB::table('evenement_participation')
                    ->where('E_CODE', $source->E_CODE)->get();
                foreach ($rows as $r) {
                    $p = (array) $r;
                    $p['E_CODE'] = $newCode;
                    DB::table('evenement_participation')->insert($p);
                }

                $teams = DB::table('evenement_equipe')
                    ->where('E_CODE', $source->E_CODE)->get();
                foreach ($teams as $t) {
                    $p = (array) $t;
                    $p['E_CODE'] = $newCode;
                    DB::table('evenement_equipe')->insert($p);
                }

                $chefs = DB::table('evenement_chef')
                    ->where('E_CODE', $source->E_CODE)->get();
                foreach ($chefs as $c) {
                    $p = (array) $c;
                    $p['E_CODE'] = $newCode;
                    DB::table('evenement_chef')->insert($p);
                }
            }

            if ($validated['copy_vehicles'] ?? false) {
                $rows = DB::table('evenement_vehicule')
                    ->where('E_CODE', $source->E_CODE)->get();
                foreach ($rows as $r) {
                    $p = (array) $r;
                    $p['E_CODE'] = $newCode;
                    DB::table('evenement_vehicule')->insert($p);
                }

                $rows = DB::table('evenement_materiel')
                    ->where('E_CODE', $source->E_CODE)->get();
                foreach ($rows as $r) {
                    $p = (array) $r;
                    $p['E_CODE'] = $newCode;
                    DB::table('evenement_materiel')->insert($p);
                }
            }

            return $newCode;
        });

        return redirect()->route('event.show', $newCode)
            ->with('success', 'Activité dupliquée avec succès.');
    }

    // ── Shared validation ────────────────────────────────────────────────────

    private function validateEventRequest(Request $request, bool $isCreate): array
    {
        return $request->validate([
            'TE_CODE' => ['required', 'string', 'max:10'],
            'E_PARENT' => ['nullable', 'integer'],
            'E_LIBELLE' => ['required', 'string', 'max:60'],
            'E_LIEU' => ['nullable', 'string', 'max:50'],
            'E_ADDRESS' => ['nullable', 'string', 'max:255'],
            'E_LIEU_RDV' => ['nullable', 'string', 'max:150'],
            'E_HEURE_RDV' => ['nullable', 'date_format:H:i'],
            'S_ID' => ['required', 'integer'],
            'E_NB' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'E_CHEF' => ['nullable', 'integer'],
            'E_TEL' => ['nullable', 'string', 'max:15'],
            'horaires' => ['required', 'array', 'min:1'],
            'horaires.*.EH_DATE_DEBUT' => ['required', 'date'],
            'horaires.*.EH_DATE_FIN' => ['nullable', 'date', 'after_or_equal:horaires.*.EH_DATE_DEBUT'],
            'horaires.*.EH_DEBUT' => ['nullable', 'date_format:H:i'],
            'horaires.*.EH_FIN' => ['nullable', 'date_format:H:i'],
            'E_COMMENT' => ['nullable', 'string', 'max:5000'],
            'E_CONSIGNES' => ['nullable', 'string', 'max:500'],
            'E_CONTACT_LOCAL' => ['nullable', 'string', 'max:50'],
            'E_CONTACT_TEL' => ['nullable', 'string', 'max:20'],
            'E_WHATSAPP' => ['nullable', 'string', 'max:30'],
            'E_WEBEX_URL' => ['nullable', 'string', 'max:500'],
            'E_WEBEX_PIN' => ['nullable', 'string', 'max:20'],
            'E_WEBEX_START' => ['nullable', 'date_format:H:i'],
            'E_AUTOCLOSE_BEFORE' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'horaires.*.EH_DATE_FIN.after_or_equal' => __('event.validation_date_fin_after'),
        ], [
            'horaires.*.EH_DATE_DEBUT' => __('event.form_date_debut_label'),
            'horaires.*.EH_DATE_FIN' => __('event.form_date_fin_label'),
        ]);
    }

    /** Reinforcement request management page. */
    public function reinforcementRequest(string $code, ReinforcementTransmissionService $tx): View
    {
        $event = Event::findOrFail($code);
        abort_unless(auth()->user()->hasPermission(0), 403);

        $global = DB::table('demande_renfort_vehicule')
            ->where('E_CODE', $code)->where('TV_CODE', '0')
            ->first(['NB_VEHICULES', 'POINT_REGROUPEMENT', 'DEMANDE_SPECIFIQUE']);

        $vehicleTypes = DB::table('type_vehicule')
            ->orderBy('TV_USAGE')->orderBy('TV_LIBELLE')
            ->get(['TV_CODE', 'TV_LIBELLE', 'TV_USAGE']);

        $assignedVehicleCodes = DB::table('demande_renfort_vehicule')
            ->where('E_CODE', $code)->where('TV_CODE', '<>', '0')
            ->pluck('NB_VEHICULES', 'TV_CODE');

        $materialCategories = DB::table('categorie_materiel')
            ->whereNotIn('TM_USAGE', ['Habillement', 'Promo-Com', 'ALL', 'Divers'])
            ->orderBy('TM_USAGE')
            ->get(['TM_USAGE', 'CM_DESCRIPTION', 'PICTURE']);

        $assignedCategories = DB::table('demande_renfort_materiel')
            ->where('E_CODE', $code)
            ->whereIn('TYPE_MATERIEL', $materialCategories->pluck('TM_USAGE'))
            ->pluck('TYPE_MATERIEL');

        // Transmission: candidate sections (with who would receive it) + log.
        $targets = $tx->targetSections((int) $event->S_ID)->map(function ($s) use ($tx) {
            $s->recipients = $tx->recipients((int) $s->S_ID);

            return $s;
        });
        $transmissions = $tx->history((int) $event->E_CODE);

        return view('event.renfort-request',
            compact('event', 'global', 'vehicleTypes', 'assignedVehicleCodes', 'materialCategories', 'assignedCategories', 'targets', 'transmissions'));
    }

    /** Transmit the reinforcement request to other sections by email. */
    public function reinforcementTransmit(Request $request, string $code, ReinforcementTransmissionService $tx): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);

        $validated = $request->validate([
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:600'],
        ], [
            'sections.required' => __('event.renfort_tx_no_section'),
        ]);

        $sent = $tx->transmit($event, $validated['sections'], $validated['note'] ?? null, auth()->user());

        $redirect = redirect()->route('event.renfort-request', $code);
        if (empty($sent)) {
            return $redirect->with('error', __('event.renfort_tx_no_section'));
        }

        $empty = count(array_filter($sent, fn ($n) => $n === 0));
        $message = __('event.renfort_tx_sent', ['sections' => count($sent), 'recipients' => array_sum($sent)]);

        return $empty > 0
            ? $redirect->with('warning', $message.' '.__('event.renfort_tx_some_empty', ['count' => $empty]))
            : $redirect->with('success', $message);
    }

    /** Save the reinforcement request for an event. */
    public function reinforcementRequestUpdate(Request $request, string $code): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);

        $validated = $request->validate([
            'nb_vehicules' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'point_regroupement' => ['nullable', 'string', 'max:250'],
            'demande_specifique' => ['nullable', 'string', 'max:600'],
            'vehicle_types' => ['nullable', 'array'],
            'vehicle_types.*' => ['nullable', 'integer', 'min:0', 'max:999'],
            'categories' => ['nullable', 'array'],
        ]);

        DB::transaction(function () use ($validated, $code, $request) {
            DB::table('demande_renfort_vehicule')->where('E_CODE', $code)->delete();
            DB::table('demande_renfort_materiel')->where('E_CODE', $code)->delete();

            $nbVeh = (int) ($validated['nb_vehicules'] ?? 0);
            DB::table('demande_renfort_vehicule')->insert([
                'E_CODE' => $code,
                'TV_CODE' => '0',
                'NB_VEHICULES' => $nbVeh,
                'POINT_REGROUPEMENT' => $validated['point_regroupement'] ?? null,
                'DEMANDE_SPECIFIQUE' => $validated['demande_specifique'] ?? null,
            ]);

            foreach ($validated['vehicle_types'] ?? [] as $tvCode => $nb) {
                $nb = (int) $nb;
                if ($nb > 0) {
                    DB::table('demande_renfort_vehicule')->insert([
                        'E_CODE' => $code, 'TV_CODE' => $tvCode, 'NB_VEHICULES' => $nb,
                    ]);
                }
            }

            foreach ($request->input('categories', []) as $usage) {
                DB::table('demande_renfort_materiel')->insert([
                    'E_CODE' => $code, 'TYPE_MATERIEL' => $usage,
                ]);
            }
        });

        return redirect()->route('event.show', $code)->with('success', 'Demande de renfort enregistrée.');
    }

    /**
     * Photo-directory (trombinoscope) for event participants.
     */
    public function trombinoscope(string $code): View
    {
        $event = Event::findOrFail($code);

        $participants = DB::table('evenement_participation as ep')
            ->join('pompier as p', 'ep.P_ID', '=', 'p.P_ID')
            ->leftJoin('type_participation as tp', 'tp.TP_ID', '=', 'ep.TP_ID')
            ->where('ep.E_CODE', $code)
            ->where('ep.EP_ABSENT', 0)
            ->orderBy('p.P_NOM')
            ->orderBy('p.P_PRENOM')
            ->select('p.P_ID', 'p.P_NOM', 'p.P_PRENOM', 'p.P_PHOTO', 'p.P_GRADE',
                'p.P_CIVILITE', 'tp.TP_LIBELLE')
            ->get()
            ->each(function ($row): void {
                $row->avatarSrc = Personnel::avatarUrl($row->P_ID, $row->P_PHOTO, $row->P_CIVILITE);
            });

        return view('event.trombinoscope', compact('event', 'participants'));
    }

    /**
     * Printable event report — native successor to legacy `evenement_rapport.php`.
     * Assembles the identity, key figures, engaged vehicles/matériel and the
     * main-courante log into a print-optimised page (browser print → PDF).
     *
     * Gated by permission 15 (event management) or being the event's chef,
     * mirroring the legacy `check_rights(15) || is_chef_evenement` guard.
     * Intervention/victim statistics and the "centres d'accueil" section are
     * intentionally omitted — that data model (DPS) is not yet migrated.
     */
    public function report(string $code): View
    {
        $event = Event::with(['type', 'chef', 'horaires'])->findOrFail($code);

        $user = auth()->user();
        $isChef = (int) $event->E_CHEF === (int) $user->P_ID
            || DB::table('evenement_chef')
                ->where('E_CODE', $event->E_CODE)
                ->where('E_CHEF', $user->P_ID)
                ->exists();
        abort_unless($user->hasPermission(15) || $isChef, 403);

        $vehicules = DB::table('evenement_vehicule as ev')
            ->join('vehicule as v', 'ev.V_ID', '=', 'v.V_ID')
            ->where('ev.E_CODE', $code)
            ->orderBy('v.V_INDICATIF')
            ->select('v.V_ID', 'v.V_IMMATRICULATION', 'v.V_INDICATIF', 'ev.EV_KM')
            ->get();

        $materiels = DB::table('evenement_materiel as em')
            ->join('materiel as m', 'em.MA_ID', '=', 'm.MA_ID')
            ->where('em.E_CODE', $code)
            ->orderBy('m.MA_MODELE')
            ->select('m.MA_ID', 'm.MA_MODELE', 'm.MA_NUMERO_SERIE', 'em.EM_NB')
            ->get();

        $eventLog = DB::table('evenement_log as el')
            ->leftJoin('type_evenement_log as tel', 'tel.TEL_CODE', '=', 'el.TEL_CODE')
            ->leftJoin('pompier as p', 'p.P_ID', '=', 'el.EL_AUTHOR')
            ->where('el.E_CODE', $code)
            ->orderByDesc('el.EL_DEBUT')
            ->select(
                'el.EL_ID', 'el.TEL_CODE', 'el.EL_DEBUT', 'el.EL_TITLE',
                'el.EL_COMMENTAIRE', 'el.EL_IMPORTANT', 'tel.TEL_DESCRIPTION',
                'p.P_NOM', 'p.P_PRENOM'
            )
            ->get();

        $participantCount = DB::table('evenement_participation')
            ->where('E_CODE', $code)->where('EP_ABSENT', 0)->distinct()->count('P_ID');

        $figures = [
            'participants' => $participantCount,
            'vehicules' => $vehicules->count(),
            'materiels' => (int) $materiels->sum('EM_NB'),
            'messages' => $eventLog->count(),
            'interventions' => $eventLog->where('TEL_CODE', 'I')->count(),
        ];

        return view('event.report', compact('event', 'vehicules', 'materiels', 'eventLog', 'figures'));
    }

    /** Add a required position to an event (or set global count if ps_id=0). */
    public function storeRequiredPosition(Request $request, string $code): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);

        $validated = $request->validate([
            'ps_id' => ['required', 'integer', 'min:0'],
            'nb' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        DB::table('evenement_competences')->upsert(
            [['E_CODE' => $event->E_CODE, 'EH_ID' => 1, 'PS_ID' => $validated['ps_id'], 'NB' => $validated['nb']]],
            ['E_CODE', 'EH_ID', 'PS_ID'],
            ['NB']
        );

        return back()->with('success', 'Poste requis ajouté.');
    }

    /** Update the required count for a position. */
    public function updateRequiredPosition(Request $request, string $code, int $psId): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);

        $nb = (int) $request->validate(['nb' => ['required', 'integer', 'min:0', 'max:9999']])['nb'];

        if ($nb === 0) {
            DB::table('evenement_competences')
                ->where('E_CODE', $event->E_CODE)->where('EH_ID', 1)->where('PS_ID', $psId)
                ->delete();
        } else {
            DB::table('evenement_competences')
                ->where('E_CODE', $event->E_CODE)->where('EH_ID', 1)->where('PS_ID', $psId)
                ->update(['NB' => $nb]);
        }

        return back()->with('success', 'Poste requis mis à jour.');
    }

    /** Remove a required position from an event. */
    public function destroyRequiredPosition(string $code, int $psId): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);

        DB::table('evenement_competences')
            ->where('E_CODE', $event->E_CODE)->where('EH_ID', 1)->where('PS_ID', $psId)
            ->delete();

        return back()->with('success', 'Poste requis supprimé.');
    }

    // ── Event options (groupes + options + dropdown choices) ─────────────────

    public function optionGroupStore(Request $request, string $code): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);
        $data = $request->validate([
            'EOG_TITLE' => 'required|string|max:80',
            'EOG_ORDER' => 'nullable|integer|min:1|max:99',
        ]);

        DB::table('evenement_option_group')->insert([
            'E_CODE' => $event->E_CODE,
            'EOG_TITLE' => $data['EOG_TITLE'],
            'EOG_ORDER' => $data['EOG_ORDER'] ?? 1,
        ]);

        return back()->with('success', 'Groupe d\'options ajouté.');
    }

    public function optionGroupUpdate(Request $request, string $code, int $groupId): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);
        $data = $request->validate([
            'EOG_TITLE' => 'required|string|max:80',
            'EOG_ORDER' => 'nullable|integer|min:1|max:99',
        ]);

        DB::table('evenement_option_group')
            ->where('EOG_ID', $groupId)->where('E_CODE', $event->E_CODE)
            ->update([
                'EOG_TITLE' => $data['EOG_TITLE'],
                'EOG_ORDER' => $data['EOG_ORDER'] ?? 1,
            ]);

        return back()->with('success', 'Groupe mis à jour.');
    }

    public function optionGroupDestroy(string $code, int $groupId): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);

        // Detach options from the group (keep the options, just ungroup them).
        DB::table('evenement_option')
            ->where('E_CODE', $event->E_CODE)->where('EOG_ID', $groupId)
            ->update(['EOG_ID' => null]);

        DB::table('evenement_option_group')
            ->where('EOG_ID', $groupId)->where('E_CODE', $event->E_CODE)
            ->delete();

        return back()->with('success', 'Groupe supprimé.');
    }

    public function optionStore(Request $request, string $code): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);
        $data = $request->validate([
            'EO_TITLE' => 'required|string|max:80',
            'EO_TYPE' => 'required|in:checkbox,text,textnum,dropdown,date,hour',
            'EO_COMMENT' => 'nullable|string|max:255',
            'EO_ORDER' => 'nullable|integer|min:1|max:99',
            'EOG_ID' => 'nullable|integer',
        ]);

        $optionId = DB::table('evenement_option')->insertGetId([
            'E_CODE' => $event->E_CODE,
            'EO_TITLE' => $data['EO_TITLE'],
            'EO_TYPE' => $data['EO_TYPE'],
            'EO_COMMENT' => $data['EO_COMMENT'] ?? '',
            'EO_ORDER' => $data['EO_ORDER'] ?? 1,
            'EOG_ID' => $data['EOG_ID'] ?: null,
        ]);

        // Seed a default "Choisir…" entry for dropdown options.
        if ($data['EO_TYPE'] === 'dropdown') {
            DB::table('evenement_option_dropdown')->insert([
                'EO_ID' => $optionId, 'EOD_ORDER' => 0, 'EOD_TEXTE' => 'Choisir une option',
            ]);
        }

        return back()->with('success', 'Option ajoutée.');
    }

    public function optionUpdate(Request $request, string $code, int $optionId): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);
        $data = $request->validate([
            'EO_TITLE' => 'required|string|max:80',
            'EO_TYPE' => 'required|in:checkbox,text,textnum,dropdown,date,hour',
            'EO_COMMENT' => 'nullable|string|max:255',
            'EO_ORDER' => 'nullable|integer|min:1|max:99',
            'EOG_ID' => 'nullable|integer',
        ]);

        DB::table('evenement_option')
            ->where('EO_ID', $optionId)->where('E_CODE', $event->E_CODE)
            ->update([
                'EO_TITLE' => $data['EO_TITLE'],
                'EO_TYPE' => $data['EO_TYPE'],
                'EO_COMMENT' => $data['EO_COMMENT'] ?? '',
                'EO_ORDER' => $data['EO_ORDER'] ?? 1,
                'EOG_ID' => $data['EOG_ID'] ?: null,
            ]);

        return back()->with('success', 'Option mise à jour.');
    }

    public function optionDestroy(string $code, int $optionId): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);

        DB::table('evenement_option_choix')
            ->where('EO_ID', $optionId)->where('E_CODE', $event->E_CODE)
            ->delete();
        DB::table('evenement_option_dropdown')->where('EO_ID', $optionId)->delete();
        DB::table('evenement_option')
            ->where('EO_ID', $optionId)->where('E_CODE', $event->E_CODE)
            ->delete();

        return back()->with('success', 'Option supprimée.');
    }

    public function dropdownChoiceStore(Request $request, string $code, int $optionId): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);
        abort_unless(
            DB::table('evenement_option')->where('EO_ID', $optionId)->where('E_CODE', $event->E_CODE)->exists(),
            404
        );
        $data = $request->validate([
            'EOD_TEXTE' => 'required|string|max:80',
            'EOD_ORDER' => 'nullable|integer|min:1|max:99',
        ]);

        DB::table('evenement_option_dropdown')->insert([
            'EO_ID' => $optionId,
            'EOD_TEXTE' => $data['EOD_TEXTE'],
            'EOD_ORDER' => $data['EOD_ORDER'] ?? 1,
        ]);

        return back()->with('success', 'Choix ajouté.');
    }

    public function dropdownChoiceDestroy(string $code, int $optionId, int $choiceId): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission(15), 403);
        $event = Event::findOrFail($code);

        DB::table('evenement_option_choix')
            ->where('EO_ID', $optionId)->where('EOC_VALUE', $choiceId)->delete();
        DB::table('evenement_option_dropdown')
            ->where('EOD_ID', $choiceId)->where('EO_ID', $optionId)->delete();

        return back()->with('success', 'Choix supprimé.');
    }

    public function participantChoicesSave(Request $request, string $code, int $pid): RedirectResponse
    {
        $user = auth()->user();
        $event = Event::findOrFail($code);

        // Allow: perm 15, event chef, or the participant themselves.
        $isSelf = (int) $user->P_ID === $pid;
        abort_unless($user->hasPermission(15) || $isSelf, 403);

        // Verify the person is enrolled.
        abort_unless(
            DB::table('evenement_participation')
                ->where('E_CODE', $event->E_CODE)->where('P_ID', $pid)->exists(),
            403
        );

        // Clear existing choices for this participant on this event, then re-insert.
        DB::table('evenement_option_choix')
            ->where('E_CODE', $event->E_CODE)->where('P_ID', $pid)->delete();

        $options = DB::table('evenement_option')->where('E_CODE', $event->E_CODE)->get();

        foreach ($options as $opt) {
            $key = 'O'.$opt->EO_ID;
            if (! $request->has($key)) {
                continue;
            }
            $value = $request->input($key);
            if ($value === null || $value === '') {
                continue;
            }
            if ($opt->EO_TYPE === 'dropdown' && (int) $value <= 0) {
                continue;
            }

            DB::table('evenement_option_choix')->insert([
                'E_CODE' => $event->E_CODE,
                'P_ID' => $pid,
                'EO_ID' => $opt->EO_ID,
                'EOC_VALUE' => (string) $value,
            ]);
        }

        return back()->with('success', 'Choix enregistrés.');
    }

    // ── Main courante ─────────────────────────────────────────────────────────

    public function logStore(Request $request, string $code): RedirectResponse
    {
        $event = Event::findOrFail($code);

        $validated = $request->validate([
            'TEL_CODE' => ['required', 'string', 'max:10', 'exists:type_evenement_log,TEL_CODE'],
            'EL_DEBUT' => ['required', 'date_format:Y-m-d\TH:i'],
            'EL_FIN' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'EL_SLL' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'EL_TITLE' => ['nullable', 'string', 'max:255'],
            'EL_COMMENTAIRE' => ['nullable', 'string', 'max:2000'],
            'EL_IMPORTANT' => ['boolean'],
        ]);

        DB::table('evenement_log')->insert([
            'E_CODE' => $event->E_CODE,
            'TEL_CODE' => $validated['TEL_CODE'],
            'EL_DEBUT' => $validated['EL_DEBUT'],
            'EL_FIN' => $validated['EL_FIN'] ?? null,
            'EL_SLL' => $validated['EL_SLL'] ?? null,
            'EL_TITLE' => $validated['EL_TITLE'] ?? null,
            'EL_COMMENTAIRE' => $validated['EL_COMMENTAIRE'] ?? null,
            'EL_IMPORTANT' => (int) ($validated['EL_IMPORTANT'] ?? 0),
            'EL_AUTHOR' => auth()->id(),
            'EL_DATE_ADD' => now(),
        ]);

        return back()->with('success', 'Entrée de main courante ajoutée.');
    }

    public function logUpdate(Request $request, string $code, int $logId): RedirectResponse
    {
        $event = Event::findOrFail($code);

        $log = DB::table('evenement_log')->where('EL_ID', $logId)->where('E_CODE', $event->E_CODE)->firstOrFail();

        $validated = $request->validate([
            'TEL_CODE' => ['required', 'string', 'max:10', 'exists:type_evenement_log,TEL_CODE'],
            'EL_DEBUT' => ['required', 'date_format:Y-m-d\TH:i'],
            'EL_FIN' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'EL_SLL' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'EL_TITLE' => ['nullable', 'string', 'max:255'],
            'EL_COMMENTAIRE' => ['nullable', 'string', 'max:2000'],
            'EL_IMPORTANT' => ['boolean'],
        ]);

        DB::table('evenement_log')->where('EL_ID', $logId)->update([
            'TEL_CODE' => $validated['TEL_CODE'],
            'EL_DEBUT' => $validated['EL_DEBUT'],
            'EL_FIN' => $validated['EL_FIN'] ?? null,
            'EL_SLL' => $validated['EL_SLL'] ?? null,
            'EL_TITLE' => $validated['EL_TITLE'] ?? null,
            'EL_COMMENTAIRE' => $validated['EL_COMMENTAIRE'] ?? null,
            'EL_IMPORTANT' => (int) ($validated['EL_IMPORTANT'] ?? 0),
        ]);

        return back()->with('success', 'Entrée modifiée.');
    }

    public function logDestroy(string $code, int $logId): RedirectResponse
    {
        $event = Event::findOrFail($code);
        DB::table('evenement_log')->where('EL_ID', $logId)->where('E_CODE', $event->E_CODE)->delete();

        return back()->with('success', 'Entrée supprimée.');
    }
}
