<?php

use App\Services\WorkAvailabilityService;

/**
 * Pure period-maths of the coherence service (no DB). Periods:
 * 1 = Matin 06–12, 2 = Après-midi 12–18, 3 = Soir 18–24, 4 = Nuit 00–06.
 */
test('a clock range maps to the overlapping availability periods', function () {
    $s = new WorkAvailabilityService;

    expect($s->periodsForTime('08:00', '12:00'))->toBe([1]);
    expect($s->periodsForTime('14:00', '18:00'))->toBe([2]);
    expect($s->periodsForTime('08:00', '17:00'))->toBe([1, 2]);
    expect($s->periodsForTime('00:00', '06:00'))->toBe([4]);
});

test('a range that wraps past midnight covers both ends', function () {
    $s = new WorkAvailabilityService;

    // 20:00 → 08:00 covers Soir (18–24), Nuit (00–06) and the start of Matin (06–08).
    expect($s->periodsForTime('20:00', '08:00'))->toEqualCanonicalizing([1, 3, 4]);
});

test('an empty or zero-length range maps to nothing', function () {
    $s = new WorkAvailabilityService;

    expect($s->periodsForTime(null, '12:00'))->toBe([]);
    expect($s->periodsForTime('08:00', '08:00'))->toBe([]);
});
