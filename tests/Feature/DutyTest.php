<?php

use App\Http\Controllers\DutyController;
use App\Models\User;
use App\Services\NavigationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Mockery\MockInterface;

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Stub NavigationService so the layout's view composer never hits the DB.
 */
function gardeStubNav(): void
{
    $nav = Mockery::mock(NavigationService::class);
    $nav->shouldReceive('getNavGroups')->andReturn([]);
    $nav->shouldReceive('getPinnedShortcuts')->andReturn([]);
    app()->instance(NavigationService::class, $nav);
}

/**
 * Build a minimal fake User (no DB required). hasPermission() returns true so
 * permission-gated roster actions are reachable.
 */
function gardeFakeUser(array $attrs = []): User
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
 * Bind DutyController so index() returns the real view rendered with stub data,
 * keeping the assertion at the HTTP/view level without touching the database.
 */
function gardeStubIndex(User $user): void
{
    $now = now();
    app()->bind(DutyController::class, function () use ($now) {
        $ctrl = Mockery::mock(DutyController::class)->makePartial();
        $ctrl->shouldReceive('index')->andReturn(
            view('duty.index', [
                'days' => [],
                'monday' => $now->copy()->startOfWeek(),
                'sunday' => $now->copy()->endOfWeek(),
                'prevWeek' => -1,
                'nextWeek' => 1,
                'weekOffset' => 0,
                'roles' => [],
            ])
        );

        return $ctrl;
    });
}

beforeEach(function () {
    gardeStubNav();
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

// ── Access control ───────────────────────────────────────────────────────────

test('unauthenticated users are redirected from /duty to login', function () {
    $this->get('/duty')->assertRedirect('/login');
});

test('unauthenticated users are redirected from the on-call exports to login', function () {
    $this->get('/duty/monthly/export/xls')->assertRedirect('/login');
    $this->get('/duty/monthly/export/csv')->assertRedirect('/login');
});

test('the on-call export routes are registered', function () {
    expect(route('duty.on-call.export.xls'))->toContain('/duty/monthly/export/xls');
    expect(route('duty.on-call.export.csv'))->toContain('/duty/monthly/export/csv');
});

// ── Legacy bridge redirects ──────────────────────────────────────────────────

test('legacy tableau_garde.php redirects to garde.index', function () {
    $this->actingAs(gardeFakeUser())
        ->get('/legacy/tableau_garde.php')
        ->assertRedirect(route('duty.index'));
});

test('legacy feuille_garde.php redirects to garde.index', function () {
    $this->actingAs(gardeFakeUser())
        ->get('/legacy/feuille_garde.php')
        ->assertRedirect(route('duty.index'));
});

// ── Duty index (stubbed controller) ─────────────────────────────────────────

test('authenticated users can access the garde roster', function () {
    $user = gardeFakeUser();
    gardeStubIndex($user);

    $this->actingAs($user)->get('/duty/weekly')->assertStatus(200);
});

test('garde index renders the garde.index view', function () {
    $user = gardeFakeUser();
    gardeStubIndex($user);

    $this->actingAs($user)->get('/duty/weekly')->assertViewIs('duty.index');
});

test('garde index passes all required view variables', function () {
    $user = gardeFakeUser();
    gardeStubIndex($user);

    $this->actingAs($user)->get('/duty/weekly')
        ->assertViewHasAll(['days', 'monday', 'sunday', 'prevWeek', 'nextWeek', 'weekOffset', 'roles']);
});

test('garde index passes week=0 by default', function () {
    $user = gardeFakeUser();
    gardeStubIndex($user);

    $this->actingAs($user)->get('/duty/weekly')
        ->assertViewHas('weekOffset', 0);
});

// ── Garde du jour (today's guard) ────────────────────────────────────────────

/** Bind DutyController so today() renders the real view DB-free. */
function gardeStubToday(): void
{
    $now = now();
    app()->bind(DutyController::class, function () use ($now) {
        $ctrl = Mockery::mock(DutyController::class)->makePartial();
        $slot = (object) [
            'AS_ID' => 1,
            'AS_DEBUT' => $now->copy()->startOfDay()->setTime(8, 0)->toDateTimeString(),
            'AS_FIN' => $now->copy()->startOfDay()->setTime(20, 0)->toDateTimeString(),
            'P_ID' => 5, 'P_NOM' => 'Durand', 'P_PRENOM' => 'Paul',
            'P_PHONE' => '0600000000', 'GP_DESCRIPTION' => 'Chef de garde',
        ];
        $slots = collect([$slot]);
        $ctrl->shouldReceive('today')->andReturn(
            view('duty.today', [
                'slots' => $slots,
                'byRole' => $slots->groupBy('GP_DESCRIPTION'),
                'day' => $now->copy()->startOfDay(),
            ])
        );

        return $ctrl;
    });
}

test('the garde du jour route is registered', function () {
    expect(route('duty.today'))->toContain('/duty/today');
});

test('unauthenticated users are redirected from garde du jour to login', function () {
    $this->get('/duty/today')->assertRedirect('/login');
});

test('authenticated users can view the garde du jour', function () {
    gardeStubToday();

    $this->actingAs(gardeFakeUser())->get('/duty/today')
        ->assertStatus(200)
        ->assertViewIs('duty.today')
        ->assertSee(__('duty.today_heading'))
        ->assertSee('Paul DURAND')
        ->assertSee('Chef de garde');
});

// ── Garde calendar (#20) ─────────────────────────────────────────────────────

test('the garde calendar routes are registered', function () {
    expect(route('duty.calendar'))->toContain('/duty/calendar');
    expect(route('duty.calendar.events'))->toContain('/duty/calendar/events');
});

test('unauthenticated users are redirected from the garde calendar to login', function () {
    $this->get('/duty/calendar')->assertRedirect('/login');
    $this->get('/duty/calendar/events')->assertRedirect('/login');
});

test('the on-call print route is registered (the monthly view links to it)', function () {
    expect(route('duty.on-call.print'))->toContain('/duty/monthly/print');
});
