<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Computes suggested repos (Jour / Nuit half-days) for a person over a month,
 * from the factors configured in Admin ▸ Options (Planning tab):
 *
 *  - post-garde security rest (rest the day after a 24h garde),
 *  - half-day by shift (night garde → Jour repos, day garde → Nuit repos),
 *  - timesheet threshold (a day worked beyond N minutes → rest the next day),
 *  - a monthly minimum number of rest days (top-up on free days).
 *
 * Suggestions are advisory: the Repos screen pre-fills the grid with them and a
 * manager overrides before saving. Nothing here writes to the database.
 */
class ReposSuggestionService
{
    public function __construct(private readonly GeneralSettingService $settings) {}

    /** Whether the "Suggérer" action is enabled at all. */
    public function enabled(): bool
    {
        return $this->settings->int('repos_auto_enable') === 1;
    }

    /**
     * @return array<string, array{jour: bool, nuit: bool}> keyed by Y-m-d (in month)
     */
    public function suggest(int $personId, Carbon $first, Carbon $last): array
    {
        $out = [];
        $add = function (string $date, bool $jour, bool $nuit) use (&$out, $first, $last): void {
            if ($date < $first->toDateString() || $date > $last->toDateString()) {
                return;
            }
            $out[$date]['jour'] = ($out[$date]['jour'] ?? false) || $jour;
            $out[$date]['nuit'] = ($out[$date]['nuit'] ?? false) || $nuit;
        };

        $postGarde = $this->settings->int('repos_postgarde') === 1;
        $halfShift = $this->settings->int('repos_halfday_shift') === 1;
        $scope = $this->settings->string('repos_postgarde_scope') ?: 'full';
        $threshold = $this->settings->int('repos_timesheet_threshold_min');
        $minMonth = $this->settings->int('repos_min_rest_days');
        $minWeek = $this->settings->int('repos_min_rest_days_week');

        // Look back one day so a garde on the last day of the previous month can
        // still suggest rest on the 1st.
        $scanFrom = $first->copy()->subDay();

        // ── Post-garde rest ───────────────────────────────────────────────────
        if ($postGarde || $halfShift) {
            foreach ($this->gardeHalvesByDate($personId, $scanFrom, $last) as $date => $halves) {
                $restDate = Carbon::parse($date)->addDay()->toDateString();
                if ($halfShift) {
                    // night worked → rest the day; day worked → rest the night.
                    $add($restDate, in_array(2, $halves, true), in_array(1, $halves, true));
                } else {
                    $add($restDate, $scope !== 'nuit', $scope !== 'jour');
                }
            }
        }

        // ── Timesheet threshold ───────────────────────────────────────────────
        if ($threshold > 0) {
            foreach (DB::table('horaires')
                ->where('P_ID', $personId)
                ->whereBetween('H_DATE', [$scanFrom->toDateString(), $last->toDateString()])
                ->where('H_DUREE_MINUTES', '>', $threshold)
                ->pluck('H_DATE') as $worked) {
                $add(Carbon::parse($worked)->addDay()->toDateString(), true, true);
            }
        }

        // ── Minimum rest days (top-up free days), per week then per month ──────
        if ($minWeek > 0 || $minMonth > 0) {
            $busy = $this->busyDays($personId, $first, $last);

            if ($minWeek > 0) {
                foreach ($this->weekWindows($first, $last) as [$ws, $we]) {
                    $this->topUpWindow($out, $busy, $ws, $we, $minWeek);
                }
            }
            if ($minMonth > 0) {
                $this->topUpWindow($out, $busy, $first, $last, $minMonth);
            }
        }

        // Normalise every entry to the full shape.
        return array_map(fn ($v) => ['jour' => $v['jour'] ?? false, 'nuit' => $v['nuit'] ?? false], $out);
    }

    /**
     * Garde days the person worked, mapped to the halves worked (1 = day, 2 =
     * night), derived from evenement_horaire.EH_ID.
     *
     * @return array<string, array<int, int>> Y-m-d => [EH_ID, …]
     */
    private function gardeHalvesByDate(int $personId, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('evenement_participation as ep')
            ->join('evenement as e', 'ep.E_CODE', '=', 'e.E_CODE')
            ->join('evenement_horaire as eh', function ($j) {
                $j->on('eh.E_CODE', '=', 'ep.E_CODE')->on('eh.EH_ID', '=', 'ep.EH_ID');
            })
            ->where('ep.P_ID', $personId)
            ->where('ep.EP_ABSENT', 0)
            ->where('e.E_CANCELED', 0)
            ->where('e.TE_CODE', 'GAR')
            ->whereBetween('eh.EH_DATE_DEBUT', [$from->toDateString(), $to->toDateString()])
            ->get(['eh.EH_ID', DB::raw('DATE(eh.EH_DATE_DEBUT) as d')]);

        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r->d][] = (int) $r->EH_ID;
        }

        return $map;
    }

    /**
     * Ensure at least $min days carry a (full) rest suggestion within one window,
     * filling the soonest days that have neither activity nor an existing rest.
     *
     * @param  array<string, array{jour?: bool, nuit?: bool}>  $out
     * @param  Collection<string, int>  $busy  day (Y-m-d) => flag
     */
    private function topUpWindow(array &$out, $busy, Carbon $winStart, Carbon $winEnd, int $min): void
    {
        $restDays = 0;
        for ($d = $winStart->copy(); $d->lte($winEnd); $d->addDay()) {
            $key = $d->toDateString();
            if (($out[$key]['jour'] ?? false) || ($out[$key]['nuit'] ?? false)) {
                $restDays++;
            }
        }
        if ($restDays >= $min) {
            return;
        }

        for ($d = $winStart->copy(); $d->lte($winEnd) && $restDays < $min; $d->addDay()) {
            $key = $d->toDateString();
            $hasRest = ($out[$key]['jour'] ?? false) || ($out[$key]['nuit'] ?? false);
            if ($hasRest || $busy->has($key)) {
                continue;
            }
            $out[$key]['jour'] = true;
            $out[$key]['nuit'] = true;
            $restDays++;
        }
    }

    /**
     * Days in the month on which the person is booked on an activity.
     *
     * @return Collection<string, int>
     */
    private function busyDays(int $personId, Carbon $first, Carbon $last)
    {
        return DB::table('evenement_participation as ep')
            ->join('evenement_horaire as eh', function ($j) {
                $j->on('eh.E_CODE', '=', 'ep.E_CODE')->on('eh.EH_ID', '=', 'ep.EH_ID');
            })
            ->where('ep.P_ID', $personId)
            ->where('ep.EP_ABSENT', 0)
            ->whereBetween('eh.EH_DATE_DEBUT', [$first->toDateString(), $last->toDateString()])
            ->pluck(DB::raw('DATE(eh.EH_DATE_DEBUT)'))
            ->map(fn ($d) => (string) $d)
            ->flip();
    }

    /**
     * ISO-week windows (Mon–Sun) overlapping the month, each clipped to it.
     *
     * @return array<int, array{0: Carbon, 1: Carbon}>
     */
    private function weekWindows(Carbon $first, Carbon $last): array
    {
        $windows = [];
        $cursor = $first->copy()->startOfWeek(Carbon::MONDAY);
        while ($cursor->lte($last)) {
            $ws = $cursor->copy()->max($first);
            $we = $cursor->copy()->endOfWeek(Carbon::SUNDAY)->min($last);
            $windows[] = [$ws, $we];
            $cursor->addWeek();
        }

        return $windows;
    }
}
