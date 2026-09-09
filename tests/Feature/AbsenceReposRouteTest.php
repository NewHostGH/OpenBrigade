<?php

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('the absence declaration + validation routes are registered', function () {
    expect(route('unavailability.create'))->toContain('/unavailability/create');
    expect(route('unavailability.store'))->toContain('/unavailability');
    expect(route('unavailability.decide', 1))->toContain('/unavailability/1/decide');
    expect(route('unavailability.cancel', 1))->toContain('/unavailability/1/cancel');
});

test('the repos routes are registered', function () {
    expect(route('repos.index'))->toContain('/rest');
    expect(route('repos.save'))->toContain('/rest');
});

test('unauthenticated users are redirected from the new planning screens to login', function () {
    $this->get('/unavailability/create')->assertRedirect('/login');
    $this->post('/unavailability')->assertRedirect('/login');
    $this->post('/unavailability/1/decide')->assertRedirect('/login');
    $this->get('/rest')->assertRedirect('/login');
    $this->post('/rest')->assertRedirect('/login');
});
