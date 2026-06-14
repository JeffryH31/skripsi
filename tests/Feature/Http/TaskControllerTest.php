<?php

use App\Models\Label;
use App\Models\Project;
use App\Models\Space;
use App\Models\Task;
use App\Models\Workspace;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(CreatesWorkspaceHierarchy::class);

// Shared setup
beforeEach(function () {
    $this->owner = $this->createUser();
    $this->h = $this->createFullHierarchy($this->owner);

    // Use one of the auto-created statuses (Space::created observer creates Open/In Progress/Review/Completed)
    $this->status = $this->h['statuses']->firstWhere('type', 'in_progress')
        ?? $this->h['statuses']->first();
});

// store
test('owner can create a task', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list']]), [
            'name' => 'New Integration Task',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tasks', [
        'project_id' => $this->h['list']->id,
        'name' => 'New Integration Task',
    ]);
});

test('unauthenticated user is redirected when creating a task', function () {
    post(route('tasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list']]), [
        'name' => 'Sneaky Task',
    ])
        ->assertRedirectToRoute('login');
});

test('non-member gets 403 when creating a task', function () {
    $stranger = $this->createUser();

    // Give workspace membership but no project role — list has an owner, so open-by-default does not apply
    $this->h['workspace']->addMember($stranger, 'member');
    $this->h['list']->addMember($this->owner, 'project_owner'); // lock the list

    actingAs($stranger)
        ->post(route('tasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list']]), [
            'name' => 'Forbidden Task',
        ])
        ->assertForbidden();
});

test('creating a task without a name returns a validation error', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list']]), [])
        ->assertSessionHasErrors(['name']);
});

test('creating a task with due date before start date fails validation', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list']]), [
            'name' => 'Bad Dates',
            'start_date' => '2026-05-10',
            'due_date' => '2026-05-01',
        ])
        ->assertSessionHasErrors(['due_date']);
});

test('creating a task with equal start and due date passes validation', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list']]), [
            'name' => 'Same Dates',
            'start_date' => '2026-05-05',
            'due_date' => '2026-05-05',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tasks', ['name' => 'Same Dates']);
});

// update
test('owner can update a task', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->patch(route('tasks.update', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'name' => 'Renamed Task',
            'description' => 'Updated description',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tasks', [
        'id' => $this->h['task']->id,
        'name' => 'Renamed Task',
        'description' => 'Updated description',
    ]);
});

test('updating a task with a task from another list returns 404', function () {
    $otherList = Project::create([
        'space_id' => $this->h['space']->id,
        'name' => 'Other List',
        'created_by' => $this->owner->id,
    ]);
    $otherTask = Task::create([
        'project_id' => $otherList->id,
        'name' => 'Other Task',
        'created_by' => $this->owner->id,
    ]);

    actingAs($this->owner)
        ->patch(route('tasks.update', [$this->h['workspace'], $this->h['space'], $this->h['list'], $otherTask]))
        ->assertNotFound();
});

// destroy
test('owner can delete a task', function () {
    actingAs($this->owner)
        ->delete(route('tasks.destroy', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]))
        ->assertRedirect();

    $this->assertSoftDeleted('tasks', ['id' => $this->h['task']->id]);
});

test('non-member gets 403 when deleting a task', function () {
    $stranger = $this->createUser();
    $this->h['workspace']->addMember($stranger, 'member');
    $this->h['list']->addMember($this->owner, 'project_owner'); // lock the list

    actingAs($stranger)
        ->delete(route('tasks.destroy', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]))
        ->assertForbidden();
});

// changeStatus
test('owner can change task status', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->patch(route('tasks.change-status', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'status_id' => $this->status->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tasks', [
        'id' => $this->h['task']->id,
        'status_id' => $this->status->id,
    ]);
});

test('changing task status with invalid status_id returns validation error', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->patch(route('tasks.change-status', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'status_id' => 99999,
        ])
        ->assertSessionHasErrors(['status_id']);
});

// changePriority
test('owner can change task priority', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->patch(route('tasks.change-priority', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'priority_level' => 2,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('tasks', [
        'id' => $this->h['task']->id,
        'priority_level' => 2,
    ]);
});

test('invalid priority level returns validation error', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->patch(route('tasks.change-priority', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'priority_level' => 9,
        ])
        ->assertSessionHasErrors(['priority_level']);
});

// addLabel / removeLabel
test('owner can add a label to a task', function () {
    $label = $this->createLabel($this->h['workspace']);

    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.labels.add', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'label_id' => $label->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('task_labels', [
        'task_id' => $this->h['task']->id,
        'label_id' => $label->id,
    ]);
});

test('cannot attach a label from a different workspace', function () {
    $otherWorkspace = Workspace::create(['name' => 'Other WS', 'color' => '#000000']);
    $foreignLabel = Label::create(['workspace_id' => $otherWorkspace->id, 'name' => 'Foreign', 'color' => '#FF0000']);

    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.labels.add', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'label_id' => $foreignLabel->id,
        ])
        ->assertSessionHasErrors(['label_id']);
});

test('owner can remove a label from a task', function () {
    $label = $this->createLabel($this->h['workspace']);
    $this->h['task']->labels()->attach($label->id);

    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->delete(route('tasks.labels.remove', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'label_id' => $label->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseMissing('task_labels', [
        'task_id' => $this->h['task']->id,
        'label_id' => $label->id,
    ]);
});

// duplicate
test('owner can duplicate a task', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.duplicate', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]))
        ->assertRedirect();

    $this->assertDatabaseCount('tasks', 2);
});
