<?php

use App\Services\SubtaskService;

test('normalizeDateForComparison returns null for null input', function () {
    $service = new SubtaskService;
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    expect($method->invoke($service, null))->toBeNull();
});

test('normalizeDateForComparison returns null for empty string', function () {
    $service = new SubtaskService;
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    expect($method->invoke($service, ''))->toBeNull();
    expect($method->invoke($service, '   '))->toBeNull();
});

test('normalizeDateForComparison parses Y-m-d string correctly', function () {
    $service = new SubtaskService;
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    expect($method->invoke($service, '2026-03-15'))->toBe('2026-03-15');
});

test('normalizeDateForComparison extracts date from datetime string', function () {
    $service = new SubtaskService;
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    expect($method->invoke($service, '2026-03-15 14:30:00'))->toBe('2026-03-15');
    expect($method->invoke($service, '2026-03-15T14:30:00'))->toBe('2026-03-15');
});

test('normalizeDateForComparison handles Carbon instances', function () {
    $service = new SubtaskService;
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    $carbon = \Carbon\Carbon::create(2026, 1, 15, 8, 30, 0);
    expect($method->invoke($service, $carbon))->toBe('2026-01-15');
});

test('normalizeDateForComparison returns null for non-scalar value', function () {
    $service = new SubtaskService;
    $method = new ReflectionMethod($service, 'normalizeDateForComparison');

    expect($method->invoke($service, ['invalid']))->toBeNull();
});
