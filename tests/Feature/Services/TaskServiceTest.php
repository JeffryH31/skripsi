<?php

use App\Enums\PriorityLevel;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new TaskService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('create task with name and description', function () {
    $task = $this->service->create([
        'name' => 'New Feature',
        'description' => 'Build the new feature',
    ], $this->hierarchy['list'], $this->owner);

    expect($task)->toBeInstanceOf(Task::class);
    expect($task->name)->toBe('New Feature');
    expect($task->description)->toBe('Build the new feature');
    expect($task->project_id)->toBe($this->hierarchy['list']->id);
});

test('create task logs created activity', function () {
    $this->service->create([
        'name' => 'Activity Test',
    ], $this->hierarchy['list'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Task::class)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['name'])->toBe('Activity Test');
});

test('delete task soft deletes and logs activity', function () {
    $task = $this->service->create([
        'name' => 'To Delete',
    ], $this->hierarchy['list'], $this->owner);

    $this->service->delete($task, $this->owner);

    expect(Task::find($task->id))->toBeNull();
    expect(Task::withTrashed()->find($task->id))->not->toBeNull();

    $activity = Activity::where('action', 'deleted')
        ->where('subject_type', Task::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('changeStatus updates status and logs activity', function () {
    $task = $this->service->create([
        'name' => 'Status Test',
    ], $this->hierarchy['list'], $this->owner);

    $inProgress = $this->hierarchy['statuses']->firstWhere('type', 'in_progress');

    $updated = $this->service->changeStatus($task, $inProgress, $this->owner);

    expect($updated->status_id)->toBe($inProgress->id);

    $activity = Activity::where('action', 'status_changed')->first();
    expect($activity)->not->toBeNull();
});

test('changePriority updates priority and logs activity', function () {
    $task = $this->service->create([
        'name' => 'Priority Test',
    ], $this->hierarchy['list'], $this->owner);

    $updated = $this->service->changePriority($task, PriorityLevel::High->value, $this->owner);

    expect($updated->priority_level)->toBe(PriorityLevel::High);

    $activity = Activity::where('action', 'priority_changed')->first();
    expect($activity)->not->toBeNull();
});

test('assign and unassign user to task', function () {
    $task = $this->service->create([
        'name' => 'Assignment Test',
    ], $this->hierarchy['list'], $this->owner);

    $assignee = $this->createUser();

    $assigned = $this->service->assign($task, $assignee, $this->owner);
    expect($assigned->assignees->pluck('id')->toArray())->toContain($assignee->id);

    $unassigned = $this->service->unassign($task->fresh(), $assignee, $this->owner);
    expect($unassigned->assignees->pluck('id')->toArray())->not->toContain($assignee->id);
});

test('assign logs assigned activity', function () {
    $task = $this->service->create([
        'name' => 'Assign Log Test',
    ], $this->hierarchy['list'], $this->owner);

    $assignee = $this->createUser();
    $this->service->assign($task, $assignee, $this->owner);

    $activity = Activity::where('action', 'assigned')->first();
    expect($activity)->not->toBeNull();
    expect($activity->properties['assignee_name'])->toBe($assignee->name);
});

test('move task to different list', function () {
    $task = $this->service->create([
        'name' => 'Movable Task',
    ], $this->hierarchy['list'], $this->owner);

    $newList = Project::create([
        'space_id' => $this->hierarchy['space']->id,
        'name' => 'New List',
        'created_by' => $this->owner->id,
    ]);

    $moved = $this->service->move($task, $newList, $this->owner);

    expect($moved->project_id)->toBe($newList->id);

    $activity = Activity::where('action', 'moved')
        ->where('subject_type', Task::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('reorder updates task positions', function () {
    $task1 = $this->service->create(['name' => 'Task 1'], $this->hierarchy['list'], $this->owner);
    $task2 = $this->service->create(['name' => 'Task 2'], $this->hierarchy['list'], $this->owner);

    $this->service->reorder($this->hierarchy['list'], [$task2->id, $task1->id]);

    expect($task2->fresh()->position)->toBe(0);
    expect($task1->fresh()->position)->toBe(1);
});

test('addLabel and removeLabel on task', function () {
    $task = $this->service->create(['name' => 'Label Test'], $this->hierarchy['list'], $this->owner);
    $label = $this->createLabel($this->hierarchy['workspace']);

    $labeled = $this->service->addLabel($task, $label, $this->owner);
    expect($labeled->labels->pluck('id')->toArray())->toContain($label->id);

    $unlabeled = $this->service->removeLabel($task->fresh(), $label, $this->owner);
    expect($unlabeled->labels->pluck('id')->toArray())->not->toContain($label->id);
});

test('duplicate task copies name, assignees, labels, and subtasks', function () {
    $task = $this->service->create(['name' => 'Original'], $this->hierarchy['list'], $this->owner);

    $assignee = $this->createUser();
    $task->assign($assignee);

    $label = $this->createLabel($this->hierarchy['workspace']);
    $task->addLabel($label);

    $this->createSubtask($task, ['name' => 'Sub A']);

    // Refresh to pick up relationships added outside Eloquent's cache
    $task = $task->fresh(['assignees', 'labels', 'subtasks']);

    $duplicate = $this->service->duplicate($task, $this->owner);

    expect($duplicate->name)->toBe('Original (Copy)');
    expect($duplicate->assignees)->toHaveCount(1);
    expect($duplicate->labels)->toHaveCount(1);
    expect($duplicate->subtasks)->toHaveCount(1);
});

test('getTasksForList returns tasks with relations', function () {
    $this->service->create(['name' => 'Task A'], $this->hierarchy['list'], $this->owner);
    $this->service->create(['name' => 'Task B'], $this->hierarchy['list'], $this->owner);

    $tasks = $this->service->getTasksForList($this->hierarchy['list']);

    // +1 for the task created in createFullHierarchy
    expect($tasks->count())->toBeGreaterThanOrEqual(2);
});

test('getTasksForList applies search filter', function () {
    $this->service->create(['name' => 'Alpha Feature'], $this->hierarchy['list'], $this->owner);
    $this->service->create(['name' => 'Beta Bug'], $this->hierarchy['list'], $this->owner);

    $tasks = $this->service->getTasksForList($this->hierarchy['list'], ['search' => 'Alpha']);

    expect($tasks->count())->toBe(1);
    expect($tasks->first()->name)->toBe('Alpha Feature');
});

test('getMyTasks returns tasks assigned to user', function () {
    $task = $this->service->create(['name' => 'My Task'], $this->hierarchy['list'], $this->owner);
    $task->assign($this->owner);

    $myTasks = $this->service->getMyTasks($this->owner);

    expect($myTasks->pluck('id')->toArray())->toContain($task->id);
});

test('getMySubtasks returns only subtasks assigned to user', function () {
    $task = $this->service->create(['name' => 'Parent Task'], $this->hierarchy['list'], $this->owner);
    // Task-level assignee but NO subtask-level assignee -> should NOT appear
    $task->assign($this->owner);

    $subtaskAssigned = $this->createSubtask($task, ['name' => 'Assigned Sub']);
    $subtaskAssigned->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $subtaskUnassigned = $this->createSubtask($task, ['name' => 'Unassigned Sub']);

    $mySubtasks = $this->service->getMySubtasks($this->owner);

    expect($mySubtasks->pluck('id')->toArray())->toContain($subtaskAssigned->id);
    expect($mySubtasks->pluck('id')->toArray())->not->toContain($subtaskUnassigned->id);
});

test('getMySubtasks excludes completed subtasks', function () {
    $task = $this->service->create(['name' => 'Parent'], $this->hierarchy['list'], $this->owner);

    $active = $this->createSubtask($task, ['name' => 'Active']);
    $active->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $done = $this->createSubtask($task, ['name' => 'Done', 'completed_at' => now()]);
    $done->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $mySubtasks = $this->service->getMySubtasks($this->owner);

    expect($mySubtasks->pluck('id')->toArray())->toContain($active->id);
    expect($mySubtasks->pluck('id')->toArray())->not->toContain($done->id);
});

test('getMySubtasks with is_overdue filter returns only overdue subtasks', function () {
    $task = $this->service->create(['name' => 'Parent'], $this->hierarchy['list'], $this->owner);

    $overdue = $this->createSubtask($task, [
        'name' => 'Overdue',
        'due_date' => now()->subDays(3),
    ]);
    $overdue->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $future = $this->createSubtask($task, [
        'name' => 'Future',
        'due_date' => now()->addDays(5),
    ]);
    $future->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $noDue = $this->createSubtask($task, ['name' => 'No Due']);
    $noDue->assignees()->attach($this->owner->id, ['assigned_by' => $this->owner->id]);

    $overdueSubtasks = $this->service->getMySubtasks($this->owner, ['is_overdue' => true]);

    expect($overdueSubtasks->pluck('id')->toArray())->toContain($overdue->id);
    expect($overdueSubtasks->pluck('id')->toArray())->not->toContain($future->id);
    expect($overdueSubtasks->pluck('id')->toArray())->not->toContain($noDue->id);
});

test('getTaskWithRelations loads all expected relations', function () {
    $task = $this->hierarchy['task'];

    // Add a subtask so the subtasks relation is non-empty
    $this->createSubtask($task, ['name' => 'Sub 1']);

    $loaded = $this->service->getTaskWithRelations($task);

    expect($loaded->relationLoaded('project'))->toBeTrue();
    expect($loaded->relationLoaded('status'))->toBeTrue();
    expect($loaded->relationLoaded('assignees'))->toBeTrue();
    expect($loaded->relationLoaded('labels'))->toBeTrue();
    expect($loaded->relationLoaded('subtasks'))->toBeTrue();
    expect($loaded->relationLoaded('dependencies'))->toBeTrue();
    expect($loaded->relationLoaded('dependents'))->toBeTrue();
    expect($loaded->relationLoaded('comments'))->toBeTrue();
    expect($loaded->relationLoaded('activities'))->toBeTrue();
    expect($loaded->relationLoaded('creator'))->toBeTrue();
    expect($loaded->project->relationLoaded('space'))->toBeTrue();
    expect($loaded->subtasks)->toHaveCount(1);
});

// ============================================================
// Merged from Unit/Services/TaskServiceTest.php
// ============================================================
test('create task with name', function () {
    $task = $this->service->create(
        ['name' => 'New Feature'],
        $this->hierarchy['list'],
        $this->owner
    );

    expect($task)->toBeInstanceOf(Task::class);
    expect($task->name)->toBe('New Feature');
    expect($task->project_id)->toBe($this->hierarchy['list']->id);
    expect($task->created_by)->toBe($this->owner->id);
});

test('create task with all optional fields', function () {
    $status = $this->hierarchy['statuses']->first();

    $task = $this->service->create([
        'name' => 'Full Task',
        'description' => 'A description',
        'status_id' => $status->id,
        'priority_level' => 2,
        'start_date' => '2026-06-01',
        'due_date' => '2026-06-15',
        'time_estimate' => 480,
    ], $this->hierarchy['list'], $this->owner);

    expect($task->description)->toBe('A description');
    expect($task->status_id)->toBe($status->id);
    expect($task->time_estimate)->toBe(480);
});

test('create task syncs assignees', function () {
    $assignee = $this->createUser();

    $task = $this->service->create([
        'name' => 'Assigned Task',
        'assignee_ids' => [$assignee->id],
    ], $this->hierarchy['list'], $this->owner);

    expect($task->assignees)->toHaveCount(1);
    expect($task->assignees->first()->id)->toBe($assignee->id);
});

test('create task syncs labels', function () {
    $label = $this->createLabel($this->hierarchy['workspace']);

    $task = $this->service->create([
        'name' => 'Labeled Task',
        'label_ids' => [$label->id],
    ], $this->hierarchy['list'], $this->owner);

    expect($task->labels)->toHaveCount(1);
});

test('update task name and description', function () {
    $task = $this->service->create(['name' => 'Old'], $this->hierarchy['list'], $this->owner);

    $updated = $this->service->update($task, [
        'name' => 'New Name',
        'description' => 'New Desc',
    ], $this->owner);

    expect($updated->name)->toBe('New Name');
    expect($updated->description)->toBe('New Desc');
});

test('update task logs changes activity', function () {
    $task = $this->service->create(['name' => 'Original'], $this->hierarchy['list'], $this->owner);

    $this->service->update($task, ['name' => 'Changed'], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Task::class)
        ->where('subject_id', $task->id)
        ->first();

    expect($activity)->not->toBeNull();
});

test('update task status logs status_changed', function () {
    $task = $this->service->create(['name' => 'Status Test'], $this->hierarchy['list'], $this->owner);
    $inProgress = $this->hierarchy['statuses']->firstWhere('type', 'in_progress');

    $this->service->update($task, ['status_id' => $inProgress->id], $this->owner);

    $activity = Activity::where('action', 'status_changed')
        ->where('subject_id', $task->id)
        ->first();

    expect($activity)->not->toBeNull();
});

test('update task priority logs priority_changed', function () {
    $task = $this->service->create(['name' => 'Priority Test'], $this->hierarchy['list'], $this->owner);

    $this->service->update($task, ['priority_level' => 1], $this->owner);

    $activity = Activity::where('action', 'priority_changed')
        ->where('subject_id', $task->id)
        ->first();

    expect($activity)->not->toBeNull();
});

test('assign and unassign user', function () {
    $task = $this->service->create(['name' => 'Assign Test'], $this->hierarchy['list'], $this->owner);
    $assignee = $this->createUser();

    $assigned = $this->service->assign($task, $assignee, $this->owner);
    expect($assigned->assignees->pluck('id'))->toContain($assignee->id);

    $unassigned = $this->service->unassign($assigned, $assignee, $this->owner);
    expect($unassigned->assignees->pluck('id'))->not->toContain($assignee->id);
});

test('reorder tasks updates positions', function () {
    $t1 = $this->service->create(['name' => 'Task 1'], $this->hierarchy['list'], $this->owner);
    $t2 = $this->service->create(['name' => 'Task 2'], $this->hierarchy['list'], $this->owner);

    $this->service->reorder($this->hierarchy['list'], [$t2->id, $t1->id]);

    expect($t2->fresh()->position)->toBe(0);
    expect($t1->fresh()->position)->toBe(1);
});

test('addLabel and removeLabel work correctly', function () {
    $task = $this->service->create(['name' => 'Label Test'], $this->hierarchy['list'], $this->owner);
    $label = $this->createLabel($this->hierarchy['workspace']);

    $labeled = $this->service->addLabel($task, $label, $this->owner);
    expect($labeled->labels->pluck('id'))->toContain($label->id);

    $unlabeled = $this->service->removeLabel($labeled, $label, $this->owner);
    expect($unlabeled->labels->pluck('id'))->not->toContain($label->id);
});

test('duplicate task creates copy with "(Copy)" suffix', function () {
    $task = $this->service->create(['name' => 'Original'], $this->hierarchy['list'], $this->owner);
    $task = $task->fresh(['assignees', 'labels', 'subtasks']);

    $copy = $this->service->duplicate($task, $this->owner);

    expect($copy->name)->toBe('Original (Copy)');
    expect($copy->project_id)->toBe($task->project_id);
});

test('applyFilters filters by status_ids', function () {
    $open = $this->hierarchy['statuses']->firstWhere('type', 'open');
    $closed = $this->hierarchy['statuses']->firstWhere('type', 'closed');

    $this->service->create(['name' => 'Open Task',   'status_id' => $open->id], $this->hierarchy['list'], $this->owner);
    $this->service->create(['name' => 'Closed Task', 'status_id' => $closed->id], $this->hierarchy['list'], $this->owner);

    $results = $this->service->getTasksForList($this->hierarchy['list'], ['status_ids' => [$open->id]]);

    expect($results->pluck('status_id')->unique()->values()->toArray())->toBe([$open->id]);
});

test('applyFilters filters by priority_levels', function () {
    $this->service->create(['name' => 'Urgent Task',  'priority_level' => 1], $this->hierarchy['list'], $this->owner);
    $this->service->create(['name' => 'Low Task',     'priority_level' => 4], $this->hierarchy['list'], $this->owner);

    $results = $this->service->getTasksForList($this->hierarchy['list'], ['priority_levels' => [1]]);

    expect($results->pluck('name')->toArray())->toContain('Urgent Task');
    expect($results->pluck('name')->toArray())->not->toContain('Low Task');
});

test('applyFilters filters by search term', function () {
    $this->service->create(['name' => 'Fix login bug'], $this->hierarchy['list'], $this->owner);
    $this->service->create(['name' => 'Add dashboard'], $this->hierarchy['list'], $this->owner);

    $results = $this->service->getTasksForList($this->hierarchy['list'], ['search' => 'login']);

    expect($results->pluck('name')->toArray())->toContain('Fix login bug');
    expect($results->pluck('name')->toArray())->not->toContain('Add dashboard');
});

test('applyFilters returns all tasks when no filters applied', function () {
    $this->service->create(['name' => 'Task A'], $this->hierarchy['list'], $this->owner);
    $this->service->create(['name' => 'Task B'], $this->hierarchy['list'], $this->owner);

    $results = $this->service->getTasksForList($this->hierarchy['list'], []);

    // +1 for task created in createFullHierarchy
    expect($results->count())->toBeGreaterThanOrEqual(2);
});
