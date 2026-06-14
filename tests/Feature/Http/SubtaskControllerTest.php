<?php

use App\Models\Subtask;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;

uses(CreatesWorkspaceHierarchy::class);

// Shared setup
beforeEach(function () {
    $this->owner = $this->createUser();
    $this->h = $this->createFullHierarchy($this->owner);
    $this->subtask = $this->createSubtask($this->h['task']);

    // Use auto-created statuses from Space::created observer
    $this->openStatus = $this->h['statuses']->firstWhere('type', 'open')
        ?? $this->h['statuses']->first();
    $this->closedStatus = $this->h['statuses']->firstWhere('type', 'closed')
        ?? $this->h['statuses']->last();
});

// store
test('owner can create a subtask', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.subtasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'task_id' => $this->h['task']->id,
            'name' => 'New Subtask',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('subtasks', [
        'task_id' => $this->h['task']->id,
        'name' => 'New Subtask',
    ]);
});

test('unauthenticated user is redirected when creating a subtask', function () {
    $this->post(route('tasks.subtasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
        'task_id' => $this->h['task']->id,
        'name' => 'Ghost Subtask',
    ])
        ->assertRedirectToRoute('login');
});

test('creating a subtask without a name returns a validation error', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.subtasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [])
        ->assertSessionHasErrors(['name']);
});

test('creating a subtask with more than one assignee returns a validation error', function () {
    $userA = $this->createUser();
    $userB = $this->createUser();

    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.subtasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'task_id' => $this->h['task']->id,
            'name' => 'Multi Assignee',
            'assignee_ids' => [$userA->id, $userB->id],
        ])
        ->assertSessionHasErrors(['assignee_ids']);
});

test('creating a subtask with exactly one assignee succeeds', function () {
    $assignee = $this->createUser();

    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.subtasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'task_id' => $this->h['task']->id,
            'name' => 'Single Assignee',
            'assignee_ids' => [$assignee->id],
        ])
        ->assertRedirect();

    $created = Subtask::where('name', 'Single Assignee')->firstOrFail();
    expect($created->assignees)->toHaveCount(1);
});

test('creating subtask with due date before start date fails validation', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.subtasks.store', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task']]), [
            'task_id' => $this->h['task']->id,
            'name' => 'Bad Dates',
            'start_date' => '2026-05-10',
            'due_date' => '2026-05-01',
        ])
        ->assertSessionHasErrors();
});

// update
test('owner can update a subtask name and description', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->patch(route('tasks.subtasks.update', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task'], $this->subtask]), [
            'name' => 'Renamed Subtask',
            'description' => 'Some detail',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('subtasks', [
        'id' => $this->subtask->id,
        'name' => 'Renamed Subtask',
        'description' => 'Some detail',
    ]);
});

test('developer (project member) can update a subtask', function () {
    $dev = $this->createUser();
    $this->h['workspace']->addMember($dev, 'member');
    $this->h['list']->addMember($dev, 'development_team');

    actingAs($dev)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->patch(route('tasks.subtasks.update', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task'], $this->subtask]), [
            'name' => 'Dev Updated',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('subtasks', ['id' => $this->subtask->id, 'name' => 'Dev Updated']);
});

test('non-member gets 403 when updating a subtask', function () {
    $stranger = $this->createUser();
    $this->h['workspace']->addMember($stranger, 'member');
    $this->h['list']->addMember($this->owner, 'project_owner'); // lock the list

    actingAs($stranger)
        ->patch(route('tasks.subtasks.update', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task'], $this->subtask]), [
            'name' => 'Forbidden',
        ])
        ->assertForbidden();
});

// complete
test('owner can complete a subtask', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.subtasks.complete', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task'], $this->subtask]))
        ->assertRedirect();

    $this->assertDatabaseHas('subtasks', [
        'id' => $this->subtask->id,
        'completed_at' => now()->toDateTimeString(),
    ]);
});

test('completing a subtask sets its status to the provided closed status', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.subtasks.complete', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task'], $this->subtask]), [
            'target_status_id' => $this->closedStatus->id,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('subtasks', [
        'id' => $this->subtask->id,
        'status_id' => $this->closedStatus->id,
    ]);
});

// reopen
test('owner can reopen a completed subtask', function () {
    $this->subtask->update(['completed_at' => now()]);

    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.subtasks.reopen', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task'], $this->subtask]))
        ->assertRedirect();

    $this->assertDatabaseHas('subtasks', [
        'id' => $this->subtask->id,
        'completed_at' => null,
    ]);
});

// destroy
test('owner can delete a subtask', function () {
    actingAs($this->owner)
        ->delete(route('tasks.subtasks.destroy', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task'], $this->subtask]))
        ->assertRedirect();

    $this->assertSoftDeleted('subtasks', ['id' => $this->subtask->id]);
});

test('developer cannot delete a subtask (requires canManageTaskStructure)', function () {
    $dev = $this->createUser();
    $this->h['workspace']->addMember($dev, 'member');
    $this->h['list']->addMember($dev, 'development_team');

    actingAs($dev)
        ->delete(route('tasks.subtasks.destroy', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task'], $this->subtask]))
        ->assertForbidden();
});

// duplicate
test('owner can duplicate a subtask', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.subtasks.duplicate', [$this->h['workspace'], $this->h['space'], $this->h['list'], $this->h['task'], $this->subtask]))
        ->assertRedirect();

    expect(Subtask::where('task_id', $this->h['task']->id)->count())->toBe(2);
});
