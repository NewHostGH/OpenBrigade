<?php

namespace App\Http\Controllers;

use App\Services\SectionScopeService;
use App\Services\TableExportService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DutyController extends Controller
{
    /** Per-role colour palette for the calendar (assigned by GP_ID). */
    private const ROLE_PALETTE = [
        '#2563eb', '#16a34a', '#d97706', '#7c3aed', '#0891b2',
        '#dc2626', '#db2777', '#4f46e5', '#ca8a04', '#0d9488',
    ];

    public function __construct(
        private readonly SectionScopeService $sectionScope,
    ) {}

    /**
     * On-call roster as a month calendar (FullCalendar). Astreinte slots are
     * fetched from {@see self::calendarEvents()} and coloured per role.
     */
    public function calendar(Request $request): View
    {
        return view('duty.calendar', [
            'sectionId' => $this->sectionScope->sectionFilter($request),
        ]);
    }

    /**
     * FullCalendar events feed — section-scoped astreinte slots overlapping the
     * requested range (FullCalendar appends ?start=&end=), coloured per role.
     */
    public function calendarEvents(Request $request): JsonResponse
    {
        $from = $request->query('start')
            ? Carbon::parse($request->query('start'))->toDateTimeString()
            : now()->startOfMonth()->toDateTimeString();
        $to = $request->query('end')
            ? Carbon::parse($request->query('end'))->toDateTimeString()
            : now()->endOfMonth()->toDateTimeString();

        $query = DB::table('astreinte as a')
            ->join('pompier as p', 'a.P_ID', '=', 'p.P_ID')
            ->join('groupe as g', 'a.GP_ID', '=', 'g.GP_ID')
            ->where('a.AS_DEBUT', '<=', $to)
            ->where('a.AS_FIN', '>=', $from)
            ->orderBy('a.AS_DEBUT')
            ->select(
                'a.AS_ID', 'a.AS_DEBUT', 'a.AS_FIN',
                'p.P_NOM', 'p.P_PRENOM', 'g.GP_ID', 'g.GP_DESCRIPTION'
            );

        $this->sectionScope->apply($query, 'a.S_ID', $this->sectionScope->sectionFilter($request));

        $events = $query->get()->map(function ($s) {
            $color = self::ROLE_PALETTE[(int) $s->GP_ID % count(self::ROLE_PALETTE)];
            $name = strtoupper($s->P_NOM).' '.$s->P_PRENOM;

            return [
                'title' => $s->GP_DESCRIPTION ? $s->GP_DESCRIPTION.' · '.$name : $name,
                'start' => Carbon::parse($s->AS_DEBUT)->toIso8601String(),
                'end' => Carbon::parse($s->AS_FIN)->toIso8601String(),
                'color' => $color,
            ];
        });

        return response()->json($events);
    }

    /**
     * On-call roster (tableau de garde) for a given week.
     *
     * Displays all astreinte slots for the user's section within the
     * selected week, grouped by day, so operators can see who is on duty.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $sectionId = (int) $user->P_SECTION;

        // Week navigation: default to current week's Monday
        $weekOffset = (int) $request->integer('week', 0);
        $monday = now()->startOfWeek()->addWeeks($weekOffset);
        $sunday = $monday->copy()->endOfWeek();

        $prevWeek = $weekOffset - 1;
        $nextWeek = $weekOffset + 1;

        // Fetch all slots for the week
        $slots = DB::table('astreinte as a')
            ->join('pompier as p', 'a.P_ID', '=', 'p.P_ID')
            ->join('groupe as g', 'a.GP_ID', '=', 'g.GP_ID')
            ->where('a.S_ID', $sectionId)
            ->whereBetween('a.AS_DEBUT', [
                $monday->toDateTimeString(),
                $sunday->toDateTimeString(),
            ])
            ->orderBy('a.AS_DEBUT')
            ->orderBy('p.P_NOM')
            ->select(
                'a.AS_ID',
                'a.AS_DEBUT',
                'a.AS_FIN',
                'p.P_ID',
                'p.P_NOM',
                'p.P_PRENOM',
                'p.P_PHOTO',
                'p.P_PHONE',
                'g.GP_DESCRIPTION',
                DB::raw('DATE(a.AS_DEBUT) as day_date')
            )
            ->get();

        // Group slots by day
        $byDay = $slots->groupBy('day_date');

        // Build the 7-day grid
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $monday->copy()->addDays($i);
            $dateKey = $date->format('Y-m-d');
            $days[] = [
                'date' => $date,
                'label' => ucfirst($date->locale('fr')->isoFormat('ddd D MMM')),
                'slots' => $byDay->get($dateKey, collect()),
                'isToday' => $date->isToday(),
            ];
        }

        // Roles for column headings
        $roles = DB::table('astreinte as a')
            ->join('groupe as g', 'a.GP_ID', '=', 'g.GP_ID')
            ->where('a.S_ID', $sectionId)
            ->whereBetween('a.AS_DEBUT', [
                $monday->toDateTimeString(),
                $sunday->toDateTimeString(),
            ])
            ->distinct()
            ->orderBy('g.GP_DESCRIPTION')
            ->pluck('g.GP_DESCRIPTION')
            ->toArray();

        return view('duty.index', compact(
            'days', 'monday', 'sunday', 'prevWeek', 'nextWeek', 'weekOffset', 'roles'
        ));
    }

    /**
     * "Garde du jour" — a focused view of the guard/on-call slots that cover
     * today for the user's section, grouped by role. Distinct from the weekly
     * "Tableau de garde" (index) which shows the whole week as a grid; both read
     * the same `astreinte` table.
     */
    public function today(Request $request): View
    {
        $user = auth()->user();
        $sectionId = (int) $user->P_SECTION;

        $day = now()->startOfDay();
        $dayEnd = now()->endOfDay();

        $slots = DB::table('astreinte as a')
            ->join('pompier as p', 'a.P_ID', '=', 'p.P_ID')
            ->join('groupe as g', 'a.GP_ID', '=', 'g.GP_ID')
            ->where('a.S_ID', $sectionId)
            ->where('a.AS_DEBUT', '<=', $dayEnd->toDateTimeString())
            ->where('a.AS_FIN', '>=', $day->toDateTimeString())
            ->orderBy('g.GP_DESCRIPTION')
            ->orderBy('a.AS_DEBUT')
            ->orderBy('p.P_NOM')
            ->select(
                'a.AS_ID', 'a.AS_DEBUT', 'a.AS_FIN',
                'p.P_ID', 'p.P_NOM', 'p.P_PRENOM', 'p.P_PHONE',
                'g.GP_DESCRIPTION'
            )
            ->get();

        $byRole = $slots->groupBy('GP_DESCRIPTION');

        return view('duty.today', compact('slots', 'byRole', 'day'));
    }

    /**
     * Astreintes management list — admin view for managing on-call slots.
     */
    public function onCall(Request $request): View
    {
        [$month, $year, $first] = $this->onCallPeriod($request);

        $slots = $this->buildOnCallQuery($request)->paginate(50)->withQueryString();

        $prevMonth = $month === 1 ? 12 : $month - 1;
        $prevYear = $month === 1 ? $year - 1 : $year;
        $nextMonth = $month === 12 ? 1 : $month + 1;
        $nextYear = $month === 12 ? $year + 1 : $year;

        return view('duty.on-call', compact(
            'slots', 'month', 'year', 'first',
            'prevMonth', 'prevYear', 'nextMonth', 'nextYear'
        ) + ['columns' => $this->onCallColumns()]);
    }

    /**
     * Print-optimised on-call roster for the month (guard export → PDF via the
     * browser print dialog). Shares the section-scoped query with the list and
     * the XLS/CSV exports, but returns the full month unpaginated.
     */
    public function printOnCall(Request $request): View
    {
        [$month, $year, $first] = $this->onCallPeriod($request);

        $slots = $this->buildOnCallQuery($request)->get();

        return view('duty.on-call-print', compact('slots', 'month', 'year', 'first'));
    }

    /**
     * Resolve and normalise the requested on-call month/year (wrapping at the
     * year boundary), returning [month, year, firstDayOfMonth].
     *
     * @return array{0:int,1:int,2:Carbon}
     */
    private function onCallPeriod(Request $request): array
    {
        $month = (int) $request->integer('month', now()->month);
        $year = (int) $request->integer('year', now()->year);

        if ($month < 1) {
            $month = 12;
            $year--;
        }
        if ($month > 12) {
            $month = 1;
            $year++;
        }

        return [$month, $year, Carbon::create($year, $month, 1)->startOfDay()];
    }

    /**
     * Section-scoped on-call (astreinte) slots for the requested month, shared
     * by the list and the exports. Pagination is applied by the caller.
     */
    private function buildOnCallQuery(Request $request): Builder
    {
        $filtSect = $this->sectionScope->sectionFilter($request);
        [, , $first] = $this->onCallPeriod($request);
        $last = $first->copy()->endOfMonth();

        $query = DB::table('astreinte as a')
            ->join('pompier as p', 'a.P_ID', '=', 'p.P_ID')
            ->join('groupe as g', 'a.GP_ID', '=', 'g.GP_ID');

        $this->sectionScope->apply($query, 'a.S_ID', $filtSect);

        return $query->whereBetween('a.AS_DEBUT', [$first->toDateTimeString(), $last->toDateTimeString()])
            ->orderBy('a.AS_DEBUT')
            ->select(
                'a.AS_ID', 'a.AS_DEBUT', 'a.AS_FIN',
                'p.P_ID', 'p.P_NOM', 'p.P_PRENOM',
                'g.GP_ID', 'g.GP_DESCRIPTION'
            );
    }

    /**
     * Stream the month's on-call roster as an XLSX download (guard export).
     */
    public function exportOnCallXls(Request $request)
    {
        return $this->exportOnCall($request, 'xlsx');
    }

    /**
     * Stream the month's on-call roster as a CSV download.
     */
    public function exportOnCallCsv(Request $request)
    {
        return $this->exportOnCall($request, 'csv');
    }

    private function exportOnCall(Request $request, string $format)
    {
        [$month, $year] = $this->onCallPeriod($request);

        $service = new TableExportService;
        // 'debut' / 'personnel' are alwaysVisible, so resolveColumns skips them.
        $columns = $service->resolveColumns($this->onCallColumns(), $request, [
            ['Début',     fn ($s) => Carbon::parse($s->AS_DEBUT)->format('d/m/Y H:i')],
            ['Personnel', fn ($s) => $s->P_PRENOM.' '.strtoupper((string) $s->P_NOM)],
        ]);

        $items = $this->buildOnCallQuery($request)->get();
        $filename = 'Astreintes_'.sprintf('%04d-%02d', $year, $month);

        return $format === 'csv'
            ? $service->toCsv($columns, $items, $filename)
            : $service->toXlsx($columns, $items, $filename, ['sheetTitle' => 'Astreintes', 'freezeHeader' => true]);
    }

    private function onCallColumns(): array
    {
        return [
            ['key' => 'debut', 'label' => 'Début', 'type' => 'html', 'value' => fn ($s) => Carbon::parse($s->AS_DEBUT)->locale('fr')->isoFormat('ddd D MMM, HH:mm'), 'alwaysVisible' => true, 'mobile' => true],
            ['key' => 'fin', 'label' => 'Fin', 'type' => 'text', 'value' => fn ($s) => Carbon::parse($s->AS_FIN)->locale('fr')->isoFormat('ddd D MMM, HH:mm'), 'mobile' => false, 'exportable' => true, 'exportValue' => fn ($s) => Carbon::parse($s->AS_FIN)->format('d/m/Y H:i')],
            ['key' => 'personnel', 'label' => 'Personnel', 'type' => 'text', 'value' => fn ($s) => $s->P_PRENOM.' '.strtoupper($s->P_NOM), 'alwaysVisible' => true, 'mobile' => true],
            ['key' => 'role', 'label' => 'Rôle', 'type' => 'text', 'value' => fn ($s) => $s->GP_DESCRIPTION ?? '—', 'mobile' => false, 'exportable' => true, 'exportValue' => fn ($s) => $s->GP_DESCRIPTION ?? ''],
        ];
    }
}
