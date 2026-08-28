<?php

use App\Http\Controllers\AvailabilityController;
use App\Models\User;
use App\Services\FeatureService;
use App\Services\NavigationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Mockery\MockInterface;

function avStubNav(): void
{
    $nav = Mockery::mock(NavigationService::class);
    $nav->shouldReceive('getNavGroups')->andReturn([]);
    $nav->shouldReceive('getPinnedShortcuts')->andReturn([]);
    app()->instance(NavigationService::class, $nav);
}

function avFakeUser(): User
{
    /** @var User&MockInterface $user */
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill([
        'P_ID' => 1, 'P_NOM' => 'Test', 'P_PRENOM' => 'User',
        'P_SECTION' => 1, 'P_ACTIF' => 1, 'P_MDP' => bcrypt('secret'),
    ]);
    $user->shouldReceive('hasPermission')->andReturn(true);

    return $user;
}

beforeEach(function () {
    avStubNav();
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('unauthenticated users are redirected from the availability to login', function () {
    $this->get('/availability')->assertRedirect('/login');
});

test('the availability route is registered', function () {
    expect(route('availability.index'))->toContain('/availability');
});

test('authenticated users see the availability grid', function () {
    // The route is gated by the `disponibilites` feature — enable it here.
    $feat = Mockery::mock(FeatureService::class);
    $feat->shouldReceive('isEnabled')->andReturn(true);
    app()->instance(FeatureService::class, $feat);

    $now = now();
    app()->bind(AvailabilityController::class, function () use ($now) {
        $ctrl = Mockery::mock(AvailabilityController::class)->makePartial();
        $person = (object) ['P_ID' => 7, 'P_NOM' => 'Martin', 'P_PRENOM' => 'Lea', 'P_SECTION' => 1];
        $periods = collect([(object) ['DP_ID' => 1, 'DP_NAME' => 'Matin']]);
        $key = $now->toDateString();
        $ctrl->shouldReceive('index')->andReturn(
            view('availability.index', [
                'personnel' => collect([$person]),
                'periods' => $periods,
                'periodMap' => $periods->keyBy('DP_ID'),
                'byPersonDate' => [7 => [$key => [1]]],
                'days' => [[
                    'key' => $key, 'day' => (int) $now->day, 'weekday' => 'Lu',
                    'isWeekend' => false, 'isToday' => true, 'isPast' => false,
                ]],
                'first' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
                'week' => 0, 'prevWeek' => -1, 'nextWeek' => 1,
                'canSeeOthers' => false,
                'sectionId' => null,
            ])
        );

        return $ctrl;
    });

    $this->actingAs(avFakeUser())->get('/availability')
        ->assertStatus(200)
        ->assertViewIs('availability.index')
        ->assertSee('MARTIN Lea')
        ->assertSee('Matin');
});

test('the availability declaration + print routes are registered', function () {
    expect(route('availability.toggle'))->toContain('/availability/toggle');
    expect(route('availability.print'))->toContain('/availability/print');
});

test('unauthenticated users are redirected from the availability declaration to login', function () {
    $this->post('/availability/toggle')->assertRedirect('/login');
});
