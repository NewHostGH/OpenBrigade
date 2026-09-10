<?php

use App\Models\User;
use App\Services\DashboardService;
use App\Services\NavigationService;
use Mockery\MockInterface;

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Stub NavigationService so the layout's view composer never hits the DB.
 */
function dashboardStubNav(): void
{
    $nav = Mockery::mock(NavigationService::class);
    $nav->shouldReceive('getNavGroups')->andReturn([]);
    $nav->shouldReceive('getPinnedShortcuts')->andReturn([]);
    app()->instance(NavigationService::class, $nav);
}

/**
 * Build a minimal fake User (no DB required). hasPermission() returns false so
 * every Blade template renders without touching the database.
 */
function dashboardFakeUser(array $attrs = []): User
{
    /** @var User&MockInterface $user */
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(array_merge([
        'P_ID' => 1,
        'P_NOM' => 'Test',
        'P_PRENOM' => 'User',
        'P_SECTION' => 1,
        'P_CIVILITE' => 1,
        'P_STATUT' => 'INT',
        'P_PHOTO' => '',
        'GP_ID' => 2,
        'GP_ID2' => null,
        'P_ACTIF' => 1,
        'P_MDP' => bcrypt('secret'),
    ], $attrs));

    // Prevent hasPermission() from hitting the DB in any Blade template.
    $user->shouldReceive('hasPermission')->andReturn(false);

    return $user;
}

/**
 * Empty stubs matching the exact array shapes the dashboard widgets expect.
 * Keys mirror the real DashboardService return values (verified against the
 * widget partials), so the controller can render every widget without a DB.
 */
function dashboardPayloads(User $user): array
{
    return [
        'getStats' => [
            'partiDone' => 5,
            'partiIncoming' => 2,
            'actMonth' => 3,
            'actQuarter' => 9,
            'newMonth' => 1,
            'newQuarter' => 2,
            'alerts' => 0,
            'sectionName' => 'Section Test',
            'year' => date('Y'),
            'pid' => 1,
        ],
        'getPasswordExpiry' => null,
        'getCompetenceWarnings' => [],
        'getWelcome' => [
            'user' => $user,
            'section' => null,         // null → @if omits S_DESCRIPTION access
            'avatarSrc' => '',
            'avatarFallback' => '',
            'missingFields' => [],
        ],
        'getMyActivities' => ['events' => []],
        'getReplacementRequests' => ['count' => 0, 'debut' => null, 'fin' => null],
        'getUnpaidActivities' => ['rows' => []],
        'getMissingStats' => ['rows' => []],
        'getExpenses' => ['rows' => [], 'isManager' => false],
        'getEvents' => ['events' => [], 'sectionId' => 1, 'sectionName' => ''],
        'getDuty' => ['duty' => []],
        'getInfos' => ['consignes' => [], 'actualites' => []],
        'getBirthdays' => ['days' => []],
        'getVehiclesAlerts' => ['items' => []],
        'getConsumablesAlerts' => ['items' => []],
        'getCpAlerts' => ['count' => 0, 'items' => []],
        'getHorairesAlerts' => ['rows' => []],
        'getReplacementsAlerts' => ['count' => 0, 'type' => ''],
        'getTraining' => ['asTrainee' => [], 'asTrainer' => [], 'year' => date('Y')],
        'getMcEvents' => ['events' => []],
        'getSectionLinks' => ['links' => [], 'whatsappBase' => ''],
        'getAbout' => ['version' => '1.0.0', 'supportEmail' => '', 'canAdmin' => false],
        'getWidgetLayout' => ['columns' => [1 => [], 2 => [], 3 => []], 'hidden' => []],
    ];
}

/**
 * Bind a fully-stubbed DashboardService into the container. Individual method
 * returns can be overridden via $overrides (e.g. to drive the expiry banner).
 */
