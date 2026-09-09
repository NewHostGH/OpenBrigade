<?php

namespace App\Http\Controllers;

use App\Services\SectionScopeService;
use App\Services\TableExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlanningController extends Controller
{
    /** Per-person colour palette (assigned by position in the visible list). */
    private const PALETTE = [
        '#2563eb', '#db2777', '#16a34a', '#d97706', '#7c3aed',
        '#0891b2', '#dc2626', '#4f46e5', '#ca8a04', '#0d9488',
        '#be123c', '#65a30d', '#9333ea', '#2dd4bf', '#e11d48',
    ];

    public function __construct(
        private readonly SectionScopeService $scope,
    ) {}

    /**
     * Planning calendar. Managers (permission 56) pick several people from a
     * scoped personnel list; everyone else sees only themselves. FullCalendar
     * fetches activities + absences from {@see self::events()}.
     */
    public function index(Request $request): View
    {
        $personnel = $this->visiblePersonnel($request)->values();
        $personnel->each(function ($p, $i): void {
            $p->color = self::PALETTE[$i % count(self::PALETTE)];
        });

        return view('planning.index', [
            'personnel' => $personnel,
            'sectionId' => $this->scope->sectionFilter($request),
            'canSeeOthers' => (bool) auth()->user()->hasPermission(56),
        ]);
    }

    /**
     * FullCalendar events feed — activities + absences for the selected people.
     * Requested ids are intersected with the viewer's visible set (so the client
     * can never pull outside its scope); with no selection it defaults to self.
     * FullCalendar appends ?start=&end=.
     */
    public function events(Request $request): JsonResponse
    {
        $self = (int) auth()->user()->P_ID;

        $from = $request->query('start')
            ? Carbon::parse($request->query('start'))->toDateString()
            : now()->startOfMonth()->toDateString();
        $to = $request->query('end')
            ? Carbon::parse($request->query('end'))->toDateString()
            : now()->endOfMonth()->toDateString();

        $visible = $this->visiblePersonnel($request)->values();
        $visibleIds = $visible->pluck('P_ID')->map(fn ($id) => (int) $id)->all();

        $requested = array_filter(array_map('intval', (array) $request->query('people', [])));
        // `filtered` = the selection is explicit (from the checkbox list), so an
        // empty set genuinely means "nobody" — the viewer can hide their own too.
        // Without it (first load / no JS) fall back to the signed-in user.
        $pids = $request->has('filtered')
            ? array_values(array_intersect($requested, $visibleIds))
            : ($requested ? array_values(array_intersect($requested, $visibleIds)) : [$self]);
        if (empty($pids)) {
            return response()->json([]);
        }

        // Per-person colour + name; only prefixed/coloured when showing several.
        $meta = [];
        foreach ($visible as $i => $p) {
            $meta[(int) $p->P_ID] = [
                'color' => self::PALETTE[$i % count(self::PALETTE)],
                'name' => strtoupper($p->P_NOM).' '.$p->P_PRENOM,
            ];
        }
        $multi = count($pids) > 1;

        $events = DB::table('evenement_participation as ep')
            ->join('evenement as e', 'ep.E_CODE', '=', 'e.E_CODE')
            ->join('evenement_horaire as eh', function ($j) {
                $j->on('eh.E_CODE', '=', 'ep.E_CODE')
                    ->on('eh.EH_ID', '=', 'ep.EH_ID');
            })
            ->whereIn('ep.P_ID', $pids)
            ->where('ep.EP_ABSENT', 0)
            ->where('e.E_CANCELED', 0)
            ->whereBetween('eh.EH_DATE_DEBUT', [$from, $to])
            ->select(
                'ep.P_ID',
                'e.E_CODE',
                'e.E_LIBELLE',
                'e.E_CLOSED',
                DB::raw('DATE(eh.EH_DATE_DEBUT) as event_date'),
                DB::raw("TIME_FORMAT(eh.EH_DEBUT,'%H:%i') as event_time"),
                DB::raw("TIME_FORMAT(eh.EH_FIN,'%H:%i') as event_end")
            )
            ->get()
            ->map(function ($e) use ($meta, $multi) {
                $hasStart = $e->event_time && $e->event_time !== '00:00';
                $hasEnd = $e->event_end && $e->event_end !== '00:00';
                $prefix = $multi ? $meta[(int) $e->P_ID]['name'].' · ' : '';

                return [
                    'title' => $prefix.($e->E_LIBELLE ?: $e->E_CODE),
                    'start' => $hasStart ? $e->event_date.'T'.$e->event_time : $e->event_date,
                    'end' => $hasStart && $hasEnd ? $e->event_date.'T'.$e->event_end : null,
                    'url' => route('event.show', $e->E_CODE),
                    'color' => $multi ? $meta[(int) $e->P_ID]['color'] : null,
                    'classNames' => $multi ? ['fc-sp-ev'] : ($e->E_CLOSED ? ['fc-ev-activity', 'fc-ev-closed'] : ['fc-ev-activity']),
                ];
            });

        $absences = DB::table('indisponibilite as i')
            ->leftJoin('type_indisponibilite as ti', 'i.TI_CODE', '=', 'ti.TI_CODE')
            ->whereIn('i.P_ID', $pids)
            ->whereNull('i.I_CANCEL')
            ->whereNotIn('i.I_STATUS', ['REF', 'ANN'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('i.I_DEBUT', [$from, $to])
                    ->orWhereBetween('i.I_FIN', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->where('i.I_DEBUT', '<=', $from)
                            ->where('i.I_FIN', '>=', $to);
                    });
            })
            ->select('i.P_ID', 'i.I_DEBUT', 'i.I_FIN', 'i.I_ACCEPT', 'ti.TI_LIBELLE')
            ->get()
            ->map(function ($a) use ($meta, $multi) {
                $label = $a->TI_LIBELLE ?: __('planning.absence_default');
                $prefix = $multi ? $meta[(int) $a->P_ID]['name'].' · ' : '';

                return [
                    'title' => $prefix.$label.($a->I_ACCEPT ? '' : ' ('.__('planning.pending').')'),
                    'start' => Carbon::parse($a->I_DEBUT)->toDateString(),
                    // FullCalendar treats all-day `end` as exclusive.
                    'end' => Carbon::parse($a->I_FIN ?: $a->I_DEBUT)->addDay()->toDateString(),
                    'allDay' => true,
                    'color' => $multi ? $meta[(int) $a->P_ID]['color'] : null,
                    'classNames' => $multi
                        ? ($a->I_ACCEPT ? ['fc-sp-abs'] : ['fc-sp-abs-pending'])
                        : ($a->I_ACCEPT ? ['fc-ev-abs-ok'] : ['fc-ev-abs-pending']),
                ];
            });

        return response()->json($events->concat($absences)->values());
    }

    /**
     * Personnel the viewer may see on the planning: their section scope if they
     * hold permission 56 ("Voir le personnel"), otherwise just themselves.
     */
    private function visiblePersonnel(Request $request): Collection
    {
        $user = auth()->user();

        if (! $user->hasPermission(56)) {
            return collect([(object) [
                'P_ID' => (int) $user->P_ID,
                'P_NOM' => $user->P_NOM,
                'P_PRENOM' => $user->P_PRENOM,
                'P_SECTION' => $user->P_SECTION,
            ]]);
        }

        $query = DB::table('pompier as p')
            ->where('p.P_OLD_MEMBER', 0)
            ->whereNull('p.P_FIN')
            ->orderBy('p.P_NOM')
            ->orderBy('p.P_PRENOM')
            ->select('p.P_ID', 'p.P_NOM', 'p.P_PRENOM', 'p.P_SECTION');

        $this->scope->apply($query, 'p.P_SECTION', $this->scope->sectionFilter($request));

        return $query->get();
    }

    /**
     * Print-optimised monthly planning for the selected people (browser print →
     * PDF). One section per person, each listing their activities and absences.
     * Selection follows the calendar's people[] filter, intersected with scope;
     * with none selected it defaults to the signed-in user.
     */
    public function print(Request $request): View
    {
        $self = (int) auth()->user()->P_ID;

        $year = (int) $request->integer('year', now()->year);
        $month = (int) $request->integer('month', now()->month);
        if ($month < 1) {
            $month = 12;
            $year--;
        }
        if ($month > 12) {
            $month = 1;
            $year++;
        }

        $first = Carbon::create($year, $month, 1)->startOfDay();
        $last = $first->copy()->endOfMonth();

        $visible = $this->visiblePersonnel($request)->keyBy(fn ($p) => (int) $p->P_ID);
        $requested = array_filter(array_map('intval', (array) $request->query('people', [])));
        $pids = $requested
            ? array_values(array_intersect($requested, $visible->keys()->all()))
            : [$self];

        $eventsByPid = DB::table('evenement_participation as ep')
            ->join('evenement as e', 'ep.E_CODE', '=', 'e.E_CODE')
            ->join('evenement_horaire as eh', function ($j) {
                $j->on('eh.E_CODE', '=', 'ep.E_CODE')
                    ->on('eh.EH_ID', '=', 'ep.EH_ID');
            })
            ->join('type_evenement as te', 'e.TE_CODE', '=', 'te.TE_CODE')
            ->whereIn('ep.P_ID', $pids)
            ->where('ep.EP_ABSENT', 0)
            ->where('e.E_CANCELED', 0)
            ->whereBetween('eh.EH_DATE_DEBUT', [$first->toDateString(), $last->toDateString()])
            ->select(
                'ep.P_ID',
                'e.E_CODE',
                'e.E_LIBELLE',
                'e.E_CLOSED',
                'te.TE_LIBELLE',
                DB::raw('DATE(eh.EH_DATE_DEBUT) as event_date'),
                DB::raw("TIME_FORMAT(eh.EH_DEBUT,'%H:%i') as event_time")
            )
            ->orderBy('eh.EH_DATE_DEBUT')
            ->get()
            ->groupBy('P_ID');

        $absencesByPid = DB::table('indisponibilite as i')
            ->leftJoin('type_indisponibilite as ti', 'i.TI_CODE', '=', 'ti.TI_CODE')
            ->whereIn('i.P_ID', $pids)
            ->whereNull('i.I_CANCEL')
            ->whereNotIn('i.I_STATUS', ['REF', 'ANN'])
            ->where(function ($q) use ($first, $last) {
                $q->whereBetween('i.I_DEBUT', [$first->toDateString(), $last->toDateString()])
                    ->orWhereBetween('i.I_FIN', [$first->toDateString(), $last->toDateString()])
                    ->orWhere(function ($inner) use ($first, $last) {
                        $inner->where('i.I_DEBUT', '<=', $first->toDateString())
                            ->where('i.I_FIN', '>=', $last->toDateString());
                    });
            })
            ->select('i.P_ID', 'i.I_DEBUT', 'i.I_FIN', 'i.I_ACCEPT', 'i.I_COMMENT', 'ti.TI_LIBELLE')
            ->orderBy('i.I_DEBUT')
            ->get()
            ->groupBy('P_ID');

        $people = collect($pids)->map(fn ($pid) => [
            'name' => $visible->has($pid)
                ? strtoupper($visible[$pid]->P_NOM).' '.$visible[$pid]->P_PRENOM
                : '#'.$pid,
            'events' => $eventsByPid->get($pid, collect()),
            'absences' => $absencesByPid->get($pid, collect()),
        ]);

        return view('planning.print', compact('people', 'first', 'year', 'month'));
    }

    /** Monthly planning matrix (personnel × days) as an XLSX download. */
    public function exportXls(Request $request, TableExportService $export): StreamedResponse
    {
        [$columns, $items, $filename] = $this->matrix($request);

        return $export->toXlsx($columns, $items, $filename, [
            'sheetTitle' => __('planning.title'),
            'freezeHeader' => true,
            'repeatHeader' => true,
        ]);
    }

    /** Monthly planning matrix (personnel × days) as a CSV download. */
    public function exportCsv(Request $request, TableExportService $export): StreamedResponse
    {
        [$columns, $items, $filename] = $this->matrix($request);

        return $export->toCsv($columns, $items, $filename);
    }

    /**
     * Build the monthly personnel × days matrix for export. Each row is a person
     * (Nom, Prénom, Section) followed by one cell per day of the month holding an
     * accepted-absence code, else the number of activities that day, else blank.
     * Scope follows the calendar: the people[] selection intersected with the
     * viewer's visible set (all visible when none selected), plus ?section= /
     * ?year= / ?month=.
     *
     * @return array{0: array<int, array{0: string, 1: callable}>, 1: Collection<int, object>, 2: string}
     */
    private function matrix(Request $request): array
    {
        $year = (int) $request->integer('year', now()->year);
        $month = (int) $request->integer('month', now()->month);
        if ($month < 1) {
            $month = 12;
            $year--;
        }
        if ($month > 12) {
            $month = 1;
            $year++;
        }

        $first = Carbon::create($year, $month, 1)->startOfDay();
        $last = $first->copy()->endOfMonth();
        $daysInMonth = (int) $last->day;

        $visible = $this->visiblePersonnel($request)->keyBy(fn ($p) => (int) $p->P_ID);
        $requested = array_filter(array_map('intval', (array) $request->query('people', [])));
        $pids = $requested
            ? array_values(array_intersect($requested, $visible->keys()->all()))
            : $visible->keys()->all();

        // Personnel rows with their section code, in the visible order.
        $rows = collect();
        if (! empty($pids)) {
            $rows = DB::table('pompier as p')
                ->leftJoin('section as s', 'p.P_SECTION', '=', 's.S_ID')
                ->whereIn('p.P_ID', $pids)
                ->orderBy('p.P_NOM')
                ->orderBy('p.P_PRENOM')
                ->get(['p.P_ID', 'p.P_NOM', 'p.P_PRENOM', 's.S_CODE']);
        }

        // Activities per person per day (excluding main-courante and cancelled).
        $activity = [];
        if (! empty($pids)) {
            foreach (DB::table('evenement_participation as ep')
                ->join('evenement as e', 'ep.E_CODE', '=', 'e.E_CODE')
                ->join('evenement_horaire as eh', function ($j) {
                    $j->on('eh.E_CODE', '=', 'ep.E_CODE')->on('eh.EH_ID', '=', 'ep.EH_ID');
                })
                ->whereIn('ep.P_ID', $pids)
                ->where('ep.EP_ABSENT', 0)
                ->where('e.E_CANCELED', 0)
                ->where('e.TE_CODE', '<>', 'MC')
                ->whereBetween('eh.EH_DATE_DEBUT', [$first->toDateString(), $last->toDateString()])
                ->groupBy('ep.P_ID', 'day')
                ->select('ep.P_ID', DB::raw('DAY(eh.EH_DATE_DEBUT) as day'), DB::raw('COUNT(*) as nb'))
                ->get() as $r) {
                $activity[(int) $r->P_ID][(int) $r->day] = (int) $r->nb;
            }
        }

        // Accepted absences → a code stamped on every covered day.
        $absence = [];
        if (! empty($pids)) {
            foreach (DB::table('indisponibilite as i')
                ->whereIn('i.P_ID', $pids)
                ->whereNull('i.I_CANCEL')
                ->where('i.I_STATUS', 'VAL')
                ->where('i.I_DEBUT', '<=', $last->toDateString())
                ->where('i.I_FIN', '>=', $first->toDateString())
                ->get(['i.P_ID', 'i.I_DEBUT', 'i.I_FIN', 'i.TI_CODE']) as $a) {
                $start = Carbon::parse($a->I_DEBUT)->max($first);
                $end = Carbon::parse($a->I_FIN ?: $a->I_DEBUT)->min($last);
                for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                    $absence[(int) $a->P_ID][(int) $d->day] = $a->TI_CODE ?: 'ABS';
                }
            }
        }

        // Attach the per-day cell map to each person row.
        $rows->each(function ($p) use ($activity, $absence, $daysInMonth): void {
            $pid = (int) $p->P_ID;
            $days = [];
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $days[$d] = $absence[$pid][$d]
                    ?? (! empty($activity[$pid][$d]) ? (string) $activity[$pid][$d] : '');
            }
            $p->days = $days;
        });

        $columns = [
            [__('planning.export_col_lastname'), fn ($p) => strtoupper((string) $p->P_NOM)],
            [__('planning.export_col_firstname'), fn ($p) => $p->P_PRENOM],
            [__('planning.export_col_section'), fn ($p) => $p->S_CODE],
        ];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $columns[] = [(string) $d, fn ($p) => $p->days[$d] ?? ''];
        }

        $filename = 'planning-'.$first->format('Y-m');

        return [$columns, $rows, $filename];
    }
}
