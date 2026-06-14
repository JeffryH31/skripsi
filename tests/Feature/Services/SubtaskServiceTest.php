<?php

use App\Enums\PriorityLevel;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Services\SubtaskService;
use Illuminate\Validation\ValidationException;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new SubtaskService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('create subtask with correct fields', function () {
    $subtask = $this->service->create([
        'name' => 'Implement login',
        'description' => 'Build login page',
    ], $this->hierarchy['task'], $this->owner);

    expect($subtask)->toBeInstanceOf(Subtask::class);
    expect($subtask->name)->toBe('Implement login');
    expect($subtask->description)->toBe('Build login page');
    expect($subtask->task_id)->toBe($this->hierarchy['task']->id);
    expect($subtask->created_by)->toBe($this->owner->id);
});

test('create subtask logs created activity', function () {
    $subtask = $this->service->create([
        'name' => 'New subtask',
    ], $this->hierarchy['task'], $this->owner);

    $activity = Activity::where('subject_type', Subtask::class)
        ->where('subject_id', $subtask->id)
        ->where('action', 'created')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['name'])->toBe('New subtask');
    expect($activity->user_id)->toBe($this->owner->id);
});

test('create subtask auto-populates baseline dates from start/due', function () {
    $subtask = $this->service->create([
        'name' => 'Dated subtask',
        'start_date' => '2026-05-01',
        'due_date' => '2026-05-15',
    ], $this->hierarchy['task'], $this->owner);

    expect($subtask->baseline_start_date->toDateString())->toBe('2026-05-01');
    expect($subtask->baseline_due_date->toDateString())->toBe('2026-05-15');
});

test('create subtask syncs assignees and labels', function () {
    $assignee = $this->createUser();
    $label = $this->createLabel($this->hierarchy['workspace']);

    $subtask = $this->service->create([
        'name' => 'Team subtask',
        'assignee_ids' => [$assignee->id],
        'label_ids' => [$label->id],
    ], $this->hierarchy['task'], $this->owner);

    expect($subtask->assignees)->toHaveCount(1);
    expect($subtask->assignees->first()->id)->toBe($assignee->id);
    expect($subtask->labels)->toHaveCount(1);
    expect($subtask->labels->first()->id)->toBe($label->id);
});

test('update tracks field changes in activity log', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Original',
    ]);

    $this->service->update($subtask, [
        'name' => 'Updated Name',
    ], $this->owner);

    $activity = Activity::where('subject_type', Subtask::class)
        ->where('subject_id', $subtask->id)
        ->where('action', 'updated')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->changes['name']['old'])->toBe('Original');
    expect($activity->changes['name']['new'])->toBe('Updated Name');
});

test('update assignees logs assigned and unassigned activities', function () {
    $user1 = $this->createUser();
    $user2 = $this->createUser();

    $subtask = $this->createSubtask($this->hierarchy['task']);
    $subtask->assignees()->attach($user1->id, [
        'assigned_by' => $this->owner->id,
    ]);

    $this->service->update($subtask, [
        'assignee_ids' => [$user2->id],
    ], $this->owner);

    $assigned = Activity::where('subject_id', $subtask->id)
        ->where('subject_type', Subtask::class)
        ->where('action', 'assigned')
        ->first();

    $unassigned = Activity::where('subject_id', $subtask->id)
        ->where('subject_type', Subtask::class)
        ->where('action', 'unassigned')
        ->first();

    expect($assigned)->not->toBeNull();
    expect($assigned->properties['assignee_name'])->toBe($user2->name);
    expect($unassigned)->not->toBeNull();
    expect($unassigned->properties['assignee_name'])->toBe($user1->name);
});

test('update status change logs status_changed with status names', function () {
    $statuses = $this->hierarchy['statuses'];
    $openStatus = $statuses->firstWhere('type', 'open');
    $closedStatus = $statuses->firstWhere('type', 'closed');

    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'status_id' => $openStatus->id,
    ]);

    $this->service->update($subtask, [
        'status_id' => $closedStatus->id,
    ], $this->owner);

    $activity = Activity::where('subject_id', $subtask->id)
        ->where('subject_type', Subtask::class)
        ->where('action', 'status_changed')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->changes['status']['old'])->toBe('Open');
    expect($activity->changes['status']['new'])->toBe('Completed');
});

