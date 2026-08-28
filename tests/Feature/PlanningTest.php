<?php

use App\Http\Controllers\PlanningController;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Mockery\MockInterface;

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Stub NavigationService so the layout's view composer never hits the DB.
 */
function planningStubNav(): void
{
    $nav = Mockery::mock(NavigationService::class);
    $nav->shouldReceive('getNavGroups')->andReturn([]);
    $nav->shouldReceive('getPinnedShortcuts')->andReturn([]);
    app()->instance(NavigationService::class, $nav);
}

/**
 * Build a minimal fake User (no DB required). hasPermission() returns false so
 * Blade templates render without touching the database.
 */
function planningFakeUser(array $attrs = []): User
{
    /** @var User&MockInterface $user */
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(array_merge([
        'P_ID' => 1,
        'P_NOM' => 'Test',
        'P_PRENOM' => 'User',
        'P_SECTION' => 1,
        'P_ACTIF' => 1,
        'P_MDP' => bcrypt('secret'),
    ], $attrs));
    $user->shouldReceive('hasPermission')->andReturn(false);

    return $user;
}

beforeEach(function () {
    planningStubNav();
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

// ── Access control ───────────────────────────────────────────────────────────

test('unauthenticated users are redirected from /planning to login', function () {
    $this->get('/calendar')->assertRedirect('/login');
});

// ── Legacy bridge redirects ──────────────────────────────────────────────────

test('legacy calendar.php redirects to planning.index', function () {
    $this->actingAs(planningFakeUser())
        ->get('/legacy/calendar.php')
        ->assertRedirect(route('planning.index'));
});

test('legacy myagenda.php redirects to planning.index', function () {
    $this->actingAs(planningFakeUser())
        ->get('/legacy/myagenda.php')
        ->assertRedirect(route('planning.index'));
});

// ── Planning calendar (index renders the calendar + person filter) ───────────

/** Bind PlanningController so index() renders the view DB-free. */
function planningStubIndexView(): void
{
    app()->bind(PlanningController::class, function () {
        $ctrl = Mockery::mock(PlanningController::class)->makePartial();
        $person = (object) ['P_ID' => 1, 'P_NOM' => 'Test', 'P_PRENOM' => 'User', 'P_SECTION' => 1, 'color' => '#2563eb'];
        $ctrl->shouldReceive('index')->andReturn(
            view('planning.index', ['personnel' => collect([$person]), 'sectionId' => null, 'canSeeOthers' => false])
        );

        return $ctrl;
    });
}

test('authenticated users can access the planning', function () {
    planningStubIndexView();
    $this->actingAs(planningFakeUser())->get('/calendar')->assertStatus(200);
});

test('planning index renders the calendar container', function () {
    planningStubIndexView();
    $this->actingAs(planningFakeUser())->get('/calendar')
        ->assertViewIs('planning.index')
        ->assertSee('data-ob-section-planning', false);
});

// ── Planning events feed (JSON) ──────────────────────────────────────────────

test('the planning events route is registered', function () {
    expect(route('planning.events'))->toContain('/calendar/events');
});

test('unauthenticated users are redirected from the planning events feed to login', function () {
    $this->get('/calendar/events')->assertRedirect('/login');
});

test('the events feed returns JSON', function () {
    // Stub the controller so the feed renders without a database.
    app()->bind(PlanningController::class, function () {
        $ctrl = Mockery::mock(PlanningController::class)->makePartial();
        $ctrl->shouldReceive('events')->andReturn(response()->json([
            ['title' => 'Formation PSC1', 'start' => '2026-08-10', 'url' => '/events/EVT1'],
        ]));

        return $ctrl;
    });

    $this->actingAs(planningFakeUser())->get('/calendar/events')
        ->assertStatus(200)
        ->assertJsonFragment(['title' => 'Formation PSC1']);
});

// ── Planning print/PDF export (stubbed controller) ───────────────────────────

/** Bind PlanningController so print() renders the real print view DB-free. */
function planningStubPrint(User $user): void
{
    $now = now();
    app()->bind(PlanningController::class, function () use ($now, $user) {
        $ctrl = Mockery::mock(PlanningController::class)->makePartial();
        $event = (object) [
            'E_CODE' => 'EVT1', 'E_LIBELLE' => 'Formation PSC1', 'E_CLOSED' => 0,
            'TE_LIBELLE' => 'Formation', 'event_date' => $now->copy()->startOfMonth()->toDateString(),
            'event_time' => '09:00',
        ];
        $absence = (object) [
            'I_DEBUT' => $now->copy()->startOfMonth()->addDays(4)->toDateString(),
            'I_FIN' => $now->copy()->startOfMonth()->addDays(6)->toDateString(),
            'I_ACCEPT' => 1, 'I_COMMENT' => 'Congés', 'TI_LIBELLE' => 'Vacances',
        ];
        $ctrl->shouldReceive('print')->andReturn(
            view('planning.print', [
                'people' => collect([[
                    'name' => strtoupper($user->P_NOM).' '.$user->P_PRENOM,
                    'events' => collect([$event]),
                    'absences' => collect([$absence]),
                ]]),
                'first' => $now->copy()->startOfMonth(),
                'year' => $now->year,
                'month' => $now->month,
            ])
        );

        return $ctrl;
    });
}

test('the planning print route is registered', function () {
    expect(route('planning.print'))->toContain('/calendar/print');
});

test('unauthenticated users are redirected from the planning print to login', function () {
    $this->get('/calendar/print')->assertRedirect('/login');
});

test('authenticated users can view the printable planning', function () {
    $user = planningFakeUser(['P_NOM' => 'Durand', 'P_PRENOM' => 'Paul']);
    planningStubPrint($user);

    $this->actingAs($user)->get('/calendar/print')
        ->assertStatus(200)
        ->assertViewIs('planning.print')
        ->assertSee('DURAND Paul')
        ->assertSee(__('planning.print_section_events'))
        ->assertSee('Formation PSC1')
        ->assertSee(__('planning.print_section_absences'))
        ->assertSee('Vacances');
});
