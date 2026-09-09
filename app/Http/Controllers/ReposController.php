<?php

namespace App\Http\Controllers;

use App\Services\ReposSuggestionService;
use App\Services\SectionScopeService;
use App\Services\WorkAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Repos (régime de travail mixte) — a monthly personnel × days grid where each
 * cell offers a "Jour" and a "Nuit" half-day rest toggle. Rest periods are
 * stored as `indisponibilite` rows with TI_CODE = 'RT' (auto-validated) and, via
 * {@see WorkAvailabilityService}, clear the person's overlapping availability.
 *
 * Managers (permission 10) see everyone in their section scope; everyone else
 * sees only their own row. A configurable engine can pre-fill suggested rest
 * from gardes, worked hours and a monthly minimum ({@see ReposSuggestionService}).
 */
class ReposController extends Controller
{
    private const TYPE = 'RT';

    private const PERIOD_DAY = 2;   // I_TYPE_PERIODE for the daytime half

    private const PERIOD_NIGHT = 3; // I_TYPE_PERIODE for the night half

    public function __construct(
        private readonly SectionScopeService $scope,
        private readonly WorkAvailabilityService $coherence,
        private readonly ReposSuggestionService $suggestions,
    ) {}

    public function index(Request $request): View
    {
        return view('repos.index', $this->board($request));
    }

