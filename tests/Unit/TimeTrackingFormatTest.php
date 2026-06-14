<?php

use App\Services\TimeTrackingService;

test('formatMinutes returns only minutes when less than 60', function () {
    $service = new TimeTrackingService;
    $method = new ReflectionMethod($service, 'formatMinutes');

    expect($method->invoke($service, 0))->toBe('0m');
    expect($method->invoke($service, 30))->toBe('30m');
    expect($method->invoke($service, 59))->toBe('59m');
});

test('formatMinutes returns hours and minutes when 60 or more', function () {
    $service = new TimeTrackingService;
    $method = new ReflectionMethod($service, 'formatMinutes');

    expect($method->invoke($service, 60))->toBe('1h 0m');
    expect($method->invoke($service, 90))->toBe('1h 30m');
    expect($method->invoke($service, 120))->toBe('2h 0m');
});
