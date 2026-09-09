<?php

namespace App\Http\Controllers;

use App\Services\SectionScopeService;
use App\Services\WorkAvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Absences / indisponibilités. Members declare their own absences (permission
 * 11); holders of permission 12 declare for others in their section and run the
 * validation circuit. Types flagged `TI_FLAG = 1` ("congés avec circuit de
 * validation") are created pending (I_STATUS = ATT) and must be accepted;
 * every other type is auto-validated (VAL). Stored in `indisponibilite`.
 */
class UnavailabilityController extends Controller
{
    /** Volunteer statuses barred from validation-circuit (paid-leave) types. */
    private const VOLUNTEER_STATUTES = ['SPV', 'BEN', 'ADH', 'JSP'];

    public function __construct(
        private readonly SectionScopeService $scope,
        private readonly WorkAvailabilityService $coherence,
    ) {}

    /**
     * Availability / absences list for the section.
     */
    public function index(Request $request): View
    {
        $user = auth()->user();
        $sectionId = (int) $user->P_SECTION;
        $pid = (int) $user->P_ID;

        $tab = (string) $request->string('tab', 'section'); // section | mine
        $status = (string) $request->string('status', 'pending'); // pending | accepted | all

        $query = DB::table('indisponibilite as i')
            ->leftJoin('pompier as p', 'i.P_ID', '=', 'p.P_ID')
            ->leftJoin('type_indisponibilite as ti', 'i.TI_CODE', '=', 'ti.TI_CODE')
            ->whereNull('i.I_CANCEL')
            ->select(
                'i.I_CODE', 'i.P_ID', 'i.I_DEBUT', 'i.I_FIN',
                'i.I_ACCEPT', 'i.I_STATUS', 'i.I_COMMENT', 'i.I_JOUR_COMPLET',
                'ti.TI_LIBELLE', 'p.P_SECTION',
                DB::raw("CONCAT(p.P_PRENOM, ' ', p.P_NOM) as person_name")
            )
            ->orderByDesc('i.I_DEBUT');

        if ($tab === 'mine') {
            $query->where('i.P_ID', $pid);
        } else {
            $query->where('p.P_SECTION', $sectionId);
        }

        match ($status) {
            'pending' => $query->where('i.I_STATUS', 'ATT'),
            'accepted' => $query->where('i.I_STATUS', 'VAL'),
            default => null,
        };

        // Only current/future absences.
        $query->where(function ($q) {
            $q->where('i.I_FIN', '>=', now()->toDateString())
                ->orWhereNull('i.I_FIN');
        });

        $canManage = (bool) $user->hasPermission(12);
        $items = $query->paginate(30)->withQueryString();

        return view('unavailability.index', compact('items', 'tab', 'status', 'canManage')
            + ['columns' => $this->indispoColumns($canManage, $pid)]);
    }

    /** Absence declaration form. */
    public function create(Request $request): View
    {
        $types = DB::table('type_indisponibilite')
            ->orderBy('TI_LIBELLE')
            ->get(['TI_CODE', 'TI_LIBELLE', 'TI_FLAG']);

        $canManage = (bool) auth()->user()->hasPermission(12);
        $personnel = $canManage ? $this->visiblePersonnel($request) : collect();

        return view('unavailability.create', compact('types', 'personnel', 'canManage'));
    }

    /** Persist a declared absence, applying the validation-circuit rules. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'person' => ['nullable', 'integer'],
            'type' => ['required', 'string', 'exists:type_indisponibilite,TI_CODE'],
            'debut' => ['required', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:debut'],
            'scope' => ['required', 'in:full,morning,afternoon,hours'],
            'heure_debut' => ['nullable', 'date_format:H:i', 'required_if:scope,hours'],
            'heure_fin' => ['nullable', 'date_format:H:i', 'required_if:scope,hours', 'after:heure_debut'],
            'comment' => ['nullable', 'string', 'max:50'],
        ]);

        $self = (int) auth()->user()->P_ID;
        $personId = (int) ($validated['person'] ?? 0) ?: $self;

        $person = $this->requireDeclarablePerson($personId);

        $type = DB::table('type_indisponibilite')->where('TI_CODE', $validated['type'])->first();
        $needsValidation = (int) ($type->TI_FLAG ?? 0) === 1;

        // Paid-leave (circuit) types are not available to volunteer statuses.
        if ($needsValidation && in_array($person->P_STATUT, self::VOLUNTEER_STATUTES, true)) {
            return back()->withInput()->withErrors([
                'type' => __('unavailability.error_circuit_volunteer'),
            ]);
        }

        // Map the scope to the legacy day/period shape.
        [$jourComplet, $typePeriode, $hDebut, $hFin] = match ($validated['scope']) {
            'morning' => [2, 2, '08:00:00', '12:00:00'],
            'afternoon' => [2, 3, '14:00:00', '18:00:00'],
            'hours' => [0, 1, $validated['heure_debut'].':00', $validated['heure_fin'].':00'],
            default => [1, 1, '08:00:00', '19:00:00'],
        };

        $debut = Carbon::parse($validated['debut'])->toDateString();
        // Half-day / hours are single-day; full-day spans to `fin` (default same day).
        $fin = $jourComplet === 1
            ? Carbon::parse($validated['fin'] ?: $validated['debut'])->toDateString()
            : $debut;

        $status = $needsValidation ? 'ATT' : 'VAL';

        $row = [
            'P_ID' => $personId,
            'TI_CODE' => $validated['type'],
            'I_STATUS' => $status,
            'I_DEBUT' => $debut,
            'I_FIN' => $fin,
            'I_COMMENT' => $validated['comment'] ?? '',
            'IH_DEBUT' => $hDebut,
            'IH_FIN' => $hFin,
            'I_JOUR_COMPLET' => $jourComplet,
            'I_TYPE_PERIODE' => $typePeriode,
            'I_ACCEPT' => $status === 'VAL' ? now() : null,
            'I_STATUS_BY' => $status === 'VAL' ? $self : null,
        ];
        DB::table('indisponibilite')->insert($row);

        // Auto-validated absences immediately clear any overlapping availability.
        if ($status === 'VAL') {
            $this->coherence->syncAbsence((object) $row);
        }

        return redirect()
            ->route('unavailability.index', ['tab' => $personId === $self ? 'mine' : 'section'])
            ->with('status', __('unavailability.saved'));
    }

    /** Accept or reject a pending absence (permission 12). */
    public function decide(Request $request, int $code): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:accept,reject'],
        ]);

        $absence = $this->findManageable($code);

        if ($validated['decision'] === 'accept') {
            DB::table('indisponibilite')->where('I_CODE', $code)->update([
                'I_STATUS' => 'VAL',
                'I_ACCEPT' => now(),
                'I_STATUS_BY' => (int) auth()->user()->P_ID,
            ]);
            $this->markParticipationsAbsent($absence);
            $this->coherence->syncAbsence($absence);
        } else {
            DB::table('indisponibilite')->where('I_CODE', $code)->update([
                'I_STATUS' => 'REF',
                'I_ACCEPT' => null,
                'I_STATUS_BY' => (int) auth()->user()->P_ID,
            ]);
        }

        return back()->with('status', __('unavailability.'.($validated['decision'] === 'accept' ? 'accepted' : 'rejected')));
    }

    /** Cancel an absence (own, or any in section with permission 12). */
    public function cancel(Request $request, int $code): RedirectResponse
    {
        $absence = DB::table('indisponibilite as i')
            ->leftJoin('pompier as p', 'i.P_ID', '=', 'p.P_ID')
            ->where('i.I_CODE', $code)
            ->select('i.*', 'p.P_SECTION')
            ->first();
        abort_if($absence === null, 404);

        $self = (int) auth()->user()->P_ID;
        $isSelf = (int) $absence->P_ID === $self;
        $canManage = auth()->user()->hasPermissionInSection(12, $absence->P_SECTION !== null ? (int) $absence->P_SECTION : null);
        abort_unless($isSelf || $canManage, 403);

        DB::table('indisponibilite')->where('I_CODE', $code)->update([
            'I_CANCEL' => now(),
            'I_STATUS' => 'ANN',
        ]);

        return back()->with('status', __('unavailability.cancelled'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Personnel the viewer may declare for (permission-12 section scope). */
    private function visiblePersonnel(Request $request): Collection
    {
        $query = DB::table('pompier as p')
            ->where('p.P_OLD_MEMBER', 0)
            ->whereNull('p.P_FIN')
            ->orderBy('p.P_NOM')
            ->orderBy('p.P_PRENOM')
            ->select('p.P_ID', 'p.P_NOM', 'p.P_PRENOM', 'p.P_SECTION');

        $this->scope->apply($query, 'p.P_SECTION', $this->scope->sectionFilter($request));

        return $query->get();
    }

    /** Guard + resolve a person the user may declare an absence for. */
    private function requireDeclarablePerson(int $personId): object
    {
        $person = DB::table('pompier')->where('P_ID', $personId)
            ->first(['P_ID', 'P_STATUT', 'P_SECTION']);
        abort_if($person === null, 404);

        $user = auth()->user();
        if ($personId === (int) $user->P_ID) {
            abort_unless($user->hasPermission(11), 403);
        } else {
            abort_unless($user->hasPermissionInSection(12, $person->P_SECTION !== null ? (int) $person->P_SECTION : null), 403);
        }

        return $person;
    }

    /** Fetch an absence the current user may validate; aborts otherwise. */
    private function findManageable(int $code): object
    {
        $absence = DB::table('indisponibilite as i')
            ->leftJoin('pompier as p', 'i.P_ID', '=', 'p.P_ID')
            ->where('i.I_CODE', $code)
            ->select('i.*', 'p.P_SECTION')
            ->first();
        abort_if($absence === null, 404);
        abort_unless(
            auth()->user()->hasPermissionInSection(12, $absence->P_SECTION !== null ? (int) $absence->P_SECTION : null),
            403
        );

        return $absence;
    }

    /**
     * When a full-day absence is accepted, excuse the person from any activities
     * they were signed up for within the absence window (mirrors the legacy
     * behaviour of clearing the duty board).
     */
    private function markParticipationsAbsent(object $absence): void
    {
        if ((int) $absence->I_JOUR_COMPLET !== 1) {
            return;
        }

        $codes = DB::table('evenement_horaire')
            ->whereBetween('EH_DATE_DEBUT', [$absence->I_DEBUT, $absence->I_FIN ?: $absence->I_DEBUT])
            ->where('EH_DATE_DEBUT', '>=', now()->toDateString())
            ->pluck('E_CODE');

        if ($codes->isEmpty()) {
            return;
        }

        DB::table('evenement_participation')
            ->where('P_ID', (int) $absence->P_ID)
            ->whereIn('E_CODE', $codes)
            ->update(['EP_ABSENT' => 1, 'EP_EXCUSE' => 1, 'EP_REMINDER' => 0]);
    }

    /** @return array<int, array<string, mixed>> */
    private function indispoColumns(bool $canManage, int $selfId): array
    {
        return [
            ['key' => 'personnel', 'label' => 'Personnel', 'type' => 'text', 'value' => fn ($i) => $i->person_name ?? '—', 'alwaysVisible' => true, 'mobile' => true],
            ['key' => 'type', 'label' => 'Type', 'type' => 'text', 'value' => fn ($i) => $i->TI_LIBELLE ?? '—', 'mobile' => false, 'exportable' => true, 'exportValue' => fn ($i) => $i->TI_LIBELLE ?? ''],
            ['key' => 'debut', 'label' => 'Début', 'type' => 'date', 'value' => fn ($i) => $i->I_DEBUT, 'alwaysVisible' => true, 'mobile' => true, 'exportable' => true, 'exportValue' => fn ($i) => $i->I_DEBUT ? Carbon::parse($i->I_DEBUT)->format('d/m/Y') : ''],
            ['key' => 'fin', 'label' => 'Fin', 'type' => 'date', 'value' => fn ($i) => $i->I_FIN, 'mobile' => false, 'exportable' => true, 'exportValue' => fn ($i) => $i->I_FIN ? Carbon::parse($i->I_FIN)->format('d/m/Y') : ''],
            ['key' => 'statut', 'label' => 'Statut', 'type' => 'badge', 'value' => fn ($i) => match ($i->I_STATUS) {
                'VAL' => 'ACCEPTED', 'REF' => 'REJECTED', 'ANN' => 'CANCELLED', default => 'PENDING',
            }, 'badgeMap' => [
                'ACCEPTED' => ['Acceptée', 'ob-badge-actif'],
                'REJECTED' => ['Refusée', 'ob-badge-bloqued'],
                'CANCELLED' => ['Annulée', 'ob-badge-archive'],
                'PENDING' => ['En attente', 'ob-badge-ben'],
            ], 'exportable' => true, 'exportValue' => fn ($i) => match ($i->I_STATUS) {
                'VAL' => 'Acceptée', 'REF' => 'Refusée', 'ANN' => 'Annulée', default => 'En attente',
            }, 'mobile' => true],
            ['key' => 'commentaire', 'label' => 'Commentaire', 'type' => 'text', 'value' => fn ($i) => $i->I_COMMENT ?: '', 'mobile' => false, 'default' => false, 'exportable' => true, 'exportValue' => fn ($i) => $i->I_COMMENT ?? ''],
            ['key' => 'actions', 'label' => '', 'type' => 'html', 'exportable' => false, 'alwaysVisible' => true, 'mobile' => true, 'value' => fn ($i) => view('unavailability._actions', [
                'i' => $i,
                'canManage' => $canManage,
                'isSelf' => (int) $i->P_ID === $selfId,
            ])->render()],
        ];
    }
}
