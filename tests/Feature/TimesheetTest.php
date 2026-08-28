<?php

use App\Http\Controllers\TimesheetController;
use App\Models\User;
use App\Services\FeatureService;
use App\Services\NavigationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Mockery\MockInterface;

function tsStubNav(): void
{
    $nav = Mockery::mock(NavigationService::class);
    $nav->shouldReceive('getNavGroups')->andReturn([]);
    $nav->shouldReceive('getPinnedShortcuts')->andReturn([]);
    app()->instance(NavigationService::class, $nav);
}

function tsFakeUser(): User
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

function tsEnableFeature(): void
{
    $feat = Mockery::mock(FeatureService::class);
    $feat->shouldReceive('isEnabled')->andReturn(true);
    app()->instance(FeatureService::class, $feat);
}

beforeEach(function () {
    tsStubNav();
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('unauthenticated users are redirected from the timesheet to login', function () {
    $this->get('/timesheet')->assertRedirect('/login');
});

test('the timesheet routes are registered', function () {
    expect(route('timesheet.index'))->toContain('/timesheet');
    expect(route('timesheet.save'))->toContain('/timesheet');
    expect(route('timesheet.submit'))->toContain('/timesheet/submit');
    expect(route('timesheet.decide'))->toContain('/timesheet/decide');
    expect(route('timesheet.print'))->toContain('/timesheet/print');
});

test('unauthenticated users are redirected from the timesheet save to login', function () {
    $this->post('/timesheet')->assertRedirect('/login');
});

test('authenticated users see the weekly timesheet grid', function () {
    tsEnableFeature();

    $now = now();
    app()->bind(TimesheetController::class, function () use ($now) {
        $ctrl = Mockery::mock(TimesheetController::class)->makePartial();
        $person = (object) ['P_ID' => 7, 'P_NOM' => 'Martin', 'P_PRENOM' => 'Lea', 'P_SECTION' => 1];
        $ctrl->shouldReceive('index')->andReturn(
            view('timesheet.index', [
                'personnel' => collect([$person]),
                'person' => $person,
                'personId' => 7,
                'days' => [[
                    'key' => $now->toDateString(), 'label' => 'Lun 1 jan', 'weekday' => 'Lundi',
                    'isWeekend' => false, 'isToday' => true,
                    'debut1' => '08:00', 'fin1' => '12:00', 'debut2' => '', 'fin2' => '',
                    'overtime' => '0:00', 'total' => '4:00', 'comment' => '', 'absence' => null,
                ]],
                'first' => $now->copy()->startOfWeek(),
                'end' => $now->copy()->endOfWeek(),
                'week' => 0, 'prevWeek' => -1, 'nextWeek' => 1,
                'weekTotal' => '4:00', 'monthTotal' => '4:00', 'yearTotal' => '4:00',
                'status' => 'SEC', 'statusLabel' => 'Saisie en cours', 'statusClass' => 'ob-ts-badge--open',
                'canManage' => true, 'isSelf' => true, 'editable' => true,
                'canSubmit' => true, 'canDecide' => false,
                'canSeeOthers' => false, 'sectionId' => null,
            ])
        );

        return $ctrl;
    });

    $this->actingAs(tsFakeUser())->get('/timesheet')
        ->assertStatus(200)
        ->assertViewIs('timesheet.index')
        ->assertSee('MARTIN Lea')
        ->assertSee('Saisie en cours')
        ->assertSee('Soumettre à validation');
});
