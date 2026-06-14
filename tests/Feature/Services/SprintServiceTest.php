<?php

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Subtask;
use App\Models\Task;
use App\Services\SprintService;
use Illuminate\Validation\ValidationException;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new SprintService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('createSprint creates with auto-incremented position', function () {
    $sprint1 = $this->service->createSprint($this->hierarchy['list'], [
        'name' => 'Sprint 1',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-14',
    ]);

    $sprint2 = $this->service->createSprint($this->hierarchy['list'], [
        'name' => 'Sprint 2',
        'start_date' => '2026-05-15',
        'end_date' => '2026-05-28',
    ]);

    expect($sprint1->position)->toBeLessThan($sprint2->position);
    expect($sprint2->position)->toBe($sprint1->position + 1);
});

test('startSprint sets is_active true and deactivates other sprints', function () {
    $sprint1 = $this->createSprint($this->hierarchy['list'], [
        'name' => 'Sprint 1',
        'is_active' => true,
    ]);

    $sprint2 = $this->createSprint($this->hierarchy['list'], [
        'name' => 'Sprint 2',
        'is_active' => false,
    ]);

    $this->service->startSprint($sprint2);

    expect($sprint2->fresh()->is_active)->toBeTrue();
    expect($sprint1->fresh()->is_active)->toBeFalse();
});

test('completeSprint sets is_active false', function () {
    $sprint = $this->createSprint($this->hierarchy['list'], [
        'is_active' => true,
    ]);

    $this->service->completeSprint($sprint);

    expect($sprint->fresh()->is_active)->toBeFalse();
});

test('addSubtaskToSprint sets sprint_id on subtask', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $subtask = $this->createSubtask($this->hierarchy['task']);

    $this->service->addSubtaskToSprint($sprint, $subtask->id);

    expect($subtask->fresh()->sprint_id)->toBe($sprint->id);
});

test('addSubtaskToSprint rejects subtask from different product', function () {
    $otherList = Project::create([
        'space_id' => $this->hierarchy['space']->id,
        'name' => 'Other List',
        'created_by' => $this->owner->id,
    ]);

    $otherTask = Task::create([
        'project_id' => $otherList->id,
        'name' => 'Other Task',
        'created_by' => $this->owner->id,
    ]);

    $sprint = $this->createSprint($this->hierarchy['list']);
    $foreignSubtask = $this->createSubtask($otherTask);

    expect(fn () => $this->service->addSubtaskToSprint($sprint, $foreignSubtask->id))
        ->toThrow(ValidationException::class);
});

test('removeSubtaskFromSprint clears sprint_id', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'sprint_id' => $sprint->id,
    ]);

    $this->service->removeSubtaskFromSprint($sprint, $subtask->id);

    expect($subtask->fresh()->sprint_id)->toBeNull();
});

test('removeSubtaskFromSprint rejects subtask not in sprint', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $subtask = $this->createSubtask($this->hierarchy['task']); // no sprint_id

    expect(fn () => $this->service->removeSubtaskFromSprint($sprint, $subtask->id))
        ->toThrow(ValidationException::class);
});

test('deleteSprint clears sprint_id on all subtasks then deletes', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'sprint_id' => $sprint->id,
    ]);

    $this->service->deleteSprint($sprint);

    expect($subtask->fresh()->sprint_id)->toBeNull();
    expect(Sprint::find($sprint->id))->toBeNull();
});

test('getBacklogSubtasks returns only subtasks with null sprint_id', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);

    $backlog = $this->createSubtask($this->hierarchy['task'], ['name' => 'Backlog Item']);
    $sprinted = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Sprint Item',
        'sprint_id' => $sprint->id,
    ]);

    $result = $this->service->getBacklogSubtasks($this->hierarchy['list']);

    expect($result->pluck('id')->toArray())->toContain($backlog->id);
    expect($result->pluck('id')->toArray())->not->toContain($sprinted->id);
});

