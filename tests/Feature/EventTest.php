<?php

use App\Http\Controllers\EventController;
use App\Models\Event;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Mockery\MockInterface;

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Stub NavigationService so the layout's view composer never hits the DB.
 */
function eventStubNav(): void
{
    $nav = Mockery::mock(NavigationService::class);
    $nav->shouldReceive('getNavGroups')->andReturn([]);
    $nav->shouldReceive('getPinnedShortcuts')->andReturn([]);
    app()->instance(NavigationService::class, $nav);
}

/**
 * Build a minimal fake User (no DB required). hasPermission() returns true so
 * permission-gated event actions are reachable.
 */
function eventFakeUser(array $attrs = []): User
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
    $user->shouldReceive('hasPermission')->andReturn(true);

    return $user;
}

/**
 * Bind EventController so index() returns the real view rendered with stub
 * data, keeping the assertion at the HTTP/view level without touching the DB.
 */
function eventStubIndex(): void
{
    app()->bind(EventController::class, function () {
        $ctrl = Mockery::mock(EventController::class)->makePartial();
        $page = new LengthAwarePaginator([], 0, 50);
        $page->setPath('/events');
        $empty = Collection::make([]);
        $ctrl->shouldReceive('index')->andReturn(
            view('event.index', [
                'items' => $page,
                'columns' => [],
                'period' => 'upcoming',
                'search' => '',
                'type' => 'ALL',
                'filtSect' => 0,
                'types' => $empty,
                'sections' => $empty,
            ])
        );

        return $ctrl;
    });
}