test('update priority change logs priority_changed with labels', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'priority_level' => PriorityLevel::Low->value,
    ]);

    $this->service->update($subtask, [
        'priority_level' => PriorityLevel::High->value,
    ], $this->owner);

    $activity = Activity::where('subject_id', $subtask->id)
        ->where('subject_type', Subtask::class)
        ->where('action', 'priority_changed')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->changes['priority']['old'])->toBe('Low');
    expect($activity->changes['priority']['new'])->toBe('High');
});

test('update sprint_id rejects sprint from different product', function () {
    $otherList = Project::create([
        'space_id' => $this->hierarchy['space']->id,
        'name' => 'Other List',
        'created_by' => $this->owner->id,
    ]);

    $foreignSprint = $this->createSprint($otherList, ['name' => 'Foreign Sprint']);
    $subtask = $this->createSubtask($this->hierarchy['task']);

    expect(fn () => $this->service->update($subtask, [
        'sprint_id' => $foreignSprint->id,
    ], $this->owner))->toThrow(\Illuminate\Validation\ValidationException::class);
});

test('delete soft-deletes subtask and logs deleted_subtask activity', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'To Delete',
    ]);

    $this->service->delete($subtask, $this->owner);

    expect(Subtask::find($subtask->id))->toBeNull();
    expect(Subtask::withTrashed()->find($subtask->id))->not->toBeNull();

    $activity = Activity::where('action', 'deleted_subtask')
        ->where('subject_type', Task::class)
        ->where('subject_id', $this->hierarchy['task']->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['name'])->toBe('To Delete');
});

test('update with no actual changes does not log activity', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Same Name',
    ]);

    $countBefore = Activity::count();

    $this->service->update($subtask, [
        'name' => 'Same Name',
    ], $this->owner);

    expect(Activity::count())->toBe($countBefore);
});

test('reorder updates subtask positions', function () {
    $a = $this->createSubtask($this->hierarchy['task'], ['name' => 'First']);
    $b = $this->createSubtask($this->hierarchy['task'], ['name' => 'Second']);
    $c = $this->createSubtask($this->hierarchy['task'], ['name' => 'Third']);

    // Reorder: C, A, B
    $this->service->reorder($this->hierarchy['task'], [$c->id, $a->id, $b->id]);

    expect($c->fresh()->position)->toBe(0);
    expect($a->fresh()->position)->toBe(1);
    expect($b->fresh()->position)->toBe(2);
});

// ============================================================
// Merged from Unit/Services/SubtaskServiceTest.php
// ============================================================
test('create subtask with name only', function () {
    $subtask = $this->service->create(
        ['name' => 'Write tests'],
        $this->hierarchy['task'],
        $this->owner
    );

    expect($subtask)->toBeInstanceOf(Subtask::class);
    expect($subtask->name)->toBe('Write tests');
    expect($subtask->task_id)->toBe($this->hierarchy['task']->id);
    expect($subtask->created_by)->toBe($this->owner->id);
});

test('create subtask with status_id', function () {
    $status = $this->hierarchy['statuses']->first();

    $subtask = $this->service->create([
        'name' => 'Subtask With Status',
        'status_id' => $status->id,
    ], $this->hierarchy['task'], $this->owner);

    expect($subtask->status_id)->toBe($status->id);
});

test('create subtask with parent_id (nested)', function () {
    $parent = $this->service->create(
        ['name' => 'Parent Subtask'],
        $this->hierarchy['task'],
        $this->owner
    );

    $child = $this->service->create([
        'name' => 'Child Subtask',
        'parent_id' => $parent->id,
    ], $this->hierarchy['task'], $this->owner);

    expect($child->parent_id)->toBe($parent->id);
    expect($child->task_id)->toBe($this->hierarchy['task']->id);
});

