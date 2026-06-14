<?php

use App\Models\Subtask;
use App\Services\WorkspaceAnalyticsService;
use Carbon\Carbon;

// Use Mockery with shouldIgnoreMissing to avoid setAttribute() issues
function makeSubtaskMock(array $attrs = []): Subtask
{
    $defaults = [
        'baseline_start_date' => null,
        'baseline_due_date' => null,
        'start_date' => null,
        'due_date' => null,
        'completed_at' => null,
    ];

    $merged = array_merge($defaults, $attrs);

    $mock = Mockery::mock(Subtask::class)->shouldIgnoreMissing();
    foreach ($merged as $key => $value) {
        $mock->shouldReceive('getAttribute')->with($key)->andReturn($value);
        $mock->{$key} = $value;
    }
    // Also handle direct property access via __get
    $mock->shouldReceive('__get')->andReturnUsing(function ($key) use ($merged) {
        return $merged[$key] ?? null;
    });

    return $mock;
}

test('getScheduledProgressRatio returns 0 when asOf is before start date', function () {
    $service = new WorkspaceAnalyticsService;
    $method = new ReflectionMethod($service, 'getScheduledProgressRatio');

    $subtask = new Subtask;
    $subtask->forceFill([
        'baseline_start_date' => '2026-03-10',
        'baseline_due_date' => '2026-03-20',
    ]);

    expect($method->invoke($service, $subtask, Carbon::parse('2026-03-05')))->toBe(0.0);
});

test('getScheduledProgressRatio returns 1.0 when asOf is after end date', function () {
    $service = new WorkspaceAnalyticsService;
    $method = new ReflectionMethod($service, 'getScheduledProgressRatio');

    $subtask = new Subtask;
    $subtask->forceFill([
        'baseline_start_date' => '2026-03-10',
        'baseline_due_date' => '2026-03-20',
    ]);

    expect($method->invoke($service, $subtask, Carbon::parse('2026-03-25')))->toBe(1.0);
});

test('getScheduledProgressRatio returns ~0.5 when asOf is midpoint', function () {
    $service = new WorkspaceAnalyticsService;
    $method = new ReflectionMethod($service, 'getScheduledProgressRatio');

    $subtask = new Subtask;
    $subtask->forceFill([
        'baseline_start_date' => '2026-03-10',
        'baseline_due_date' => '2026-03-20',
    ]);

    $ratio = $method->invoke($service, $subtask, Carbon::parse('2026-03-15'));
    expect($ratio)->toBeGreaterThan(0.4)->toBeLessThan(0.6);
});

test('getScheduledProgressRatio returns 1.0 when start equals end', function () {
    $service = new WorkspaceAnalyticsService;
    $method = new ReflectionMethod($service, 'getScheduledProgressRatio');

    $subtask = new Subtask;
    $subtask->forceFill([
        'baseline_start_date' => '2026-03-10 00:00:00',
        'baseline_due_date' => '2026-03-10 00:00:00',
    ]);

    // When start == end, the method returns 1.0 if asOf >= end
    expect($method->invoke($service, $subtask, Carbon::parse('2026-03-10 12:00:00')))->toBe(1.0);
});

test('getScheduledProgressRatio returns 0 when no dates and not completed', function () {
    $service = new WorkspaceAnalyticsService;
    $method = new ReflectionMethod($service, 'getScheduledProgressRatio');

    $subtask = new Subtask;

    expect($method->invoke($service, $subtask, Carbon::parse('2026-03-15')))->toBe(0.0);
});

test('getScheduledProgressRatio returns 1.0 when no dates but completed', function () {
    $service = new WorkspaceAnalyticsService;
    $method = new ReflectionMethod($service, 'getScheduledProgressRatio');

    $subtask = new Subtask;
    $subtask->forceFill(['completed_at' => '2026-03-12']);

    expect($method->invoke($service, $subtask, Carbon::parse('2026-03-15')))->toBe(1.0);
});

test('getScheduledProgressRatio falls back to start_date/due_date when baseline is null', function () {
    $service = new WorkspaceAnalyticsService;
    $method = new ReflectionMethod($service, 'getScheduledProgressRatio');

    $subtask = new Subtask;
    $subtask->forceFill([
        'start_date' => '2026-03-10',
        'due_date' => '2026-03-20',
    ]);

    $ratio = $method->invoke($service, $subtask, Carbon::parse('2026-03-15'));
    expect($ratio)->toBeGreaterThan(0.4)->toBeLessThan(0.6);
});

test('getScheduledProgressRatio never exceeds 1.0', function () {
    $service = new WorkspaceAnalyticsService;
    $method = new ReflectionMethod($service, 'getScheduledProgressRatio');

    $subtask = new Subtask;
    $subtask->forceFill([
        'baseline_start_date' => '2026-01-01',
        'baseline_due_date' => '2026-01-02',
    ]);

    expect($method->invoke($service, $subtask, Carbon::parse('2026-12-31')))->toBe(1.0);
});

test('getScheduledProgressRatio never goes below 0.0', function () {
    $service = new WorkspaceAnalyticsService;
    $method = new ReflectionMethod($service, 'getScheduledProgressRatio');

    $subtask = new Subtask;
    $subtask->forceFill([
        'baseline_start_date' => '2026-12-01',
        'baseline_due_date' => '2026-12-31',
    ]);

    expect($method->invoke($service, $subtask, Carbon::parse('2026-01-01')))->toBe(0.0);
});