beforeEach(function () {
    eventStubNav();
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

// ── Access control ───────────────────────────────────────────────────────────

test('unauthenticated users are redirected from /events to login', function () {
    $this->get('/events')->assertRedirect('/login');
});

test('unauthenticated users are redirected from event detail to login', function () {
    $this->get('/events/EVT001')->assertRedirect('/login');
});

test('unauthenticated users are redirected from the event list exports to login', function () {
    $this->get('/events/export/xls')->assertRedirect('/login');
    $this->get('/events/export/csv')->assertRedirect('/login');
});

test('the event list export routes are registered before the {code} wildcard', function () {
    expect(route('event.export.xls'))->toContain('/events/export/xls');
    expect(route('event.export.csv'))->toContain('/events/export/csv');
});

test('unauthenticated users are redirected from the per-event vehicle export to login', function () {
    $this->get('/events/EVT001/export/vehicles')->assertRedirect('/login');
});

test('the per-event vehicle export route is registered', function () {
    expect(route('event.export.vehicles', 'EVT001'))->toContain('/events/EVT001/export/vehicles');
});

// ── Legacy bridge redirects ──────────────────────────────────────────────────

test('legacy evenements.php redirects to event.index', function () {
    $this->actingAs(eventFakeUser())
        ->get('/legacy/evenements.php')
        ->assertRedirect(route('event.index'));
});

test('legacy evenement_display.php redirects to event.index', function () {
    $this->actingAs(eventFakeUser())
        ->get('/legacy/evenement_display.php')
        ->assertRedirect(route('event.index'));
});

test('legacy evenement_detail.php with evenement param redirects to show', function () {
    $this->actingAs(eventFakeUser())
        ->get('/legacy/evenement_detail.php?evenement=EVT042')
        ->assertRedirect(route('event.show', 'EVT042'));
});

test('legacy evenement_detail.php without param redirects to index', function () {
    $this->actingAs(eventFakeUser())
        ->get('/legacy/evenement_detail.php')
        ->assertRedirect(route('event.index'));
});

// ── Event index (stubbed controller) ─────────────────────────────────────

test('authenticated users can access the event list', function () {
    eventStubIndex();
    $this->actingAs(eventFakeUser())->get('/events')->assertStatus(200);
});

test('event list renders the event.index view', function () {
    eventStubIndex();
    $this->actingAs(eventFakeUser())->get('/events')->assertViewIs('event.index');
});

test('event list passes all required view variables', function () {
    eventStubIndex();
    $this->actingAs(eventFakeUser())->get('/events')
        ->assertViewHasAll(['items', 'period', 'search', 'type', 'filtSect', 'types', 'sections']);
});

// ── Event detail — status flag badges (stubbed controller) ────────────────

/**
 * Render the real event.show view with an in-memory Event carrying the given
 * attributes and empty relations/collections — no database access.
 */
function eventStubShow(array $eventAttrs): void
{
    app()->bind(EventController::class, function () use ($eventAttrs) {
        $ctrl = Mockery::mock(EventController::class)->makePartial();

        $event = (new Event)->forceFill(array_merge([
            'E_CODE' => 'EVT001',
            'E_LIBELLE' => 'Activité test',
            'E_CANCELED' => 0,
            'E_CLOSED' => 0,
            'E_OPEN_TO_EXT' => 0,
            'E_VISIBLE_OUTSIDE' => 0,
            'E_EXTERIEUR' => 0,
            'E_VISIBLE_INSIDE' => 1,
            'E_ALLOW_REINFORCEMENT' => 0,
        ], $eventAttrs));
        $event->setRelation('horaires', Collection::make([]));
        $event->setRelation('chef', null);
        $event->setRelation('section', null);

        $empty = Collection::make([]);
        $ctrl->shouldReceive('show')->andReturn(
            view('event.show', [
                'event' => $event,
                'typeLabel' => 'Type',
                'participants' => $empty,
                'candidates' => $empty,
                'vehicules' => $empty,
                'allVehicles' => $empty,
                'functions' => $empty,
                'equipes' => $empty,
                'renforts' => $empty,
                'materiels' => $empty,
                'allMateriels' => $empty,
                'requiredPositions' => $empty,
                'availablePositions' => $empty,
                'activeCount' => 0,
                'renfortRequest' => null,
                'renfortVehicleTypes' => $empty,
                'renfortMaterials' => $empty,
                'renfortCandidates' => ['renforts' => $empty, 'others' => $empty],
                'optionGroups' => $empty,
                'eventOptions' => $empty,
                'eventLog' => $empty,
                'logTypes' => $empty,
            ])
        );

        return $ctrl;
    });
}

test('event detail surfaces the four informational status flag badges when set', function () {
    eventStubShow([
        'E_OPEN_TO_EXT' => 1,
        'E_VISIBLE_OUTSIDE' => 1,
        'E_EXTERIEUR' => 1,
        'E_VISIBLE_INSIDE' => 0,
    ]);

    $this->actingAs(eventFakeUser())->get('/events/EVT001')
        ->assertStatus(200)
        ->assertSee(__('event.flag_open_to_ext'))
        ->assertSee(__('event.flag_visible_outside'))
        ->assertSee(__('event.flag_exterieur'))
        ->assertSee(__('event.flag_hidden'));
});

test('event detail hides the informational status flag badges when unset', function () {
    eventStubShow([]); // all flags off, E_VISIBLE_INSIDE = 1 (not hidden)

    $this->actingAs(eventFakeUser())->get('/events/EVT001')
        ->assertStatus(200)
        ->assertDontSee(__('event.flag_open_to_ext'))
        ->assertDontSee(__('event.flag_visible_outside'))
        ->assertDontSee(__('event.flag_exterieur'))
        ->assertDontSee(__('event.flag_hidden'));
});

// ── Event creation — schedule date validation ─────────────────────────────

test('a schedule end date before its start date is rejected with a friendly message', function () {
    // Validation fails before any DB write, so no database is touched.
    $this->actingAs(eventFakeUser())
        ->from('/events/create')
        ->post('/events', [
            'TE_CODE' => 'FOR',
            'E_LIBELLE' => 'Test',
            'S_ID' => 1,
            'horaires' => [
                ['EH_DATE_DEBUT' => '2026-01-10', 'EH_DATE_FIN' => '2026-01-05'],
            ],
        ])
        ->assertRedirect('/events/create')
        ->assertSessionHasErrors([
            'horaires.0.EH_DATE_FIN' => __('event.validation_date_fin_after'),
        ]);
});

// ── Event report (evenement_rapport.php) ──────────────────────────────────

/**
 * Render the real event.report view with an in-memory event and empty
 * collections — no database access.
 */
function eventStubReport(): void
{
    app()->bind(EventController::class, function () {
        $ctrl = Mockery::mock(EventController::class)->makePartial();

        $event = (new Event)->forceFill([
            'E_CODE' => 'EVT001',
            'E_LIBELLE' => 'Gala annuel',
            'TE_CODE' => 'MAN',
            'E_TEL' => '0102030405',
        ]);
        $event->setRelation('type', null);
        $event->setRelation('chef', null);
        $event->setRelation('horaires', Collection::make([]));

        $empty = Collection::make([]);
        $ctrl->shouldReceive('report')->andReturn(
            view('event.report', [
                'event' => $event,
                'vehicules' => $empty,
                'materiels' => $empty,
                'eventLog' => $empty,
                'figures' => [
                    'participants' => 12, 'vehicules' => 0, 'materiels' => 0,
                    'messages' => 0, 'interventions' => 0,
                ],
            ])
        );

        return $ctrl;
    });
}

test('unauthenticated users are redirected from the event report to login', function () {
    $this->get('/events/EVT001/report')->assertRedirect('/login');
});

test('the event report route is registered', function () {
    expect(route('event.report', 'EVT001'))->toContain('/events/EVT001/report');
});

test('legacy evenement_rapport.php redirects to the native report', function () {
    $this->actingAs(eventFakeUser())
        ->get('/legacy/evenement_rapport.php?evenement=EVT042')
        ->assertRedirect(route('event.report', 'EVT042'));
});

test('authenticated users can view the event report', function () {
    eventStubReport();

    $this->actingAs(eventFakeUser())->get('/events/EVT001/report')
        ->assertStatus(200)
        ->assertViewIs('event.report')
        ->assertSee('Gala annuel')
        ->assertSee(__('event.report_section_responsable'))
        ->assertSee(__('event.report_section_log'));
});

test('the event report passes the expected view data', function () {
    eventStubReport();

    $this->actingAs(eventFakeUser())->get('/events/EVT001/report')
        ->assertViewHasAll(['event', 'vehicules', 'materiels', 'eventLog', 'figures']);
});

// ── Reinforcement request transmission (#24) ─────────────────────────────────

test('the reinforcement transmission route is registered', function () {
    expect(route('event.renfort-request.transmit', 1))->toContain('/events/1/renfort-request/transmit');
});

test('unauthenticated users cannot transmit a reinforcement request', function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
    $this->post('/events/1/renfort-request/transmit')->assertRedirect('/login');
});