test('create subtask logs activity', function () {
    $this->service->create(
        ['name' => 'Activity Subtask'],
        $this->hierarchy['task'],
        $this->owner
    );

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Subtask::class)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['name'])->toBe('Activity Subtask');
});

test('create subtask validates max nesting depth', function () {
    $task = $this->hierarchy['task'];

    // Build chain from depth 0 up to MAX_DEPTH (inclusive)
    $parent = $this->service->create(['name' => 'Level 0'], $task, $this->owner);
    for ($i = 1; $i <= Subtask::MAX_DEPTH; $i++) {
        $parent = $this->service->create(
            ['name' => "Level $i", 'parent_id' => $parent->id],
            $task,
            $this->owner
        );
    }

    // $parent is now at depth MAX_DEPTH — adding a child must throw
    expect(fn () => $this->service->create(
        ['name' => 'Too Deep', 'parent_id' => $parent->id],
        $task,
        $this->owner
    ))->toThrow(ValidationException::class);
});

test('update subtask name', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Old Name']);

    $updated = $this->service->update($subtask, ['name' => 'New Name'], $this->owner);

    expect($updated->name)->toBe('New Name');
});

test('update subtask description', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Has Desc']);

    $updated = $this->service->update($subtask, ['description' => 'Updated description'], $this->owner);

    expect($updated->description)->toBe('Updated description');
});

test('update subtask priority', function () {
    $subtask = $this->createSubtask($this->hierarchy['task']);

    $updated = $this->service->update($subtask, ['priority_level' => 2], $this->owner);

    expect($updated->priority_level->value)->toBe(2);
});

test('update subtask dates and time estimate', function () {
    $subtask = $this->createSubtask($this->hierarchy['task']);

    $updated = $this->service->update($subtask, [
        'start_date' => '2026-07-01',
        'due_date' => '2026-07-15',
        'time_estimate' => 180,
    ], $this->owner);

    expect($updated->start_date->format('Y-m-d'))->toBe('2026-07-01');
    expect($updated->due_date->format('Y-m-d'))->toBe('2026-07-15');
    expect($updated->time_estimate)->toBe(180);
});

test('update subtask logs activity with changes', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Original']);

    $this->service->update($subtask, ['name' => 'Changed'], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Subtask::class)
        ->where('subject_id', $subtask->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->changes['name']['old'])->toBe('Original');
    expect($activity->changes['name']['new'])->toBe('Changed');
});

test('update subtask status logs status_changed activity', function () {
    $subtask = $this->createSubtask($this->hierarchy['task']);
    $inProgress = $this->hierarchy['statuses']->firstWhere('type', 'in_progress');

    $this->service->update($subtask, ['status_id' => $inProgress->id], $this->owner);

    $activity = Activity::where('action', 'status_changed')
        ->where('subject_id', $subtask->id)
        ->first();

    expect($activity)->not->toBeNull();
});

test('update subtask priority logs priority_changed activity', function () {
    $subtask = $this->createSubtask($this->hierarchy['task']);

    $this->service->update($subtask, ['priority_level' => 1], $this->owner);

    $activity = Activity::where('action', 'priority_changed')
        ->where('subject_id', $subtask->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->changes['priority']['new'])->toBe('Urgent');
});

test('update subtask assignees logs assigned and unassigned activities', function () {
    $user1 = $this->createUser();
    $user2 = $this->createUser();

    $subtask = $this->createSubtask($this->hierarchy['task']);
    $subtask->assignees()->attach($user1->id, ['assigned_by' => $this->owner->id]);
    $subtask = $subtask->fresh('assignees');

    $this->service->update($subtask, ['assignee_ids' => [$user2->id]], $this->owner);

    $assigned = Activity::where('action', 'assigned')->where('subject_id', $subtask->id)->first();
    $unassigned = Activity::where('action', 'unassigned')->where('subject_id', $subtask->id)->first();

    expect($assigned)->not->toBeNull();
    expect($assigned->properties['assignee_name'])->toBe($user2->name);
    expect($unassigned)->not->toBeNull();
    expect($unassigned->properties['assignee_name'])->toBe($user1->name);
});