function dashboardStubService(User $user, array $overrides = []): void
{
    $stub = Mockery::mock(DashboardService::class);
    $payloads = array_merge(dashboardPayloads($user), $overrides);

    foreach ($payloads as $method => $value) {
        $stub->shouldReceive($method)->andReturn($value);
    }

    app()->instance(DashboardService::class, $stub);
}

// Stub the NavigationService before every test so view composers never hit DB.
beforeEach(fn () => dashboardStubNav());

// ── Access control ───────────────────────────────────────────────────────────

test('unauthenticated users are redirected from /dashboard to login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('unauthenticated users are redirected from /legacy/index_d.php to login', function () {
    $this->get('/legacy/index_d.php')->assertRedirect('/login');
});

// ── Authenticated access ─────────────────────────────────────────────────────

test('authenticated users can access the dashboard', function () {
    $user = dashboardFakeUser();
    dashboardStubService($user);

    $this->actingAs($user)->get('/dashboard')->assertStatus(200);
});

test('dashboard renders the dashboard.index view', function () {
    $user = dashboardFakeUser();
    dashboardStubService($user);

    $this->actingAs($user)->get('/dashboard')->assertViewIs('dashboard.index');
});

test('dashboard passes all required view variables', function () {
    $user = dashboardFakeUser();
    dashboardStubService($user);

    $this->actingAs($user)->get('/dashboard')->assertViewHasAll([
        'stats', 'passwordExpiry', 'competenceAlerts', 'welcome',
        'events', 'duty', 'infos', 'birthdays', 'vehicles', 'consumables',
        'cp', 'horaires', 'remplacements', 'training', 'mc',
        'sectionLinks', 'about', 'numberEvents',
    ]);
});

test('numberEvents defaults to 20 when no query param is provided', function () {
    $user = dashboardFakeUser();
    dashboardStubService($user);

    $this->actingAs($user)->get('/dashboard')->assertViewHas('numberEvents', 20);
});

test('numberEvents reflects the number_events query parameter', function () {
    $user = dashboardFakeUser();
    dashboardStubService($user);

    $this->actingAs($user)->get('/dashboard?number_events=5')->assertViewHas('numberEvents', 5);
});

// ── Legacy bridge redirects ──────────────────────────────────────────────────

test('legacy index_d.php redirects to the dashboard', function () {
    $this->actingAs(dashboardFakeUser())->get('/legacy/index_d.php')
        ->assertRedirect(route('dashboard'));
});

test('/legacy shortcut also redirects to the dashboard', function () {
    $this->actingAs(dashboardFakeUser())->get('/legacy')
        ->assertRedirect(route('dashboard'));
});

// ── Password-expiry banner ───────────────────────────────────────────────────

test('password expiry banner is absent when service returns null', function () {
    $user = dashboardFakeUser();
    dashboardStubService($user); // getPasswordExpiry => null

    // "Changer maintenant" only appears inside the dash-alert password block.
    $this->actingAs($user)->get('/dashboard')->assertDontSee('Changer maintenant');
});

test('password expiry warning banner is shown when service returns expiry data', function () {
    $user = dashboardFakeUser();
    dashboardStubService($user, [
        'getPasswordExpiry' => ['expired' => false, 'days' => 3, 'expiry' => '01/06/2026'],
    ]);

    $this->actingAs($user)->get('/dashboard')->assertSee('3 jours');
});

// ── Agenda widget (#17) ──────────────────────────────────────────────────────

test('the agenda widget is registered with a label and a default position', function () {
    $keys = array_column(DashboardService::WIDGET_DEFAULTS, 'key');

    expect($keys)->toContain('agenda');
    expect(DashboardService::WIDGET_LABELS)->toHaveKey('agenda');

    // Default positions within a column must stay unique.
    foreach ([1, 2, 3] as $col) {
        $positions = collect(DashboardService::WIDGET_DEFAULTS)
            ->where('col', $col)->pluck('position')->all();
        expect($positions)->toEqual(array_unique($positions));
    }
});