test('getSprintStatistics returns correct counts and completion rate', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);

    $this->createSubtask($this->hierarchy['task'], [
        'sprint_id' => $sprint->id,
        'completed_at' => now(),
        'time_estimate' => 60,
    ]);

    $this->createSubtask($this->hierarchy['task'], [
        'sprint_id' => $sprint->id,
        'completed_at' => null,
        'time_estimate' => 120,
    ]);

    $stats = $this->service->getSprintStatistics($sprint);

    expect($stats['total_subtasks'])->toBe(2);
    expect($stats['completed_subtasks'])->toBe(1);
    expect($stats['completion_rate'])->toEqual(50);
    expect($stats['total_estimate'])->toBe(180);
});

test('updateSprint updates name, goal, and dates', function () {
    $sprint = $this->createSprint($this->hierarchy['list'], [
        'name' => 'Sprint 1',
        'start_date' => '2026-05-01',
        'end_date' => '2026-05-14',
    ]);

    $updated = $this->service->updateSprint($sprint, [
        'name' => 'Sprint 1 (Updated)',
        'goal' => 'Deliver MVP',
        'start_date' => '2026-05-02',
        'end_date' => '2026-05-16',
    ]);

    expect($updated->name)->toBe('Sprint 1 (Updated)');
    expect($updated->goal)->toBe('Deliver MVP');
    expect($updated->start_date->toDateString())->toBe('2026-05-02');
    expect($updated->end_date->toDateString())->toBe('2026-05-16');
});

test('calculateVelocity returns correct averages for completed sprints', function () {
    // Create 2 completed sprints (end_date in the past)
    $sprint1 = $this->createSprint($this->hierarchy['list'], [
        'name' => 'Past Sprint 1',
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date' => now()->subDays(16)->toDateString(),
    ]);

    $sprint2 = $this->createSprint($this->hierarchy['list'], [
        'name' => 'Past Sprint 2',
        'start_date' => now()->subDays(15)->toDateString(),
        'end_date' => now()->subDays(1)->toDateString(),
    ]);

    // Sprint 1: 2 completed subtasks
    $this->createSubtask($this->hierarchy['task'], [
        'sprint_id' => $sprint1->id,
        'completed_at' => now()->subDays(20),
        'time_spent' => 60,
    ]);
    $this->createSubtask($this->hierarchy['task'], [
        'sprint_id' => $sprint1->id,
        'completed_at' => now()->subDays(18),
        'time_spent' => 90,
    ]);

    // Sprint 2: 1 completed subtask
    $this->createSubtask($this->hierarchy['task'], [
        'sprint_id' => $sprint2->id,
        'completed_at' => now()->subDays(5),
        'time_spent' => 120,
    ]);

    $velocity = $this->service->calculateVelocity($this->hierarchy['list']);

    expect($velocity['sprints'])->toHaveCount(2);
    expect($velocity['average_velocity'])->toEqual(1.5); // (2 + 1) / 2
});

test('getBurndownData returns ideal and actual burndown', function () {
    $sprint = $this->createSprint($this->hierarchy['list'], [
        'start_date' => now()->subDays(7)->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
    ]);

    // Create 3 subtasks, complete 1
    $this->createSubtask($this->hierarchy['task'], [
        'sprint_id' => $sprint->id,
        'completed_at' => now()->subDays(3),
    ]);
    $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id]);
    $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id]);

    $burndown = $this->service->getBurndownData($sprint);

    expect($burndown)->toHaveKeys(['ideal', 'actual']);
    expect($burndown['ideal'])->not->toBeEmpty();
    expect($burndown['actual'])->not->toBeEmpty();
    // Ideal starts at 3 (total subtasks)
    expect($burndown['ideal'][0]['remaining'])->toBe(3);
});

test('getBurndownData returns empty for sprint with no subtasks', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);

    $burndown = $this->service->getBurndownData($sprint);

    expect($burndown)->toBeEmpty();
});