test('update subtask syncs labels', function () {
    $label = $this->createLabel($this->hierarchy['workspace']);
    $subtask = $this->createSubtask($this->hierarchy['task']);

    $this->service->update($subtask, ['label_ids' => [$label->id]], $this->owner);

    expect($subtask->fresh('labels')->labels)->toHaveCount(1);
});

test('complete subtask sets completed_at', function () {
    $subtask = $this->createSubtask($this->hierarchy['task']);

    $completed = $this->service->complete($subtask, $this->owner);

    expect($completed->completed_at)->not->toBeNull();
});

test('complete subtask logs completed activity', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Finish Me']);

    $this->service->complete($subtask, $this->owner);

    $activity = Activity::where('action', 'completed')
        ->where('subject_id', $subtask->id)
        ->first();

    expect($activity)->not->toBeNull();
});

test('reopen subtask clears completed_at', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['completed_at' => now()]);

    $reopened = $this->service->reopen($subtask, $this->owner);

    expect($reopened->completed_at)->toBeNull();
});

test('reopen subtask logs reopened activity', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['completed_at' => now()]);

    $this->service->reopen($subtask, $this->owner);

    $activity = Activity::where('action', 'reopened')
        ->where('subject_id', $subtask->id)
        ->first();

    expect($activity)->not->toBeNull();
});

test('delete subtask soft deletes it', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'To Delete']);

    $this->service->delete($subtask, $this->owner);

    expect(Subtask::find($subtask->id))->toBeNull();
    expect(Subtask::withTrashed()->find($subtask->id))->not->toBeNull();
});

test('delete subtask logs activity on parent task', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Deleted One']);

    $this->service->delete($subtask, $this->owner);

    $activity = Activity::where('action', 'deleted_subtask')
        ->where('subject_type', Task::class)
        ->where('subject_id', $this->hierarchy['task']->id)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['name'])->toBe('Deleted One');
});

test('duplicate subtask creates copy with "(Copy)" suffix', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Original']);
    $subtask = $subtask->fresh(['assignees', 'labels']);

    $copy = $this->service->duplicate($subtask, $this->owner);

    expect($copy->name)->toBe('Original (Copy)');
    expect($copy->id)->not->toBe($subtask->id);
    expect($copy->task_id)->toBe($subtask->task_id);
});

test('duplicate subtask copies labels but NOT assignees', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'With Relations']);
    $assignee = $this->createUser();
    $label = $this->createLabel($this->hierarchy['workspace']);

    $subtask->assignees()->attach($assignee->id, ['assigned_by' => $this->owner->id]);
    $subtask->labels()->attach($label->id);
    $subtask = $subtask->fresh(['assignees', 'labels']);

    $copy = $this->service->duplicate($subtask, $this->owner);
    $copy = $copy->fresh(['assignees', 'labels']);

    expect($copy->labels)->toHaveCount(1);
    expect($copy->assignees)->toHaveCount(0);
});

test('duplicate subtask does not copy completed_at or time_spent', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Completed',
        'completed_at' => now(),
        'time_spent' => 120,
    ]);
    $subtask = $subtask->fresh(['assignees', 'labels']);

    $copy = $this->service->duplicate($subtask, $this->owner);

    expect($copy->completed_at)->toBeNull();
    expect($copy->time_spent)->toBeNull();
});

test('duplicate subtask does not copy dates or sprint', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'With Dates',
        'start_date' => now()->subDays(5),
        'due_date' => now()->addDays(5),
    ]);
    $subtask = $subtask->fresh(['assignees', 'labels']);

    $copy = $this->service->duplicate($subtask, $this->owner);

    expect($copy->start_date)->toBeNull();
    expect($copy->due_date)->toBeNull();
    expect($copy->sprint_id)->toBeNull();
});
