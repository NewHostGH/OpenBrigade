<?php

namespace App\Http\Controllers;

use App\Services\SectionScopeService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Horaires de travail — weekly timesheet for salaried staff (P_STATUT SAL/FONC).
 *
 * One navigable week (Monday–Sunday) is shown for a single selected person as
 * seven day-rows: two work slots (morning/afternoon), overtime, a computed
 * daily total and a free comment. Absences (accepted `indisponibilite`) are
 * surfaced read-only for context.
 *
 * Editing follows the legacy "syndicate = 0" rule: staff enter their own hours
 * while the week is open (status SEC/REJ/none), then submit it for validation.
 * Managers (permission 13 in the person's section, or permission 14) validate
 * or reject, and may edit at any time. Weeks are tracked in
 * `horaires_validation` against the `horaires_statut` lexicon.
 */
class TimesheetController extends Controller
{
    /** Ordered status flow for the validation workflow. */
    private const STATUS_OPEN = 'SEC';   // Saisie en cours

    private const STATUS_PENDING = 'ATTV'; // À valider

    private const STATUS_VALIDATED = 'VAL'; // Validés

    private const STATUS_REJECTED = 'REJ';  // Rejetés

    public function __construct(private readonly SectionScopeService $scope) {}

    public function index(Request $request): View
    {
        return view('timesheet.index', $this->board($request));
    }

    /** Print-optimised weekly timesheet (browser print → PDF). */
    public function print(Request $request): View
    {
        return view('timesheet.print', $this->board($request));
    }

    /**
     * Shared board data: the scoped salaried personnel, the selected person and
     * their week of entries, plus totals and the validation status.
     *
     * @return array<string,mixed>
     */
    private function board(Request $request): array
    {
        $week = (int) $request->integer('week', 0);
        $monday = now()->startOfWeek(Carbon::MONDAY)->addWeeks($week);
        $sunday = $monday->copy()->endOfWeek(Carbon::SUNDAY);

        $personnel = $this->visibleSalaried($request)->values();
        $person = $this->pickPerson($request, $personnel);
        $personId = $person ? (int) $person->P_ID : 0;

        // Existing entries for the week, keyed by Y-m-d.
        $rows = [];
        if ($personId) {
            foreach (DB::table('horaires')
                ->where('P_ID', $personId)
                ->whereBetween('H_DATE', [$monday->toDateString(), $sunday->toDateString()])
                ->get() as $r) {
                $rows[(string) $r->H_DATE] = $r;
            }
        }

        $absences = $this->absencesByDate($personId, $monday, $sunday);

        $days = [];
        $weekMinutes = 0;
        for ($d = $monday->copy(); $d->lte($sunday); $d->addDay()) {
            $key = $d->format('Y-m-d');
            $r = $rows[$key] ?? null;
            $total = $r ? (int) $r->H_DUREE_MINUTES : 0;
            $weekMinutes += $total;
            $days[] = [
                'key' => $key,
                'label' => ucfirst($d->locale('fr')->isoFormat('ddd D MMM')),
                'weekday' => ucfirst($d->locale('fr')->isoFormat('dddd')),
                'isWeekend' => $d->isWeekend(),
                'isToday' => $d->isToday(),
                'debut1' => $this->fmtTime($r->H_DEBUT1 ?? null),
                'fin1' => $this->fmtTime($r->H_FIN1 ?? null),
                'debut2' => $this->fmtTime($r->H_DEBUT2 ?? null),
                'fin2' => $this->fmtTime($r->H_FIN2 ?? null),
                'overtime' => $this->fmtDuration($r ? (int) $r->H_DUREE_MINUTES2 : 0),
                'total' => $this->fmtDuration($total),
                'comment' => $r ? (string) $r->H_COMMENT : '',
                'absence' => $absences[$key] ?? null,
            ];
        }

        $status = $personId ? $this->statusFor($personId, $monday) : self::STATUS_OPEN;
        $canManage = $person ? $this->canManage($person) : false;
        $isSelf = $personId === (int) auth()->user()->P_ID;

        return [
            'personnel' => $personnel,
            'person' => $person,
            'personId' => $personId,
            'days' => $days,
            'first' => $monday,
            'end' => $sunday,
            'week' => $week,
            'prevWeek' => $week - 1,
            'nextWeek' => $week + 1,
            'weekTotal' => $this->fmtDuration($weekMinutes),
            'monthTotal' => $this->fmtDuration($this->sumMinutes($personId, $monday->copy()->startOfMonth(), $monday->copy()->endOfMonth())),
            'yearTotal' => $this->fmtDuration($this->sumMinutes($personId, $monday->copy()->startOfYear(), $monday->copy()->endOfYear())),
            'status' => $status,
            'statusLabel' => $this->statusLabel($status),
            'statusClass' => $this->statusClass($status),
            'canManage' => $canManage,
            'isSelf' => $isSelf,
            'editable' => $this->isEditable($status, $isSelf, $canManage),
            'canSubmit' => $isSelf && in_array($status, [self::STATUS_OPEN, self::STATUS_REJECTED], true),
            'canDecide' => $canManage && ! $isSelf && $status === self::STATUS_PENDING,
            'canSeeOthers' => (bool) auth()->user()->hasPermission(13),
            'sectionId' => $this->scope->sectionFilter($request),
        ];
    }

    /** Persist the seven day-rows for one person's week. */
    public function save(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'person' => ['required', 'integer'],
            'week' => ['required', 'integer'],
            'days' => ['required', 'array'],
            'days.*.date' => ['required', 'date'],
            'days.*.debut1' => ['nullable', 'date_format:H:i'],
            'days.*.fin1' => ['nullable', 'date_format:H:i'],
            'days.*.debut2' => ['nullable', 'date_format:H:i'],
            'days.*.fin2' => ['nullable', 'date_format:H:i'],
            'days.*.overtime' => ['nullable', 'regex:/^\d{1,2}:[0-5]\d$/'],
            'days.*.comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $person = $this->requireEditablePerson((int) $validated['person']);

        foreach ($validated['days'] as $day) {
            $date = Carbon::parse($day['date'])->toDateString();
            $minutes1 = $this->slotMinutes($day['debut1'] ?? null, $day['fin1'] ?? null);
            $minutes2 = $this->slotMinutes($day['debut2'] ?? null, $day['fin2'] ?? null);
            $overtime = $this->parseDuration($day['overtime'] ?? null);
            $comment = trim((string) ($day['comment'] ?? ''));

            $hasData = ($day['debut1'] ?? '') !== '' || ($day['fin1'] ?? '') !== ''
                || ($day['debut2'] ?? '') !== '' || ($day['fin2'] ?? '') !== ''
                || $overtime > 0 || $comment !== '';

            $keys = ['P_ID' => (int) $person->P_ID, 'H_DATE' => $date];

            if (! $hasData) {
                DB::table('horaires')->where($keys)->delete();

                continue;
            }

            DB::table('horaires')->updateOrInsert($keys, [
                'H_DEBUT1' => $this->toSql($day['debut1'] ?? null),
                'H_FIN1' => $this->toSql($day['fin1'] ?? null),
                'H_DEBUT2' => $this->toSql($day['debut2'] ?? null),
                'H_FIN2' => $this->toSql($day['fin2'] ?? null),
                'H_DUREE_MINUTES' => $minutes1 + $minutes2 + $overtime,
                'H_DUREE_MINUTES2' => $overtime,
                'ASA' => 0,
                'H_COMMENT' => $comment !== '' ? $comment : null,
            ]);
        }

        return redirect()
            ->route('timesheet.index', ['person' => (int) $person->P_ID, 'week' => (int) $validated['week']])
            ->with('status', __('timesheet.saved'));
    }

    /** Staff submit their open week for validation (→ ATTV). */
    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'person' => ['required', 'integer'],
            'week' => ['required', 'integer'],
        ]);

        $personId = (int) $validated['person'];
        abort_unless($personId === (int) auth()->user()->P_ID, 403);

        $monday = now()->startOfWeek(Carbon::MONDAY)->addWeeks((int) $validated['week']);
        $current = $this->statusFor($personId, $monday);
        abort_unless(in_array($current, [self::STATUS_OPEN, self::STATUS_REJECTED], true), 403);

        $this->writeStatus($personId, $monday, self::STATUS_PENDING, decision: false);

        return back()->with('status', __('timesheet.submitted'));
    }

    /** Manager validates (VAL) or rejects (REJ) a pending week. */
    public function decide(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'person' => ['required', 'integer'],
            'week' => ['required', 'integer'],
            'decision' => ['required', 'in:validate,reject'],
        ]);

        $person = $this->visibleSalaried($request)->firstWhere('P_ID', (int) $validated['person']);
        abort_if($person === null, 404);
        abort_unless($this->canManage($person), 403);
        abort_if((int) $person->P_ID === (int) auth()->user()->P_ID, 403);

        $monday = now()->startOfWeek(Carbon::MONDAY)->addWeeks((int) $validated['week']);
        abort_unless($this->statusFor((int) $person->P_ID, $monday) === self::STATUS_PENDING, 403);

        $status = $validated['decision'] === 'validate' ? self::STATUS_VALIDATED : self::STATUS_REJECTED;
        $this->writeStatus((int) $person->P_ID, $monday, $status, decision: true);

        return back()->with('status', __('timesheet.'.($status === self::STATUS_VALIDATED ? 'validated' : 'rejected')));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Salaried personnel the viewer may see: their section scope if they hold
     * permission 13, otherwise just themselves (when salaried).
     */
    private function visibleSalaried(Request $request): Collection
    {
        $user = auth()->user();

        $query = DB::table('pompier as p')
            ->whereIn('p.P_STATUT', ['SAL', 'FONC'])
            ->where('p.P_OLD_MEMBER', 0)
            ->whereNull('p.P_FIN')
            ->orderBy('p.P_NOM')
            ->orderBy('p.P_PRENOM')
            ->select('p.P_ID', 'p.P_NOM', 'p.P_PRENOM', 'p.P_SECTION');

        if (! $user->hasPermission(13)) {
            $query->where('p.P_ID', (int) $user->P_ID);

            return $query->get();
        }

        $this->scope->apply($query, 'p.P_SECTION', $this->scope->sectionFilter($request));

        return $query->get();
    }

    /** Resolve the selected person: the request's, else self, else the first. */
    private function pickPerson(Request $request, Collection $personnel): ?object
    {
        if ($personnel->isEmpty()) {
            return null;
        }

        $requested = (int) $request->integer('person');
        $selfId = (int) auth()->user()->P_ID;

        return $personnel->firstWhere('P_ID', $requested)
            ?? $personnel->firstWhere('P_ID', $selfId)
            ?? $personnel->first();
    }

    /** Guard + resolve a person the current user may edit; aborts otherwise. */
    private function requireEditablePerson(int $personId): object
    {
        $person = $this->visibleSalaried(request())->firstWhere('P_ID', $personId);
        abort_if($person === null, 404);

        $status = $this->statusFor($personId, now()->startOfWeek(Carbon::MONDAY)->addWeeks((int) request()->integer('week')));
        $isSelf = $personId === (int) auth()->user()->P_ID;
        abort_unless($this->isEditable($status, $isSelf, $this->canManage($person)), 403);

        return $person;
    }

    private function canManage(object $person): bool
    {
        $user = auth()->user();

        return $user->hasPermission(14)
            || $user->hasPermissionInSection(13, $person->P_SECTION !== null ? (int) $person->P_SECTION : null);
    }

    private function isEditable(string $status, bool $isSelf, bool $canManage): bool
    {
        if ($canManage) {
            return true;
        }

        return $isSelf && in_array($status, [self::STATUS_OPEN, self::STATUS_REJECTED, ''], true);
    }

    /** Current validation status for a person's ISO week ('SEC' when none). */
    private function statusFor(int $personId, Carbon $monday): string
    {
        $code = DB::table('horaires_validation')
            ->where('P_ID', $personId)
            ->where('ANNEE', $monday->isoWeekYear)
            ->where('SEMAINE', $monday->isoWeek)
            ->value('HS_CODE');

        return $code ?: self::STATUS_OPEN;
    }

    private function writeStatus(int $personId, Carbon $monday, string $code, bool $decision): void
    {
        $now = now();
        $userId = (int) auth()->user()->P_ID;
        $keys = ['P_ID' => $personId, 'ANNEE' => $monday->isoWeekYear, 'SEMAINE' => $monday->isoWeek];

        $values = ['HS_CODE' => $code];
        if ($decision) {
            $values['STATUS_BY'] = $userId;
            $values['STATUS_DATE'] = $now;
        } else {
            $values['CREATED_BY'] = $userId;
            $values['CREATED_DATE'] = $now;
        }

        // CREATED_DATE is NOT NULL — seed it on first insert.
        if (! DB::table('horaires_validation')->where($keys)->exists()) {
            $values['CREATED_BY'] ??= $userId;
            $values['CREATED_DATE'] ??= $now;
        }

        DB::table('horaires_validation')->updateOrInsert($keys, $values);
    }

    /** @return array<string,string> Y-m-d => absence label (accepted only). */
    private function absencesByDate(int $personId, Carbon $from, Carbon $to): array
    {
        if (! $personId) {
            return [];
        }

        $map = [];
        $rows = DB::table('indisponibilite as i')
            ->leftJoin('type_indisponibilite as ti', 'i.TI_CODE', '=', 'ti.TI_CODE')
            ->where('i.P_ID', $personId)
            ->where('i.I_ACCEPT', 1)
            ->where('i.I_DEBUT', '<=', $to->toDateString())
            ->where('i.I_FIN', '>=', $from->toDateString())
            ->get(['i.I_DEBUT', 'i.I_FIN', 'ti.TI_LIBELLE']);

        foreach ($rows as $a) {
            $start = Carbon::parse($a->I_DEBUT)->max($from);
            $endAbs = Carbon::parse($a->I_FIN ?: $a->I_DEBUT)->min($to);
            for ($d = $start->copy(); $d->lte($endAbs); $d->addDay()) {
                $map[$d->format('Y-m-d')] = $a->TI_LIBELLE ?: __('timesheet.absence');
            }
        }

        return $map;
    }

    private function sumMinutes(int $personId, Carbon $from, Carbon $to): int
    {
        if (! $personId) {
            return 0;
        }

        return (int) DB::table('horaires')
            ->where('P_ID', $personId)
            ->whereBetween('H_DATE', [$from->toDateString(), $to->toDateString()])
            ->sum('H_DUREE_MINUTES');
    }

    /** Minutes between two "H:i" times on the same day (0 if incomplete). */
    private function slotMinutes(?string $start, ?string $end): int
    {
        if (! $start || ! $end) {
            return 0;
        }

        $diff = Carbon::createFromFormat('H:i', $start)->diffInMinutes(Carbon::createFromFormat('H:i', $end), false);

        return max(0, (int) $diff);
    }

    private function parseDuration(?string $value): int
    {
        if (! $value || ! preg_match('/^(\d{1,2}):([0-5]\d)$/', $value, $m)) {
            return 0;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    private function fmtDuration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0:00';
        }

        return intdiv($minutes, 60).':'.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT);
    }

    /** DB TIME ("HH:MM:SS", possibly "00:00:00") → editable "HH:MM" ('' when empty). */
    private function fmtTime(?string $time): string
    {
        if (! $time) {
            return '';
        }

        $hm = substr($time, 0, 5);

        return $hm === '00:00' ? '' : $hm;
    }

    private function toSql(?string $hm): ?string
    {
        return $hm ? $hm.':00' : null;
    }

    private function statusLabel(string $code): string
    {
        return match ($code) {
            self::STATUS_PENDING => __('timesheet.status_pending'),
            self::STATUS_VALIDATED => __('timesheet.status_validated'),
            self::STATUS_REJECTED => __('timesheet.status_rejected'),
            default => __('timesheet.status_open'),
        };
    }

    private function statusClass(string $code): string
    {
        return match ($code) {
            self::STATUS_PENDING => 'ob-ts-badge--pending',
            self::STATUS_VALIDATED => 'ob-ts-badge--validated',
            self::STATUS_REJECTED => 'ob-ts-badge--rejected',
            default => 'ob-ts-badge--open',
        };
    }
}
