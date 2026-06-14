<?php

use App\Models\Subtask;
use App\Services\WorkspaceAnalyticsService;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new WorkspaceAnalyticsService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('getOverview returns correct KPI counts', function () {
    // Create a completed subtask
    $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Done',
        'completed_at' => now(),
    ]);

    // Create an overdue subtask
    $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Overdue',
        'due_date' => now()->subDays(5),
        'completed_at' => null,
    ]);

    $overview = $this->service->getOverview($this->hierarchy['workspace']);

    expect($overview['kpi']['tasks_total'])->toBe(1); // 1 task in hierarchy
    expect($overview['kpi']['subtasks_completed'])->toBe(1);
    expect($overview['kpi']['subtasks_overdue'])->toBe(1);
});

test('getOverview respects date range filtering', function () {
    $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Recent',
        'completed_at' => now()->subDays(5),
    ]);

    $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Old',
        'completed_at' => now()->subDays(60),
    ]);

    $overview = $this->service->getOverview(
        $this->hierarchy['workspace'],
        now()->subDays(10)->toDateString(),
        now()->toDateString()
    );

    expect($overview['kpi']['subtasks_completed'])->toBe(1);
});

test('getOverview default range is last 30 days', function () {
    $overview = $this->service->getOverview($this->hierarchy['workspace']);

    expect($overview['range']['start'])->toBe(now()->subDays(30)->toDateString());
    expect($overview['range']['end'])->toBe(now()->toDateString());
});

test('calculateEvm returns correct PV EV AC and variances', function () {
    // Subtask with 2h estimate, fully completed, 1.5h spent
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'EVM task',
        'time_estimate' => 120, // 2h in minutes
        'completed_at' => now(),
        'time_spent' => 90, // 1.5h
        'start_date' => now()->subDays(10),
        'due_date' => now()->addDays(5),
        'baseline_start_date' => now()->subDays(10),
        'baseline_due_date' => now()->addDays(5),
    ]);

    $overview = $this->service->getOverview($this->hierarchy['workspace']);
    $evm = $overview['evm'];

    // EV should be > 0 (completed task)
    expect($evm['ev'])->toBeGreaterThan(0);
    // PV should be > 0 (within schedule window)
    expect($evm['pv'])->toBeGreaterThan(0);
    // CV = EV - AC
    expect($evm['cv'])->toBe(round($evm['ev'] - $evm['ac'], 2));
    // SV = EV - PV
    expect($evm['sv'])->toBe(round($evm['ev'] - $evm['pv'], 2));
});

test('getCsvRows returns KPI and EVM metric rows', function () {
    $this->createSubtask($this->hierarchy['task'], [
        'name' => 'CSV test',
        'time_estimate' => 60,
        'completed_at' => now(),
    ]);

    $rows = $this->service->getCsvRows($this->hierarchy['workspace']);

    $metrics = $rows->pluck('metric')->toArray();

    // KPI metrics
    expect($metrics)->toContain('tasks_total');
    expect($metrics)->toContain('subtasks_completed');
    expect($metrics)->toContain('subtasks_overdue');
    expect($metrics)->toContain('active_sprints');

    // EVM metrics
    expect($metrics)->toContain('evm_pv');
    expect($metrics)->toContain('evm_ev');
    expect($metrics)->toContain('evm_ac');
    expect($metrics)->toContain('evm_cv');
    expect($metrics)->toContain('evm_sv');
});
