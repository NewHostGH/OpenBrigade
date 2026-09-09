<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the four planning subsystems coherent around one idea: a person cannot
 * be "available" for a slot they are absent, resting, or already working.
 *
 * The day is split into four fixed clock periods (matching the availability
 * grid): 1 = Matin 06–12, 2 = Après-midi 12–18, 3 = Soir 18–24, 4 = Nuit 00–06.
 *
 *  - Accepting an absence or a repos (`indisponibilite`) clears the person's
 *    overlapping availability (`disponibilite`).
 *  - Saved worked hours (`horaires`) clear the overlapping availability too.
 *  - {@see self::blockedMap()} tells the availability grid which slots to lock,
 *    combining every "unavailable" source so they can never be re-declared.
 */
class WorkAvailabilityService
{
    /** period id => [startHour, endHour) on a 24h clock. */
    private const PERIODS = [1 => [6, 12], 2 => [12, 18], 3 => [18, 24], 4 => [0, 6]];

    // ── Writes: clear availability when unavailability is recorded ─────────────

    /** Clear availability overlapping one accepted absence / repos row. */
    public function syncAbsence(object $indispo): void
    {
        $start = Carbon::parse($indispo->I_DEBUT);
        $end = Carbon::parse($indispo->I_FIN ?: $indispo->I_DEBUT);

        // Full-day absences clear every period across the whole window; partial
        // ones clear only the overlapping periods on the (single) start day.
        $periods = (int) $indispo->I_JOUR_COMPLET === 1
            ? array_keys(self::PERIODS)
            : $this->periodsForTime($indispo->IH_DEBUT, $indispo->IH_FIN);

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $this->clear((int) $indispo->P_ID, $d->toDateString(), $periods);
        }
    }

    /**
     * Clear availability overlapping a worked day.
     *
     * @param  array<int, array{0: ?string, 1: ?string}>  $slots  [[start,end], …] as HH:MM(:SS)
     */
    public function syncWorkedDay(int $personId, string $date, array $slots): void
    {
        $periods = [];
        foreach ($slots as [$start, $end]) {
            $periods = array_merge($periods, $this->periodsForTime($start, $end));
        }
        $periods = array_values(array_unique($periods));

        if (! empty($periods)) {
            $this->clear($personId, $date, $periods);
        }
    }

    /** Delete availability rows for a person/day, optionally limited to periods. */
    public function clear(int $personId, string $date, ?array $periods = null): void
    {
        $query = DB::table('disponibilite')
            ->where('P_ID', $personId)
            ->where('D_DATE', $date);

        if ($periods !== null) {
            if (empty($periods)) {
                return;
            }
            $query->whereIn('PERIOD_ID', $periods);
        }

        $query->delete();
    }

    // ── Reads: which slots the availability grid must lock ────────────────────

    /**
     * Blocked availability slots per person/day, combining accepted absences,
     * repos and worked timesheet hours.
     *
     * @param  array<int, int>  $pids
     * @return array<int, array<string, array<int, bool>>> [pid][Y-m-d][periodId] => true
     */
    public function blockedMap(array $pids, string $from, string $to): array
    {
        $map = [];
        if (empty($pids)) {
            return $map;
        }

        // Accepted absences + repos.
        foreach (DB::table('indisponibilite')
            ->whereIn('P_ID', $pids)
            ->whereNull('I_CANCEL')
            ->where('I_STATUS', 'VAL')
            ->where('I_DEBUT', '<=', $to)
            ->where(fn ($q) => $q->where('I_FIN', '>=', $from)->orWhereNull('I_FIN'))
            ->get(['P_ID', 'I_DEBUT', 'I_FIN', 'IH_DEBUT', 'IH_FIN', 'I_JOUR_COMPLET']) as $r) {
            $periods = (int) $r->I_JOUR_COMPLET === 1
                ? array_keys(self::PERIODS)
                : $this->periodsForTime($r->IH_DEBUT, $r->IH_FIN);
            $start = Carbon::parse($r->I_DEBUT)->max(Carbon::parse($from));
            $end = Carbon::parse($r->I_FIN ?: $r->I_DEBUT)->min(Carbon::parse($to));
            for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
                foreach ($periods as $p) {
                    $map[(int) $r->P_ID][$d->toDateString()][$p] = true;
                }
            }
        }

        // Worked timesheet hours.
        foreach (DB::table('horaires')
            ->whereIn('P_ID', $pids)
            ->whereBetween('H_DATE', [$from, $to])
            ->get(['P_ID', 'H_DATE', 'H_DEBUT1', 'H_FIN1', 'H_DEBUT2', 'H_FIN2']) as $r) {
            $periods = array_merge(
                $this->periodsForTime($r->H_DEBUT1, $r->H_FIN1),
                $this->periodsForTime($r->H_DEBUT2, $r->H_FIN2),
            );
            $date = Carbon::parse($r->H_DATE)->toDateString();
            foreach ($periods as $p) {
                $map[(int) $r->P_ID][$date][$p] = true;
            }
        }

        return $map;
    }

    // ── Period maths ──────────────────────────────────────────────────────────

    /**
     * Period ids overlapping a clock range given as HH:MM(:SS) strings. A range
     * whose end is not after its start is treated as spanning midnight.
     *
     * @return array<int, int>
     */
    public function periodsForTime(?string $start, ?string $end): array
    {
        if (! $start || ! $end) {
            return [];
        }

        $s = $this->hours($start);
        $e = $this->hours($end);
        if ($s === $e) {
            return [];
        }

        $ranges = $e > $s ? [[$s, $e]] : [[$s, 24.0], [0.0, $e]];

        $out = [];
        foreach (self::PERIODS as $p => [$ps, $pe]) {
            foreach ($ranges as [$rs, $re]) {
                if ($rs < $pe && $re > $ps) {
                    $out[$p] = true;
                    break;
                }
            }
        }

        return array_keys($out);
    }

    private function hours(string $time): float
    {
        [$h, $m] = array_pad(explode(':', $time), 2, '0');

        return (int) $h + ((int) $m) / 60;
    }
}
