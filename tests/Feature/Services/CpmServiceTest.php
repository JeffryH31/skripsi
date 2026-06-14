<?php

use App\Models\Task;
use App\Services\CpmService;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new CpmService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('analyze returns failure for empty subtasks', function () {
    $result = $this->service->analyze($this->hierarchy['task']);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('No subtasks found');
});

test('analyze returns failure when no subtasks have time estimates', function () {
    $this->createSubtask($this->hierarchy['task'], [
        'name' => 'No Estimate',
        'time_estimate' => null,
    ]);

    $result = $this->service->analyze($this->hierarchy['task']);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('time estimates');
});

test('analyze linear chain calculates correct ES/EF/LS/LF', function () {
    // A(2h) → B(3h) → C(1h) — all critical
    $a = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'A',
        'time_estimate' => 120, // 2h in minutes
    ]);

    $b = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'B',
        'time_estimate' => 180, // 3h
    ]);

    $c = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'C',
        'time_estimate' => 60, // 1h
    ]);

    // A → B → C (blocks-type)
    $b->dependencies()->attach($a->id, ['dependency_type' => 'blocks']);
    $c->dependencies()->attach($b->id, ['dependency_type' => 'blocks']);

    $result = $this->service->analyze($this->hierarchy['task']);

    expect($result['success'])->toBeTrue();

    $data = collect($result['data']['subtasks']);
    $nodeA = $data[$a->id];
    $nodeB = $data[$b->id];
    $nodeC = $data[$c->id];

    // Forward pass: A(0→2), B(2→5), C(5→6)
    expect($nodeA['earlyStart'])->toEqual(0);
    expect($nodeA['earlyFinish'])->toEqual(2);
    expect($nodeB['earlyStart'])->toEqual(2);
    expect($nodeB['earlyFinish'])->toEqual(5);
    expect($nodeC['earlyStart'])->toEqual(5);
    expect($nodeC['earlyFinish'])->toEqual(6);

    // All should be critical (linear chain)
    expect($nodeA['isCritical'])->toBeTrue();
    expect($nodeB['isCritical'])->toBeTrue();
    expect($nodeC['isCritical'])->toBeTrue();
});

test('analyze parallel paths identifies critical vs non-critical', function () {
    // A(2h) → C(1h)
    // B(1h) → C(1h)
    // Critical path: A → C (3h total), B has slack
    $a = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'A',
        'time_estimate' => 120, // 2h
    ]);

    $b = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'B',
        'time_estimate' => 60, // 1h
    ]);

    $c = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'C',
        'time_estimate' => 60, // 1h
    ]);

    $c->dependencies()->attach($a->id, ['dependency_type' => 'blocks']);
    $c->dependencies()->attach($b->id, ['dependency_type' => 'blocks']);

    $result = $this->service->analyze($this->hierarchy['task']);

    expect($result['success'])->toBeTrue();

    $data = collect($result['data']['subtasks']);

    expect($data[$a->id]['isCritical'])->toBeTrue();
    expect($data[$c->id]['isCritical'])->toBeTrue();
    expect($data[$b->id]['isCritical'])->toBeFalse();
    expect($data[$b->id]['slack'])->toBeGreaterThan(0);
});

test('analyze detects circular dependency', function () {
    $a = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'A',
        'time_estimate' => 60,
    ]);

    $b = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'B',
        'time_estimate' => 60,
    ]);

    // A → B and B → A (circular)
    $b->dependencies()->attach($a->id, ['dependency_type' => 'blocks']);
    $a->dependencies()->attach($b->id, ['dependency_type' => 'blocks']);

    $result = $this->service->analyze($this->hierarchy['task']);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('Circular dependency');
});

test('analyze single subtask is critical with correct duration', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Solo',
        'time_estimate' => 180, // 3h
    ]);

    $result = $this->service->analyze($this->hierarchy['task']);

    expect($result['success'])->toBeTrue();

    $data = $result['data']['subtasks'][$subtask->id];

    expect($data['isCritical'])->toBeTrue();
    expect($data['duration'])->toEqual(3);
    expect($data['earlyStart'])->toEqual(0);
    expect($data['earlyFinish'])->toEqual(3);
});

test('addDependency rejects cross-task dependency', function () {
    $otherTask = Task::create([
        'project_id' => $this->hierarchy['list']->id,
        'name' => 'Other Task',
        'created_by' => $this->owner->id,
    ]);

    $subtaskA = $this->createSubtask($this->hierarchy['task']);
    $subtaskB = $this->createSubtask($otherTask);

    $result = $this->service->addDependency($subtaskA, $subtaskB);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('same task');
});

test('addDependency rejects cycle-creating dependency', function () {
    $a = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'A',
        'time_estimate' => 60,
    ]);

    $b = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'B',
        'time_estimate' => 60,
    ]);

    // B depends on A (A blocks B)
    $b->dependencies()->attach($a->id, ['dependency_type' => 'blocks']);

    // Now try A depends on B (B blocks A) — would create cycle
    $result = $this->service->addDependency($a, $b);

    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('circular');
});

test('removeDependency detaches dependency between subtasks', function () {
    $a = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'A',
        'time_estimate' => 60,
    ]);

    $b = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'B',
        'time_estimate' => 60,
    ]);

    // B depends on A
    $b->dependencies()->attach($a->id, ['dependency_type' => 'blocks']);
    expect($b->dependencies()->count())->toBe(1);

    $result = $this->service->removeDependency($b, $a);

    expect($result['success'])->toBeTrue();
    expect($b->dependencies()->count())->toBe(0);
});