    /** Replace the RT rest rows of every editable person for the month. */
    public function save(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'integer'],
            'persons' => ['required', 'array'],
            'persons.*' => ['integer'],
            'jour' => ['nullable', 'array'],
            'nuit' => ['nullable', 'array'],
        ]);

        $first = now()->startOfMonth()->addMonths((int) $validated['month']);
        $last = $first->copy()->endOfMonth();
        $editableIds = $this->visiblePersonnel($request)
            ->filter(fn ($p) => $this->mayEdit($p))
            ->pluck('P_ID')->map(fn ($id) => (int) $id)->all();

        DB::transaction(function () use ($validated, $first, $last, $editableIds): void {
            foreach ($validated['persons'] as $pid) {
                $pid = (int) $pid;
                if (! in_array($pid, $editableIds, true)) {
                    continue;
                }

                DB::table('indisponibilite')
                    ->where('P_ID', $pid)
                    ->where('TI_CODE', self::TYPE)
                    ->whereBetween('I_DEBUT', [$first->toDateString(), $last->toDateString()])
                    ->delete();

                $rows = [];
                foreach ($this->cleanDates($validated['jour'][$pid] ?? [], $first, $last) as $date) {
                    $rows[] = $this->restRow($pid, $date, self::PERIOD_DAY);
                }
                foreach ($this->cleanDates($validated['nuit'][$pid] ?? [], $first, $last) as $date) {
                    $rows[] = $this->restRow($pid, $date, self::PERIOD_NIGHT);
                }

                if (! empty($rows)) {
                    DB::table('indisponibilite')->insert($rows);
                    foreach ($rows as $row) {
                        $this->coherence->syncAbsence((object) $row);
                    }
                }
            }
        });

        return redirect()
            ->route('repos.index', ['month' => (int) $validated['month']])
            ->with('status', __('repos.saved'));
    }

    /**
     * Shared board data: scoped personnel, the day columns and each person's
     * Jour/Nuit rest per day (with the optional suggestion overlay).
     *
     * @return array<string,mixed>
     */
    private function board(Request $request): array
    {
        $month = (int) $request->integer('month', 0);
        $first = now()->startOfMonth()->addMonths($month);
        $last = $first->copy()->endOfMonth();

        $personnel = $this->visiblePersonnel($request)->values();
        $pids = $personnel->pluck('P_ID')->map(fn ($id) => (int) $id)->all();

        // Existing RT rest per person/day.
        $rest = [];
        if (! empty($pids)) {
            foreach (DB::table('indisponibilite')
                ->whereIn('P_ID', $pids)
                ->where('TI_CODE', self::TYPE)
                ->whereBetween('I_DEBUT', [$first->toDateString(), $last->toDateString()])
                ->get(['P_ID', 'I_DEBUT', 'I_TYPE_PERIODE']) as $r) {
                $key = Carbon::parse($r->I_DEBUT)->format('Y-m-d');
                $half = (int) $r->I_TYPE_PERIODE === self::PERIOD_NIGHT ? 'nuit' : 'jour';
                $rest[(int) $r->P_ID][$key][$half] = true;
            }
        }

        $days = $this->buildDays($first, $last);

        $suggesting = $request->boolean('suggest') && $this->suggestions->enabled();

        // Build one row per person: name, editability and per-day cells.
        $rows = $personnel->map(function ($p) use ($rest, $days, $suggesting, $first, $last) {
            $pid = (int) $p->P_ID;
            $editable = $this->mayEdit($p);
            $suggested = ($suggesting && $editable)
                ? $this->suggestions->suggest($pid, $first, $last)
                : [];

            $cells = [];
            foreach ($days as $day) {
                $key = $day['key'];
                $jour = $rest[$pid][$key]['jour'] ?? false;
                $nuit = $rest[$pid][$key]['nuit'] ?? false;
                $sJour = ! $jour && ($suggested[$key]['jour'] ?? false);
                $sNuit = ! $nuit && ($suggested[$key]['nuit'] ?? false);
                $cells[$key] = [
                    'jour' => $jour || $sJour,
                    'nuit' => $nuit || $sNuit,
                    'jourSuggested' => $sJour,
                    'nuitSuggested' => $sNuit,
                ];
            }

            return (object) [
                'P_ID' => $pid,
                'name' => strtoupper($p->P_NOM).' '.$p->P_PRENOM,
                'editable' => $editable,
                'cells' => $cells,
            ];
        });

        return [
            'rows' => $rows,
            'days' => $days,
            'first' => $first,
            'month' => $month,
            'prevMonth' => $month - 1,
            'nextMonth' => $month + 1,
            'canEditAny' => $rows->contains(fn ($r) => $r->editable),
            'sectionId' => $this->scope->sectionFilter($request),
            'canSeeOthers' => (bool) auth()->user()->hasPermission(10),
            'autoEnabled' => $this->suggestions->enabled(),
            'suggesting' => $suggesting,
        ];
    }

    /** Personnel the viewer may see: section scope with permission 10, else self. */
    private function visiblePersonnel(Request $request): Collection
    {
        $user = auth()->user();

        if (! $user->hasPermission(10)) {
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

    private function mayEdit(object $person): bool
    {
        $user = auth()->user();

        return (int) $person->P_ID === (int) $user->P_ID
            || $user->hasPermissionInSection(10, $person->P_SECTION !== null ? (int) $person->P_SECTION : null);
    }

    /**
     * Calendar cells for the month.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildDays(Carbon $first, Carbon $last): array
    {
        $days = [];
        for ($d = $first->copy(); $d->lte($last); $d->addDay()) {
            $days[] = [
                'key' => $d->format('Y-m-d'),
                'day' => (int) $d->day,
                'weekday' => ucfirst($d->locale('fr')->isoFormat('dd')),
                'isWeekend' => $d->isWeekend(),
                'isToday' => $d->isToday(),
            ];
        }

        return $days;
    }

    /**
     * @param  array<int, string>  $dates
     * @return array<int, string>
     */
    private function cleanDates(array $dates, Carbon $first, Carbon $last): array
    {
        $out = [];
        foreach ($dates as $d) {
            $date = Carbon::parse($d);
            if ($date->betweenIncluded($first, $last)) {
                $out[$date->toDateString()] = true;
            }
        }

        return array_keys($out);
    }

    /** @return array<string,mixed> */
    private function restRow(int $personId, string $date, int $period): array
    {
        $night = $period === self::PERIOD_NIGHT;

        return [
            'P_ID' => $personId,
            'TI_CODE' => self::TYPE,
            'I_STATUS' => 'VAL',
            'I_DEBUT' => $date,
            'I_FIN' => $date,
            'I_COMMENT' => $night ? 'Demi repos de Nuit' : 'Demi repos de Jour',
            'IH_DEBUT' => $night ? '20:00:00' : '08:00:00',
            'IH_FIN' => $night ? '08:00:00' : '12:00:00',
            'I_JOUR_COMPLET' => 2,
            'I_TYPE_PERIODE' => $period,
            'I_ACCEPT' => now(),
            'I_STATUS_BY' => (int) auth()->user()->P_ID,
        ];
    }
}
