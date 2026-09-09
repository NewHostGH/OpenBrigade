<?php

namespace App\Http\Controllers;

use App\Services\GeneralSettingService;
use App\Services\SectionScopeService;
use App\Services\WorkAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Disponibilités — "who is available when". A people × days grid for one week
 * (navigable): each cell shows the period(s) a person declared themselves
 * available (from `disponibilite`). The number of periods per 24h honours the
 * `dispo_periodes` setting. Managers (permission 56) see the people in their
 * section scope; everyone else sees only themselves.
 */
class AvailabilityController extends Controller
{
    public function __construct(
        private readonly SectionScopeService $scope,
        private readonly GeneralSettingService $settings,
        private readonly WorkAvailabilityService $coherence,
    ) {}

    public function index(Request $request): View
    {
        return view('availability.index', $this->board($request));
    }

    /** Print-optimised availability grid (browser print → PDF). */
    public function print(Request $request): View
    {
        return view('availability.print', $this->board($request));
    }

    /**
     * Shared board data: the day columns, scoped personnel and per-person /
     * per-day availability periods.
     *
     * @return array<string,mixed>
     */
    private function board(Request $request): array
    {
        // One navigable week (Monday–Sunday).
        $week = (int) $request->integer('week', 0);
        $first = now()->startOfWeek(Carbon::MONDAY)->addWeeks($week);
        $end = $first->copy()->endOfWeek(Carbon::SUNDAY);

        $personnel = $this->visiblePersonnel($request)->values();
        $pids = $personnel->pluck('P_ID')->map(fn ($id) => (int) $id)->all();

        // Availability periods per 24h honour the `dispo_periodes` setting (1–4).
        // Each mode maps to a specific set of period IDs with mode-specific
        // names (mirrors the legacy day split), so the slots and legend change
        // with the setting.
        $count = $this->settings->int('dispo_periodes');
        $count = ($count >= 1 && $count <= 4) ? $count : 4;
        $sets = [1 => [1], 2 => [1, 4], 3 => [1, 2, 4], 4 => [1, 2, 3, 4]];
        $names = [
            1 => [1 => 'Journée'],
            2 => [1 => 'Jour', 4 => 'Nuit'],
            3 => [1 => 'Matin', 2 => 'Après-midi', 4 => 'Nuit'],
            4 => [1 => 'Matin', 2 => 'Après-midi', 3 => 'Soir', 4 => 'Nuit'],
        ];
        $allowedPeriodIds = $sets[$count];
        $periods = collect($allowedPeriodIds)->map(fn ($id) => (object) [
            'DP_ID' => $id,
            'DP_NAME' => $names[$count][$id] ?? '',
        ])->values();

        // [P_ID][Y-m-d] => [PERIOD_ID, …] (only the allowed periods)
        $byPersonDate = [];
        if (! empty($pids)) {
            foreach (DB::table('disponibilite')
                ->whereIn('P_ID', $pids)
                ->whereIn('PERIOD_ID', $allowedPeriodIds)
                ->whereBetween('D_DATE', [$first->toDateString(), $end->toDateString()])
                ->get(['P_ID', 'D_DATE', 'PERIOD_ID']) as $row) {
                $byPersonDate[(int) $row->P_ID][$row->D_DATE][] = (int) $row->PERIOD_ID;
            }
        }

        $days = [];
        for ($d = $first->copy(); $d->lte($end); $d->addDay()) {
            $days[] = [
                'key' => $d->format('Y-m-d'),
                'day' => (int) $d->day,
                'weekday' => ucfirst($d->locale('fr')->isoFormat('ddd')),
                'isWeekend' => $d->isWeekend(),
                'isToday' => $d->isToday(),
                'isPast' => $d->isPast() && ! $d->isToday(),
            ];
        }

        // Slots locked because the person is absent, resting or already working.
        $blocked = $this->coherence->blockedMap($pids, $first->toDateString(), $end->toDateString());

        return [
            'personnel' => $personnel,
            'periods' => $periods,
            'periodMap' => $periods->keyBy('DP_ID'),
            'byPersonDate' => $byPersonDate,
            'blocked' => $blocked,
            'days' => $days,
            'first' => $first,
            'end' => $end,
            'week' => $week,
            'prevWeek' => $week - 1,
            'nextWeek' => $week + 1,
            'canSeeOthers' => (bool) auth()->user()->hasPermission(56),
            'sectionId' => $this->scope->sectionFilter($request),
        ];
    }

    /**
     * Declare / withdraw the signed-in user's own availability for one day and
     * period (toggles the `disponibilite` row). Only self, only today onward.
     */
    public function toggle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'period' => ['required', 'integer', 'exists:disponibilite_periode,DP_ID'],
        ]);

        $pid = (int) auth()->user()->P_ID;
        $period = (int) $validated['period'];
        $keys = ['P_ID' => $pid, 'D_DATE' => $validated['date'], 'PERIOD_ID' => $period];

        $exists = DB::table('disponibilite')->where($keys)->exists();
        if ($exists) {
            DB::table('disponibilite')->where($keys)->delete();

            return response()->json(['available' => false]);
        }

        // Can't declare available for a slot you're absent, resting or working.
        $blocked = $this->coherence->blockedMap([$pid], $validated['date'], $validated['date']);
        if (! empty($blocked[$pid][$validated['date']][$period])) {
            return response()->json(['available' => false, 'blocked' => true], 422);
        }

        DB::table('disponibilite')->insert($keys);

        return response()->json(['available' => true]);
    }

    /**
     * Personnel the viewer may see: their section scope if they hold permission
     * 56 ("Voir le personnel"), otherwise just themselves.
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
}
