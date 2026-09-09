<?php

use App\Services\TableExportService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

beforeEach(function () {
    $this->withoutMiddleware(ValidateCsrfToken::class);
});

test('the planning export routes are registered', function () {
    expect(route('planning.export.xls'))->toContain('/calendar/export/xls');
    expect(route('planning.export.csv'))->toContain('/calendar/export/csv');
});

test('unauthenticated users are redirected from the planning exports to login', function () {
    $this->get('/calendar/export/xls')->assertRedirect('/login');
    $this->get('/calendar/export/csv')->assertRedirect('/login');
});

test('the XLSX exporter supports more than 26 columns', function () {
    // A monthly planning matrix has 3 fixed + up to 31 day columns (> Z),
    // so column letters must roll over past Z (AA, AB, …).
    $columns = [];
    for ($i = 1; $i <= 35; $i++) {
        $columns[] = ["col$i", fn ($item) => $item[$i - 1] ?? ''];
    }
    $items = [array_fill(0, 35, 'x')];

    $response = app(TableExportService::class)->toXlsx($columns, $items, 'wide');

    ob_start();
    $response->sendContent();
    $bytes = strlen(ob_get_clean());

    expect($bytes)->toBeGreaterThan(0);
    expect($response->headers->get('Content-Type'))
        ->toContain('spreadsheetml.sheet');
});
