<?php

namespace App\Services;

use App\Models\Personnel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    // ── Widget layout defaults ──────────────────────────────────────────────

    public const WIDGET_LABELS = [
        'welcome' => 'Mon profil',
        'duty' => 'Astreinte',
        'birthdays' => 'Anniversaires',
        'horaires' => 'Horaires à valider',
        'unpaid' => 'Activités non facturées',
        'stats-missing' => 'Bilans manquants',
        'my-activities' => 'Mes activités',
        'agenda' => 'Agenda',
        'cp' => 'Congés à valider',
        'vehicles' => 'Véhicules',
        'consumables' => 'Consommables',
        'remplacements' => 'Remplacements',
        'replacement-requests' => 'Demandes de remplacement',
        'infos' => 'Consignes & Actualités',
        'mc' => 'Main courante',
        'expenses' => 'Notes de frais',
        'events' => 'Activités de la section',
        'training' => 'Formation',
        'about' => 'À propos',
    ];

    public const WIDGET_DEFAULTS = [
        ['key' => 'welcome',              'col' => 1, 'position' => 1,  'visible' => 1],
        ['key' => 'duty',                 'col' => 1, 'position' => 2,  'visible' => 1],
        ['key' => 'birthdays',            'col' => 1, 'position' => 3,  'visible' => 1],
        ['key' => 'horaires',             'col' => 1, 'position' => 4,  'visible' => 1],
        ['key' => 'unpaid',               'col' => 1, 'position' => 5,  'visible' => 1],
        ['key' => 'stats-missing',        'col' => 1, 'position' => 6,  'visible' => 1],
        ['key' => 'my-activities',        'col' => 2, 'position' => 1,  'visible' => 1],
        ['key' => 'agenda',               'col' => 2, 'position' => 2,  'visible' => 1],
        ['key' => 'cp',                   'col' => 2, 'position' => 3,  'visible' => 1],
        ['key' => 'vehicles',             'col' => 2, 'position' => 4,  'visible' => 1],
        ['key' => 'consumables',          'col' => 2, 'position' => 5,  'visible' => 1],
        ['key' => 'remplacements',        'col' => 2, 'position' => 6,  'visible' => 1],
        ['key' => 'replacement-requests', 'col' => 2, 'position' => 7,  'visible' => 1],
        ['key' => 'infos',                'col' => 2, 'position' => 8,  'visible' => 1],
        ['key' => 'mc',                   'col' => 3, 'position' => 1,  'visible' => 1],
        ['key' => 'expenses',             'col' => 3, 'position' => 2,  'visible' => 1],
        ['key' => 'events',               'col' => 3, 'position' => 3,  'visible' => 1],
        ['key' => 'training',             'col' => 3, 'position' => 4,  'visible' => 1],
        ['key' => 'about',                'col' => 3, 'position' => 5,  'visible' => 1],
    ];

    /**
     * Returns ['columns' => [1 => [...], 2 => [...], 3 => [...]], 'hidden' => [...]]
     * Each item carries key, col, position, visible, label.
     * Columns contain ALL widgets (visible and hidden) so DOM position is preserved.
     */
    public function getWidgetLayout(User $user): array
    {
        $pid = (int) $user->P_ID;
        $saved = DB::table('ob_dashboard_layout')
            ->where('P_ID', $pid)
            ->get(['widget_key', 'col', 'position', 'visible'])
            ->keyBy('widget_key');

        $result = [];
        foreach (self::WIDGET_DEFAULTS as $default) {
            $key = $default['key'];
            if ($saved->has($key)) {
                $s = $saved[$key];
                $result[] = [
                    'key' => $key,
                    'col' => (int) $s->col,
                    'position' => (int) $s->position,
                    'visible' => (bool) $s->visible,
                    'label' => self::WIDGET_LABELS[$key],
                ];
            } else {
                $result[] = array_merge($default, ['label' => self::WIDGET_LABELS[$key]]);
            }
        }

        usort($result, fn ($a, $b) => $a['col'] <=> $b['col'] ?: $a['position'] <=> $b['position']);

        $columns = [1 => [], 2 => [], 3 => []];
        $hidden = [];
        foreach ($result as $w) {
            $columns[(int) $w['col']][] = $w;
            if (! $w['visible']) {
                $hidden[] = $w;
            }
        }

        return ['columns' => $columns, 'hidden' => $hidden];
    }

    // ── Section hierarchy ───────────────────────────────────────────────────

    /** Returns $sectionId and all descendant section IDs (BFS). */
    public function getSectionFamily(int $sectionId): array
    {
        if ($sectionId === 0) {
            return [0];
        }
        $ids = [$sectionId];
        $queue = [$sectionId];
        while (! empty($queue)) {
            $children = DB::table('section')
                ->whereIn('S_PARENT', $queue)
                ->where('S_ID', '!=', 0)
                ->pluck('S_ID')->toArray();
            $new = array_values(array_diff($children, $ids));
            $ids = array_merge($ids, $new);
            $queue = $new;
        }

        return $ids;
    }

    /** Returns $sectionId plus all ancestors up to root. */
    public function getSectionFamilyUp(int $sectionId): array
    {
        $ids = [$sectionId];
        $current = $sectionId;
        while (true) {
            $parent = DB::table('section')
                ->where('S_ID', $current)
                ->value('S_PARENT');
            if (! $parent || in_array((int) $parent, $ids, true)) {
                break;
            }
            $ids[] = (int) $parent;
            $current = (int) $parent;
        }

        return $ids;
    }

    // ── Stats bar ───────────────────────────────────────────────────────────

    public function getStats(User $user): array
    {
        $pid = (int) $user->P_ID;
        $sectionId = (int) $user->P_SECTION;

        // Personal participations this year
        $year = date('Y');
        $rows = DB::select(
            "SELECT eh.EH_DATE_DEBUT
             FROM evenement_participation ep
             JOIN evenement_horaire eh ON ep.E_CODE=eh.E_CODE AND ep.EH_ID=eh.EH_ID
             JOIN evenement e ON e.E_CODE=ep.E_CODE
             WHERE ep.P_ID=? AND eh.EH_DATE_FIN>='$year-01-01'
               AND e.TE_CODE<>'MC' AND ep.EP_ABSENT=0 AND e.E_CANCELED=0",
            [$pid]
        );
        $partiDone = count($rows);
        $partiIncoming = 0;
        $today = date('Y-m-d');
        foreach ($rows as $r) {
            if ($r->EH_DATE_DEBUT >= $today) {
                $partiIncoming++;
            }
        }

        // Section activities this month and quarter
        $month = (int) date('n');
        $qNum = (int) ceil($month / 3);
        $qDates = [
            1 => ["$year-01-01", "$year-03-31"],
            2 => ["$year-04-01", "$year-06-30"],
            3 => ["$year-07-01", "$year-09-30"],
            4 => ["$year-10-01", "$year-12-31"],
        ];
        [$qStart, $qEnd] = $qDates[$qNum];

        $actRows = DB::select(
            "SELECT DISTINCT eh.EH_DATE_DEBUT
             FROM evenement e JOIN evenement_horaire eh ON e.E_CODE=eh.E_CODE
             WHERE e.TE_CODE<>'MC' AND e.E_CANCELED=0
               AND eh.EH_DATE_DEBUT BETWEEN ? AND ? AND e.S_ID=?",
            [$qStart, $qEnd, $sectionId]
        );
        $actMonth = 0;
        $actQuarter = count($actRows);
        $currentMonth = date('m');
        foreach ($actRows as $r) {
            if (substr($r->EH_DATE_DEBUT, 5, 2) === $currentMonth) {
                $actMonth++;
            }
        }

        // New members this month and quarter
        $family = $this->getSectionFamily($sectionId);
        $newRows = DB::table('pompier')
            ->where('P_STATUT', '<>', 'EXT')
            ->where('P_OLD_MEMBER', 0)
            ->whereBetween('P_DATE_ENGAGEMENT', [$qStart, $qEnd])
            ->whereIn('P_SECTION', $family)
            ->get(['P_DATE_ENGAGEMENT']);
        $newMonth = 0;
        $newQuarter = count($newRows);
        foreach ($newRows as $r) {
            if ($r->P_DATE_ENGAGEMENT && substr($r->P_DATE_ENGAGEMENT, 5, 2) === $currentMonth) {
                $newMonth++;
            }
        }

        // Total alerts count (run sub-queries cheaply)
        $alerts = $this->countAlerts($user);

        // Section name
        $section = DB::table('section')->where('S_ID', $sectionId)->first();
        $sectionName = $section ? $section->S_DESCRIPTION : '';

        return compact(
            'partiDone', 'partiIncoming',
            'actMonth', 'actQuarter',
            'newMonth', 'newQuarter',
            'alerts', 'sectionName', 'year', 'pid'
        );
    }

    private function countAlerts(User $user): int
    {
        $count = 0;
        $pid = (int) $user->P_ID;

        // Expiring qualifications
        $count += DB::table('qualification as q')
            ->join('poste as p', 'q.PS_ID', '=', 'p.PS_ID')
            ->where('q.P_ID', $pid)
            ->whereRaw('DATEDIFF(q.Q_EXPIRATION,NOW()) < p.DAYS_WARNING')
            ->whereRaw('DATEDIFF(q.Q_EXPIRATION,NOW()) >= 0')
            ->count();

        // Vehicles
        if ($user->hasPermission(42)) {
            $sectionId = (int) $user->P_SECTION;
            $family = $this->getSectionFamily($sectionId);
            $count += DB::table('vehicule as v')
                ->join('vehicule_position as vp', 'vp.VP_ID', '=', 'v.VP_ID')
                ->whereIn('v.S_ID', $family)
                ->where('vp.VP_OPERATIONNEL', '<', 2)
                ->where('vp.VP_OPERATIONNEL', '>=', 0)
                ->count();
            $count += DB::table('vehicule as v')
                ->join('vehicule_position as vp', 'vp.VP_ID', '=', 'v.VP_ID')
                ->whereIn('v.S_ID', $family)
                ->whereRaw("DATEDIFF(v.V_ASS_DATE,'".date('Y-m-d')."') <= 30")
                ->where('vp.VP_OPERATIONNEL', '>=', 0)
                ->count();
        }

        // Consumables
        if ($user->hasPermission(71)) {
            $sectionId = (int) $user->P_SECTION;
            $family = $this->getSectionFamily($sectionId);
            $count += DB::table('consommable')
                ->whereIn('S_ID', $family)
                ->whereRaw("DATEDIFF(C_DATE_PEREMPTION,'".date('Y-m-d')."') <= 30")
                ->count();
            $count += DB::table('consommable')
                ->whereIn('S_ID', $family)
                ->whereRaw('C_NOMBRE < C_MINIMUM')
                ->count();
        }

        return $count;
    }

    // ── Password expiry ─────────────────────────────────────────────────────

    public function getPasswordExpiry(User $user): ?array
    {
        $expiry = $user->P_MDP_EXPIRY;
        if (! $expiry) {
            return null;
        }
        $days = (int) DB::selectOne(
            'SELECT DATEDIFF(?,NOW()) AS d',
            [$expiry]
        )->d;
        if ($days > 7) {
            return null;
        }

        return [
            'days' => $days,
            'expiry' => $expiry instanceof Carbon
                ? $expiry->format('d-m-Y')
                : date('d-m-Y', strtotime((string) $expiry)),
            'expired' => $days <= 0,
        ];
    }

    // ── Competence warnings ─────────────────────────────────────────────────

    public function getCompetenceWarnings(User $user): array
    {
        return DB::table('qualification as q')
            ->join('poste as p', 'q.PS_ID', '=', 'p.PS_ID')
            ->where('q.P_ID', (int) $user->P_ID)
            ->whereRaw('DATEDIFF(q.Q_EXPIRATION,NOW()) < p.DAYS_WARNING')
            ->whereRaw('DATEDIFF(q.Q_EXPIRATION,NOW()) >= 0')
            ->select(
                'p.TYPE',
                DB::raw("DATE_FORMAT(q.Q_EXPIRATION,'%d-%m-%Y') as Q_EXPIRATION"),
                DB::raw('DATEDIFF(q.Q_EXPIRATION,NOW()) as NB')
            )
            ->get()->toArray();
    }

    // ── Welcome widget ──────────────────────────────────────────────────────

    public function getWelcome(User $user): array
    {
        $section = DB::table('section')->where('S_ID', $user->P_SECTION)->first();

        $missingFields = [];
        if ($user->P_STATUT !== 'EXT') {
            $checks = [
                'P_PHOTO' => 'Photo',
                'P_PRENOM2' => 'Deuxième prénom',
                'P_PHONE' => 'Téléphone',
                'P_EMAIL' => 'Adresse mail',
                'P_ADDRESS' => 'Adresse',
                'P_CITY' => 'Ville',
                'P_ZIP_CODE' => 'Code postal',
                'P_BIRTHDATE' => 'Date de naissance',
                'P_BIRTHPLACE' => 'Lieu de naissance',
                'P_BIRTH_DEP' => 'Département de naissance',
                'P_RELATION_NOM' => 'Nom – contact urgence',
                'P_RELATION_PRENOM' => 'Prénom – contact urgence',
                'P_RELATION_PHONE' => 'Téléphone – contact urgence',
            ];
            foreach ($checks as $field => $label) {
                $val = $user->$field ?? '';
                if ($val === '' || $val === '0' || $val === null) {
                    $missingFields[] = $label;
                }
            }
        }

        return [
            'user' => $user,
            'section' => $section,
            'avatarSrc' => $user->getAvatarUrl(),
            'avatarFallback' => asset('images/autre.png'),
            'missingFields' => $missingFields,
        ];
    }

    // ── Upcoming events ─────────────────────────────────────────────────────

    public function getEvents(User $user, int $limit = 20): array
    {
        $sectionId = (int) $user->P_SECTION;
        $section = DB::table('section')->where('S_ID', $sectionId)->first();

        $events = DB::table('evenement as E')
            ->join('type_evenement as TE', 'E.TE_CODE', '=', 'TE.TE_CODE')
            ->join('section as S', 'E.S_ID', '=', 'S.S_ID')
            ->join('evenement_horaire as EH', 'E.E_CODE', '=', 'EH.E_CODE')
            ->where('E.E_CANCELED', 0)
            ->where('E.E_VISIBLE_INSIDE', 1)
            ->where('TE.TE_CODE', '<>', 'MC')
            ->whereRaw('EH.EH_DATE_FIN >= CURDATE()')
            ->where('E.S_ID', $sectionId)
            ->where('E.E_EQUIPE', 0)
            ->orderBy('EH.EH_DATE_DEBUT')
            ->orderBy('EH.EH_DEBUT')
            ->limit($limit)
            ->select(
                'E.E_CODE', 'EH.EH_ID', 'E.TE_CODE', 'TE.TE_ICON', 'TE.TE_LIBELLE',
                'E.E_LIEU', 'E.E_LIBELLE', 'E.E_NB', 'E.E_CLOSED', 'E.E_CANCELED',
                'S.S_DESCRIPTION',
                DB::raw("DATE_FORMAT(EH.EH_DATE_DEBUT,'%d-%m-%Y') AS FORMDATE1"),
                DB::raw("DATE_FORMAT(EH.EH_DEBUT,'%H:%i') AS DEBUTDATE"),
                DB::raw("DATE_FORMAT(EH.EH_FIN,'%H:%i') AS FINDATE")
            )
            ->get()->toArray();

        return [
            'events' => $events,
            'sectionId' => $sectionId,
            'sectionName' => $section ? $section->S_DESCRIPTION : '',
        ];
    }

    // ── On-duty personnel ───────────────────────────────────────────────────

    public function getDuty(User $user): array
    {
        $sectionId = (int) $user->P_SECTION;
        $familyUp = $this->getSectionFamilyUp($sectionId);

        // depth map: root ancestor has lowest value, current section has highest
        $depthMap = array_flip(array_reverse($familyUp));

        $rows = DB::table('pompier as p')
            ->join('section as se', 'se.S_ID', '=', 'p.P_SECTION')
            ->join('ob_user_assignment as a', function ($j) {
                $j->on('a.person_id', '=', 'p.P_ID')->on('a.section_id', '=', 'p.P_SECTION');
            })
            ->join('groupe as g', 'g.GP_ID', '=', 'a.group_id')
            ->where('g.TR_WIDGET', 1)
            ->whereIn('p.P_SECTION', $familyUp)
            ->select(
                'p.P_ID', 'p.P_PRENOM', 'p.P_NOM', 'p.P_PHOTO', 'p.P_CIVILITE',
                'se.S_ID', 'se.S_DESCRIPTION', 'se.S_PHONE2',
                'p.P_PHONE', 'g.GP_ID', 'g.GP_DESCRIPTION'
            )
            ->get()->toArray();

        usort($rows, fn ($a, $b) => ($depthMap[$a->S_ID] ?? 999) <=> ($depthMap[$b->S_ID] ?? 999));

        foreach ($rows as &$row) {
            $row->avatarSrc = Personnel::avatarUrl($row->P_ID, $row->P_PHOTO, $row->P_CIVILITE);
        }
        unset($row);

        return ['duty' => $rows];
    }

    // ── Operational notices / news ──────────────────────────────────────────

    public function getInfos(User $user): array
    {
        $sectionId = (int) $user->P_SECTION;
        $today = date('Y-m-d');

        $consignes = [];
        $actualites = [];

        // Operational notices (gardes mode)
        $consignes = DB::table('message as m')
            ->join('type_message as tm', 'm.TM_ID', '=', 'tm.TM_ID')
            ->where('m.M_TYPE', 'consigne')
            ->whereIn('m.S_ID', $this->getSectionFamilyUp($sectionId))
            ->whereRaw("(DATEDIFF('$today', m.M_DATE) <= M_DUREE OR M_DUREE = 0)")
            ->orderByDesc('m.M_DATE')
            ->select('m.*', 'tm.TM_LIBELLE', 'tm.TM_COLOR', 'tm.TM_ICON',
                DB::raw("DATE_FORMAT(m.M_DATE,'%d-%m-%Y') as FORMDATE"))
            ->get()->toArray();

        // General news (permission 44)
        if ($user->hasPermission(44)) {
            $actualites = DB::table('message as m')
                ->join('type_message as tm', 'm.TM_ID', '=', 'tm.TM_ID')
                ->where('m.M_TYPE', 'amicale')
                ->whereIn('m.S_ID', $this->getSectionFamilyUp($sectionId))
                ->whereRaw("(DATEDIFF('$today', m.M_DATE) <= M_DUREE OR M_DUREE = 0)")
                ->orderByDesc('m.M_DATE')
                ->select('m.*', 'tm.TM_LIBELLE', 'tm.TM_COLOR',
                    DB::raw("DATE_FORMAT(m.M_DATE,'%d-%m-%Y') as FORMDATE"))
                ->get()->toArray();
        }

        return compact('consignes', 'actualites');
    }

    // ── Birthdays ───────────────────────────────────────────────────────────

    public function getBirthdays(User $user): array
    {
        if (! $user->hasPermission(40)) {
            return ['days' => []];
        }

        $sectionId = (int) $user->P_SECTION;
        $family = $this->getSectionFamily($sectionId);

        $days = [];
        for ($offset = 0; $offset <= 2; $offset++) {
            $ts = strtotime("+$offset days");
            $mmdd = date('m-d', $ts);
            $rows = DB::table('pompier')
                ->where('P_OLD_MEMBER', 0)
                ->where('P_STATUT', '<>', 'EXT')
                ->whereIn('P_SECTION', $family)
                ->whereRaw("DATE_FORMAT(P_BIRTHDATE,'%m-%d') = ?", [$mmdd])
                ->orderBy('P_NOM')
                ->select('P_ID', 'P_PRENOM', 'P_NOM', 'P_PHOTO', 'P_CIVILITE')
                ->get()->toArray();

            foreach ($rows as &$r) {
                $r->avatarSrc = Personnel::avatarUrl($r->P_ID, $r->P_PHOTO, $r->P_CIVILITE);
            }
            unset($r);

            $label = match ($offset) {
                0 => "Aujourd'hui",
                1 => 'Demain',
                2 => 'Après-demain',
            };
            $color = match ($offset) {
                0 => 'blue', 1 => 'orange', default => 'violet'
            };
            $displayDate = date('d', $ts).' '.$this->frMonth((int) date('m', $ts));
            $days[] = compact('rows', 'label', 'color', 'displayDate');
        }

        return ['days' => $days];
    }

    private function frMonth(int $m): string
    {
        return ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
            'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'][$m];
    }

    // ── Vehicle alerts ──────────────────────────────────────────────────────

    public function getVehiclesAlerts(User $user): array
    {
        if (! $user->hasPermission(42)) {
            return ['items' => []];
        }

        $sectionId = (int) $user->P_SECTION;
        $family = $this->getSectionFamily($sectionId);
        $today = date('Y-m-d');
        $items = [];

        $base = DB::table('vehicule as v')->join('vehicule_position as vp', 'vp.VP_ID', '=', 'v.VP_ID')
            ->whereIn('v.S_ID', $family)->where('vp.VP_OPERATIONNEL', '>=', 0);

        // TODO: Migrate code — vehicle.index has no filter/sort params yet; link to index for now
        $vehiculeUrl = route('vehicle.index');

        // Unavailable
        $nb = (clone $base)->where('vp.VP_OPERATIONNEL', '<', 2)->count();
        if ($nb) {
            $items[] = ['label' => 'Véhicules indisponibles', 'sub' => '', 'count' => $nb, 'level' => 'danger', 'url' => $vehiculeUrl];
        }

        // Expired insurance
        $nb = (clone $base)->whereRaw('v.V_ASS_DATE < NOW()')->count();
        if ($nb) {
            $items[] = ['label' => 'Assurances', 'sub' => 'Périmées', 'count' => $nb, 'level' => 'danger', 'url' => $vehiculeUrl];
        }

        // Expiring insurance ≤30 days
        $nb = (clone $base)->whereRaw("DATEDIFF(v.V_ASS_DATE,'$today') BETWEEN 1 AND 30")->count();
        if ($nb) {
            $items[] = ['label' => 'Assurances', 'sub' => 'Bientôt périmées', 'count' => $nb, 'level' => 'warning', 'url' => $vehiculeUrl];
        }

        // Expired CT
        $nb = (clone $base)->whereRaw("DATEDIFF(v.V_CT_DATE,'$today') <= 0")->count();
        if ($nb) {
            $items[] = ['label' => 'Contrôles techniques', 'sub' => 'Périmés', 'count' => $nb, 'level' => 'danger', 'url' => $vehiculeUrl];
        }

        // Expiring CT ≤60 days
        $nb = (clone $base)->whereRaw("DATEDIFF(v.V_CT_DATE,'$today') BETWEEN 1 AND 60")->count();
        if ($nb) {
            $items[] = ['label' => 'Contrôles techniques', 'sub' => 'Bientôt périmés', 'count' => $nb, 'level' => 'warning', 'url' => $vehiculeUrl];
        }

        // Expired access titles
        $nb = (clone $base)->whereRaw("DATEDIFF(v.V_TITRE_DATE,'$today') <= 0")->count();
        if ($nb) {
            $items[] = ['label' => "Titres d'accès", 'sub' => 'Périmés', 'count' => $nb, 'level' => 'danger', 'url' => $vehiculeUrl];
        }

        // Expiring access titles ≤60 days
        $nb = (clone $base)->whereRaw("DATEDIFF(v.V_TITRE_DATE,'$today') BETWEEN 1 AND 60")->count();
        if ($nb) {
            $items[] = ['label' => "Titres d'accès", 'sub' => 'Bientôt périmés', 'count' => $nb, 'level' => 'warning', 'url' => $vehiculeUrl];
        }

        // Revisions due
        $nb = (clone $base)->whereRaw("DATEDIFF(v.V_REV_DATE,'$today') <= 0")->count();
        if ($nb) {
            $items[] = ['label' => 'Révisions', 'sub' => 'À faire', 'count' => $nb, 'level' => 'warning', 'url' => $vehiculeUrl];
        }

        return ['items' => $items];
    }

    // ── Consumable alerts ───────────────────────────────────────────────────

    public function getConsumablesAlerts(User $user): array
    {
        if (! $user->hasPermission(71)) {
            return ['items' => []];
        }

        $sectionId = (int) $user->P_SECTION;
        $family = $this->getSectionFamily($sectionId);
        $today = date('Y-m-d');
        $items = [];

        $base = DB::table('consommable')->whereIn('S_ID', $family);

        $nb = (clone $base)->whereRaw("DATEDIFF(C_DATE_PEREMPTION,'$today') BETWEEN 1 AND 30")->count();
        if ($nb) {
            $items[] = ['label' => 'Consommables', 'sub' => 'Bientôt périmés', 'count' => $nb, 'level' => 'warning'];
        }

        $nb = (clone $base)->whereRaw("DATEDIFF(C_DATE_PEREMPTION,'$today') <= 0")->count();
        if ($nb) {
            $items[] = ['label' => 'Consommables', 'sub' => 'Périmés', 'count' => $nb, 'level' => 'danger'];
        }

        $nb = (clone $base)->whereRaw('C_NOMBRE < C_MINIMUM')->count();
        if ($nb) {
            $items[] = ['label' => 'Stock minimum', 'sub' => 'En dessous du seuil', 'count' => $nb, 'level' => 'warning'];
        }

        return ['items' => $items];
    }

    // ── Leave / CP alerts ───────────────────────────────────────────────────

    public function getCpAlerts(User $user): array
    {
        if (! $user->hasPermission(13)) {
            return ['count' => 0, 'items' => []];
        }

        $sectionId = (int) $user->P_SECTION;
        $family = $this->getSectionFamily($sectionId);

        $rows = DB::table('pompier as p')
            ->join('indisponibilite as i', 'p.P_ID', '=', 'i.P_ID')
            ->join('type_indisponibilite as ti', 'i.TI_CODE', '=', 'ti.TI_CODE')
            ->join('indisponibilite_status as ist', 'i.I_STATUS', '=', 'ist.I_STATUS')
            ->where('p.P_STATUT', 'IN', ['SAL', 'SPP', 'FONC'])
            ->whereIn('ti.TI_CODE', ['CP', 'RTT'])
            ->where('p.P_ID', '<>', $user->P_ID)
            ->where('ist.I_STATUS', 'ATT')
            ->whereRaw('i.I_FIN >= NOW()')
            ->whereIn('p.P_SECTION', $family)
            ->select(
                'p.P_ID', 'p.P_NOM', 'p.P_PRENOM',
                DB::raw("DATE_FORMAT(i.I_DEBUT,'%d-%m-%Y') AS I_DEBUT"),
                DB::raw("DATE_FORMAT(i.I_FIN,'%d-%m-%Y') AS I_FIN"),
                'ti.TI_CODE', 'ti.TI_LIBELLE'
            )
            ->orderBy('i.I_DEBUT')
            ->get()->toArray();

        return ['count' => count($rows), 'items' => $rows];
    }

    // ── Work hours to validate ──────────────────────────────────────────────

    public function getHorairesAlerts(User $user): array
    {
        if (! $user->hasPermission(13)) {
            return ['rows' => []];
        }

        $sectionId = (int) $user->P_SECTION;
        $family = $this->getSectionFamily($sectionId);
        $today = date('Y-m-d');

        $rows = DB::select("
            SELECT p.P_ID, p.P_NOM, p.P_PRENOM, s.S_CODE AS s_code, hv.ANNEE, hv.SEMAINE,
                   FLOOR(SUM(h.H_DUREE_MINUTES) / 60) AS heures,
                   MOD(SUM(h.H_DUREE_MINUTES), 60) AS minutes
            FROM pompier p
            JOIN section s ON p.P_SECTION = s.S_ID
            JOIN horaires h ON p.P_ID = h.P_ID
            JOIN horaires_validation hv ON hv.P_ID = h.P_ID
                AND hv.HS_CODE = 'ATTV'
                AND (
                    (YEAR(h.H_DATE) = hv.ANNEE AND WEEK(h.H_DATE,1) = hv.SEMAINE)
                    OR (WEEK(h.H_DATE,1) = 53 AND hv.SEMAINE = 1 AND YEAR(h.H_DATE)+1 = hv.ANNEE)
                )
            WHERE s.S_ID IN (".implode(',', $family).")
              AND DATE_FORMAT(h.H_DATE,'%Y-%m-%d') < '$today'
              AND DATEDIFF('$today', DATE_FORMAT(h.H_DATE,'%Y-%m-%d')) < 100
            GROUP BY p.P_ID, p.P_NOM, p.P_PRENOM, s.S_CODE, hv.ANNEE, hv.SEMAINE
            ORDER BY p.P_NOM, p.P_PRENOM, hv.ANNEE DESC, hv.SEMAINE DESC
        ");

        return ['rows' => $rows];
    }

    // ── Guard replacements ──────────────────────────────────────────────────

    public function getReplacementsAlerts(User $user): array
    {
        $pid = (int) $user->P_ID;
        $canApprove = $user->hasPermission(6) || $user->hasPermission(15);
        $sectionId = (int) $user->P_SECTION;
        $family = $this->getSectionFamily($sectionId);

        $base = DB::table('remplacement as r')
            ->join('evenement_horaire as eh', function ($j) {
                $j->on('eh.E_CODE', '=', 'r.E_CODE')->where('eh.EH_ID', 1);
            })
            ->join('evenement as e', 'e.E_CODE', '=', 'eh.E_CODE')
            ->where('r.APPROVED', 0)
            ->where('r.REJECTED', 0)
            ->whereRaw('eh.EH_DATE_FIN >= NOW()');

        if ($canApprove) {
            $pending = (clone $base)->whereIn('e.S_ID', $family)
                ->select(DB::raw('COUNT(1) as NB'),
                    DB::raw("DATE_FORMAT(MIN(eh.EH_DATE_DEBUT),'%d-%m-%Y') AS DEBUT"),
                    DB::raw("DATE_FORMAT(MAX(eh.EH_DATE_FIN),'%d-%m-%Y') AS FIN"))
                ->first();
            $nb = (int) ($pending->NB ?? 0);
            $type = 'À approuver';
        } else {
            $pending = (clone $base)->where('r.SUBSTITUTE', $pid)->where('r.ACCEPTED', 0)
                ->select(DB::raw('COUNT(1) as NB'),
                    DB::raw("DATE_FORMAT(MIN(eh.EH_DATE_DEBUT),'%d-%m-%Y') AS DEBUT"),
                    DB::raw("DATE_FORMAT(MAX(eh.EH_DATE_FIN),'%d-%m-%Y') AS FIN"))
                ->first();
            $nb = (int) ($pending->NB ?? 0);
            $type = 'À accepter';
        }

        return ['count' => $nb, 'type' => $type, 'canApprove' => $canApprove];
    }

    // ── Training hours ──────────────────────────────────────────────────────

    public function getTraining(User $user): array
    {
        $pid = (int) $user->P_ID;
        $year = date('Y');

        $asTrainee = DB::table('evenement as e')
            ->leftJoin('poste as ps', 'e.PS_ID', '=', 'ps.PS_ID')
            ->join('evenement_participation as ep', 'e.E_CODE', '=', 'ep.E_CODE')
            ->join('evenement_horaire as eh', function ($j) {
                $j->on('ep.E_CODE', '=', 'eh.E_CODE')->on('ep.EH_ID', '=', 'eh.EH_ID');
            })
            ->where('ep.P_ID', $pid)
            ->where('ep.EP_ABSENT', 0)
            ->where('ep.TP_ID', 0)
            ->where('e.E_CANCELED', 0)
            ->where('e.TE_CODE', 'FOR')
            ->where('e.E_VISIBLE_INSIDE', 1)
            ->whereRaw("eh.EH_DATE_FIN >= '$year-01-01'")
            ->whereRaw('eh.EH_DATE_DEBUT <= CURDATE()')
            ->groupBy('ps.PH_CODE')
            ->select('ps.PH_CODE', DB::raw('SUM(ep.EP_DUREE) as TOTAL'))
            ->get()->toArray();

        $asTrainer = DB::table('evenement as e')
            ->leftJoin('poste as ps', 'e.PS_ID', '=', 'ps.PS_ID')
            ->join('evenement_participation as ep', 'e.E_CODE', '=', 'ep.E_CODE')
            ->join('evenement_horaire as eh', function ($j) {
                $j->on('ep.E_CODE', '=', 'eh.E_CODE')->on('ep.EH_ID', '=', 'eh.EH_ID');
            })
            ->where('ep.P_ID', $pid)
            ->where('ep.EP_ABSENT', 0)
            ->where('ep.TP_ID', '>', 0)
            ->where('e.E_CANCELED', 0)
            ->where('e.TE_CODE', 'FOR')
            ->where('e.E_VISIBLE_INSIDE', 1)
            ->whereRaw("eh.EH_DATE_FIN >= '$year-01-01'")
            ->whereRaw('eh.EH_DATE_DEBUT <= CURDATE()')
            ->groupBy('ps.PH_CODE')
            ->select('ps.PH_CODE', DB::raw('SUM(ep.EP_DUREE) as TOTAL'))
            ->get()->toArray();

        $format = fn ($mins) => sprintf('%02d:%02d', floor($mins * 60 / 60), ($mins * 60) % 60);

        return ['asTrainee' => $asTrainee, 'asTrainer' => $asTrainer, 'year' => $year];
    }

    // ── Main courante (MC) participations ──────────────────────────────────

    public function getMcEvents(User $user): array
    {
        $pid = (int) $user->P_ID;

        // Upcoming MC events the user is signed up for
        $rows = DB::table('evenement as e')
            ->join('type_evenement as te', 'e.TE_CODE', '=', 'te.TE_CODE')
            ->join('evenement_horaire as eh', 'e.E_CODE', '=', 'eh.E_CODE')
            ->join('evenement_participation as ep', function ($j) use ($pid) {
                $j->on('ep.E_CODE', '=', 'e.E_CODE')
                    ->on('ep.EH_ID', '=', 'eh.EH_ID')
                    ->where('ep.P_ID', $pid);
            })
            ->where('e.TE_CODE', 'MC')
            ->where('e.E_CANCELED', 0)
            ->where('ep.EP_ABSENT', 0)
            ->whereRaw('eh.EH_DATE_FIN >= NOW()')
            ->orderBy('eh.EH_DATE_DEBUT')
            ->select(
                'e.E_CODE', 'e.E_LIBELLE',
                DB::raw("DATE_FORMAT(eh.EH_DATE_DEBUT,'%d-%m-%Y') AS FORMDATE"),
                DB::raw("DATE_FORMAT(eh.EH_DEBUT,'%H:%i') AS DEBUTDATE"),
                DB::raw("DATE_FORMAT(eh.EH_FIN,'%H:%i') AS FINDATE")
            )
            ->get()->toArray();

        return ['events' => $rows];
    }

    // ── Ma section (WhatsApp + birthdays wrapper) ───────────────────────────

    public function getSectionLinks(User $user): array
    {
        $sectionId = (int) $user->P_SECTION;
        $links = [];

        $section = DB::table('section as s')
            ->leftJoin('section as p', 'p.S_ID', '=', 's.S_PARENT')
            ->where('s.S_ID', $sectionId)
            ->select(
                's.S_ID', 's.S_CODE', 's.S_DESCRIPTION', 's.S_WHATSAPP', 's.S_PARENT',
                'p.S_CODE as P_CODE', 'p.S_DESCRIPTION as P_DESCRIPTION', 'p.S_WHATSAPP as P_WHATSAPP'
            )
            ->first();

        if ($section) {
            if (! empty($section->P_WHATSAPP)) {
                $label = $section->P_CODE;
                if ($section->P_DESCRIPTION && $section->P_DESCRIPTION !== $section->P_CODE) {
                    $label .= ' – '.$section->P_DESCRIPTION;
                }
                $links[] = ['label' => $label, 'whatsapp' => $section->P_WHATSAPP, 'sectionId' => (int) $section->S_PARENT];
            }
            if (! empty($section->S_WHATSAPP)) {
                $label = $section->S_CODE;
                if ($section->S_DESCRIPTION && $section->S_DESCRIPTION !== $section->S_CODE) {
                    $label .= ' – '.$section->S_DESCRIPTION;
                }
                $links[] = ['label' => $label, 'whatsapp' => $section->S_WHATSAPP, 'sectionId' => $sectionId];
            }
        }

        $whatsappBase = config('brigade.whatsapp_url', 'https://chat.whatsapp.com');

        return ['links' => $links, 'whatsappBase' => $whatsappBase];
    }

    // ── My personal upcoming events ────────────────────────────────────────

    public function getMyActivities(User $user): array
    {
        $pid = (int) $user->P_ID;

        $events = DB::table('evenement as e')
            ->join('type_evenement as te', 'e.TE_CODE', '=', 'te.TE_CODE')
            ->join('evenement_participation as ep', function ($j) use ($pid) {
                $j->on('ep.E_CODE', '=', 'e.E_CODE')->where('ep.P_ID', $pid);
            })
            ->join('evenement_horaire as eh', function ($j) {
                $j->on('ep.E_CODE', '=', 'eh.E_CODE')->on('ep.EH_ID', '=', 'eh.EH_ID');
            })
            ->where('e.E_CANCELED', 0)
            ->where('ep.EP_ABSENT', 0)
            ->where('e.TE_CODE', '<>', 'MC')
            ->whereRaw('eh.EH_DATE_FIN >= CURDATE()')
            ->orderBy('eh.EH_DATE_DEBUT')
            ->select(
                'e.E_CODE', 'e.E_LIBELLE', 'e.E_LIEU', 'e.E_CLOSED',
                'te.TE_LIBELLE',
                DB::raw("DATE_FORMAT(eh.EH_DATE_DEBUT,'%d-%m-%Y') AS FORMDATE"),
                DB::raw("DATE_FORMAT(eh.EH_DEBUT,'%H:%i') AS DEBUTDATE"),
                DB::raw("DATE_FORMAT(eh.EH_FIN,'%H:%i') AS FINDATE"),
                'ep.EH_ID', 'ep.EP_ASTREINTE'
            )
            ->get()->toArray();

        return ['events' => $events];
    }

    // ── Unpaid / un-invoiced activities ────────────────────────────────────

    public function getUnpaidActivities(User $user): array
    {
        // Requires billing permission (55) and evenement_facturation table
        if (! $user->hasPermission(55)) {
            return ['rows' => []];
        }

        try {
            $sectionId = (int) $user->P_SECTION;
            $family = $this->getSectionFamily($sectionId);

            $rows = DB::table('evenement as e')
                ->join('evenement_facturation as ef', 'ef.e_id', '=', 'e.E_CODE')
                ->join('evenement_horaire as eh', function ($j) {
                    $j->on('eh.e_code', '=', 'e.E_CODE')->where('eh.EH_ID', 1);
                })
                ->whereIn('e.S_ID', $family)
                ->where('e.E_CANCELED', 0)
                ->where('e.TE_CODE', '<>', 'MC')
                ->whereNull('ef.paiement_date')
                ->whereRaw('(ef.devis_montant > 0 OR ef.facture_montant > 0)')
                ->whereRaw('eh.EH_DATE_FIN < NOW()')
                ->orderBy('eh.EH_DATE_DEBUT', 'desc')
                ->select(
                    'e.E_CODE', 'e.E_LIBELLE', 'e.TE_CODE',
                    'ef.facture_montant', 'ef.devis_montant',
                    'ef.facture_date', 'ef.relance_date',
                    DB::raw("DATE_FORMAT(eh.EH_DATE_DEBUT,'%d-%m-%Y') AS FORMDATE"),
                    DB::raw('DATEDIFF(NOW(), eh.EH_DATE_FIN) AS TERMINE_DEPUIS')
                )
                ->get()->toArray();

            return ['rows' => $rows];
        } catch (\Exception $e) {
            return ['rows' => []];
        }
    }

    // ── Events missing statistics bilan ────────────────────────────────────

    public function getMissingStats(User $user): array
    {
        try {
            $sectionId = (int) $user->P_SECTION;
            $family = $this->getSectionFamily($sectionId);

            $rows = DB::table('evenement as e')
                ->join('type_evenement as te', 'e.TE_CODE', '=', 'te.TE_CODE')
                ->join('evenement_horaire as eh', function ($j) {
                    $j->on('eh.e_code', '=', 'e.E_CODE')->where('eh.EH_ID', 1);
                })
                ->whereIn('e.S_ID', $family)
                ->where('e.E_CANCELED', 0)
                ->where('e.TE_CODE', '<>', 'MC')
                ->where('te.TE_MAIN_COURANTE', 1)
                ->whereRaw('eh.EH_DATE_FIN <= NOW()')
                ->whereRaw('DATEDIFF(NOW(), eh.EH_DATE_FIN) < 30')
                ->whereExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('type_bilan as tb')->whereColumn('tb.TE_CODE', 'e.TE_CODE'))
                ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                    ->from('bilan_evenement as be')->whereColumn('be.E_CODE', 'e.E_CODE'))
                ->whereNull('e.E_PARENT')
                ->orderBy('eh.EH_DATE_FIN', 'desc')
                ->select(
                    'e.E_CODE', 'e.E_LIBELLE', 'e.E_LIEU', 'te.TE_LIBELLE',
                    DB::raw("DATE_FORMAT(eh.EH_DATE_FIN,'%d-%m-%Y') AS FORMDATE"),
                    DB::raw('DATEDIFF(NOW(), eh.EH_DATE_FIN) AS TERMINE_DEPUIS')
                )
                ->get()->toArray();

            return ['rows' => $rows];
        } catch (\Exception $e) {
            return ['rows' => []];
        }
    }

    // ── Expense reports (notes de frais) ────────────────────────────────────

    public function getExpenses(User $user): array
    {
        $isManager = $user->hasPermission(73) || $user->hasPermission(74) || $user->hasPermission(75);

        try {
            if ($isManager) {
                $sectionId = (int) $user->P_SECTION;
                $family = $this->getSectionFamily($sectionId);

                $rows = DB::table('note_de_frais as n')
                    ->join('pompier as p', 'p.P_ID', '=', 'n.P_ID')
                    ->whereIn('n.S_ID', $family)
                    ->whereIn('n.FS_CODE', ['ATTV', 'VAL', 'VAL1', 'VAL2'])
                    ->orderByDesc('n.NF_CREATE_DATE')
                    ->limit(10)
                    ->select(
                        'n.NF_ID', 'n.NF_CREATE_DATE', 'n.FS_CODE', 'n.TOTAL_AMOUNT',
                        'p.P_NOM', 'p.P_PRENOM'
                    )
                    ->get()->toArray();
            } else {
                $rows = DB::table('note_de_frais as n')
                    ->where('n.P_ID', (int) $user->P_ID)
                    ->whereIn('n.FS_CODE', ['ATTV', 'VAL', 'VAL1', 'VAL2'])
                    ->orderByDesc('n.NF_CREATE_DATE')
                    ->limit(5)
                    ->select('n.NF_ID', 'n.NF_CREATE_DATE', 'n.FS_CODE', 'n.TOTAL_AMOUNT')
                    ->get()->toArray();
            }

            return ['rows' => $rows, 'isManager' => $isManager];
        } catch (\Exception $e) {
            return ['rows' => [], 'isManager' => $isManager];
        }
    }

    // ── Open replacement requests (no volunteer yet) ────────────────────────
    // The remplacement table has no personal P_ID column — it identifies
    // requests by E_CODE + SUBSTITUTE (0 = nobody has volunteered yet).

    public function getReplacementRequests(User $user): array
    {
        $sectionId = (int) $user->P_SECTION;
        $family = $this->getSectionFamily($sectionId);

        $row = DB::table('remplacement as r')
            ->join('evenement_horaire as eh', function ($j) {
                $j->on('eh.E_CODE', '=', 'r.E_CODE')->where('eh.EH_ID', 1);
            })
            ->join('evenement as e', 'e.E_CODE', '=', 'r.E_CODE')
            ->where('r.APPROVED', 0)
            ->where('r.REJECTED', 0)
            ->where('r.SUBSTITUTE', 0)
            ->whereRaw('eh.EH_DATE_FIN >= NOW()')
            ->whereIn('e.S_ID', $family)
            ->select(
                DB::raw('COUNT(1) AS NB'),
                DB::raw("DATE_FORMAT(MIN(eh.EH_DATE_DEBUT),'%d-%m-%Y') AS DEBUT"),
                DB::raw("DATE_FORMAT(MAX(eh.EH_DATE_FIN),'%d-%m-%Y') AS FIN")
            )
            ->first();

        return [
            'count' => (int) ($row->NB ?? 0),
            'debut' => $row->DEBUT ?? null,
            'fin' => $row->FIN ?? null,
        ];
    }

    // ── About / app info ────────────────────────────────────────────────────

    public function getAbout(User $user): array
    {
        $version = config('brigade.version');

        // Webmaster email from section role
        $sectionId = (int) $user->P_SECTION;
        $webmaster = DB::table('pompier as p')
            ->join('ob_user_assignment as a', 'a.person_id', '=', 'p.P_ID')
            ->join('groupe as g', 'g.GP_ID', '=', 'a.group_id')
            ->whereRaw("UPPER(g.GP_DESCRIPTION) LIKE 'WEB%MASTER'")
            ->whereIn('a.section_id', $this->getSectionFamilyUp($sectionId))
            ->value('p.P_EMAIL');

        return [
            'version' => $version,
            'supportEmail' => $webmaster ?: config('mail.from.address'),
            'canAdmin' => $user->hasPermission(14),
        ];
    }
}