// ============================================================
// Merged from Unit/Services/SprintServiceTest.php
// ============================================================
test('createSprint creates sprint for list', function () {
    $sprint = $this->service->createSprint($this->hierarchy['list'], [
        'name' => 'Sprint 1',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-14',
    ]);

    expect($sprint)->toBeInstanceOf(Sprint::class);
    expect($sprint->name)->toBe('Sprint 1');
    expect($sprint->project_id)->toBe($this->hierarchy['list']->id);
    expect($sprint->is_active)->toBeFalse();
});

test('createSprint with goal and active state', function () {
    $sprint = $this->service->createSprint($this->hierarchy['list'], [
        'name' => 'Sprint Active',
        'goal' => 'Complete feature X',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-14',
        'is_active' => true,
    ]);

    expect($sprint->goal)->toBe('Complete feature X');
    expect($sprint->is_active)->toBeTrue();
});

test('updateSprint changes sprint name', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);

    $updated = $this->service->updateSprint($sprint, ['name' => 'Renamed Sprint']);

    expect($updated->name)->toBe('Renamed Sprint');
});

test('deleteSprint removes sprint and unlinks subtasks', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $subtask = $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id]);

    $this->service->deleteSprint($sprint);

    expect(Sprint::find($sprint->id))->toBeNull();
    expect($subtask->fresh()->sprint_id)->toBeNull();
});

test('startSprint activates sprint and deactivates others', function () {
    $sprint1 = $this->createSprint($this->hierarchy['list'], ['is_active' => true]);
    $sprint2 = $this->createSprint($this->hierarchy['list']);

    $this->service->startSprint($sprint2);

    expect($sprint2->fresh()->is_active)->toBeTrue();
    expect($sprint1->fresh()->is_active)->toBeFalse();
});

test('completeSprint deactivates sprint', function () {
    $sprint = $this->createSprint($this->hierarchy['list'], ['is_active' => true]);

    $completed = $this->service->completeSprint($sprint);

    expect($completed->is_active)->toBeFalse();
});

test('addSubtaskToSprint links subtask to sprint', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $subtask = $this->createSubtask($this->hierarchy['task']);

    $this->service->addSubtaskToSprint($sprint, $subtask->id);

    expect($subtask->fresh()->sprint_id)->toBe($sprint->id);
});

test('addSubtaskToSprint throws for subtask not in project', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);

    // Create subtask in different project
    $otherHierarchy = $this->createFullHierarchy($this->owner, 'B');
    $otherSubtask = $this->createSubtask($otherHierarchy['task']);

    expect(fn () => $this->service->addSubtaskToSprint($sprint, $otherSubtask->id))
        ->toThrow(ValidationException::class);
});

test('removeSubtaskFromSprint unlinks subtask', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $subtask = $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id]);

    $this->service->removeSubtaskFromSprint($sprint, $subtask->id);

    expect($subtask->fresh()->sprint_id)->toBeNull();
});

test('getSprintStatistics returns correct counts', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id]);
    $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id, 'completed_at' => now()]);

    $stats = $this->service->getSprintStatistics($sprint);

    expect($stats['total_subtasks'])->toBe(2);
    expect($stats['completed_subtasks'])->toBe(1);
    expect($stats['completion_rate'])->toEqual(50);
});

test('getBacklogSubtasks returns subtasks without sprint', function () {
    $sprint = $this->createSprint($this->hierarchy['list']);
    $inSprint = $this->createSubtask($this->hierarchy['task'], ['sprint_id' => $sprint->id]);
    $backlog = $this->createSubtask($this->hierarchy['task'], ['name' => 'Backlog Item']);

    $result = $this->service->getBacklogSubtasks($this->hierarchy['list']);

    expect($result->pluck('id'))->toContain($backlog->id);
    expect($result->pluck('id'))->not->toContain($inSprint->id);
});
