<?php

use App\Models\Label;
use App\Models\Project;
use App\Models\Space;
use App\Models\Task;
use App\Models\Workspace;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->wsOwner = $this->createUser();
    $this->projectOwner = $this->createUser();
    $this->projectManager = $this->createUser();
    $this->developer = $this->createUser();
    $this->guest = $this->createUser();

    // Build workspace hierarchy
    $this->workspace = Workspace::create(['name' => 'WS', 'color' => '#000']);
    $this->workspace->addMember($this->wsOwner, 'owner');
    $this->workspace->addMember($this->projectOwner, 'member');
    $this->workspace->addMember($this->projectManager, 'member');
    $this->workspace->addMember($this->developer, 'member');
    $this->workspace->addMember($this->guest, 'member');

    $this->space = Space::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Space',
        'created_by' => $this->wsOwner->id,
    ]);

    // Add all to space too so they can view it
    foreach ([$this->projectOwner, $this->projectManager, $this->developer, $this->guest] as $user) {
        $this->space->members()->attach($user->id, ['role' => 'member']);
    }

    $this->list = Project::create([
        'space_id' => $this->space->id,
        'name' => 'Project',
        'created_by' => $this->wsOwner->id,
    ]);

    // Assign project-level roles
    $this->list->addMember($this->projectOwner, 'project_owner');
    $this->list->addMember($this->projectManager, 'project_manager');
    $this->list->addMember($this->developer, 'development_team');
    $this->list->addMember($this->guest, 'guest');

    $this->task = Task::create([
        'project_id' => $this->list->id,
        'name' => 'Test Task',
        'created_by' => $this->wsOwner->id,
    ]);
});

// Create Task (canManageTaskStructure: project_owner & project_manager)
test('guest cannot create task', function () {
    actingAs($this->guest)
        ->post(route('tasks.store', [$this->workspace->id, $this->space->id, $this->list->id]), [
            'name' => 'Hack Task',
        ])
        ->assertForbidden();
});

test('developer cannot create task', function () {
    actingAs($this->developer)
        ->post(route('tasks.store', [$this->workspace->id, $this->space->id, $this->list->id]), [
            'name' => 'Dev Task',
        ])
        ->assertForbidden();
});

test('project_manager can create task', function () {
    actingAs($this->projectManager)
        ->post(route('tasks.store', [$this->workspace->id, $this->space->id, $this->list->id]), [
            'name' => 'Manager Task',
        ])
        ->assertRedirect();
});

test('project_owner can create task', function () {
    actingAs($this->projectOwner)
        ->post(route('tasks.store', [$this->workspace->id, $this->space->id, $this->list->id]), [
            'name' => 'Owner Task',
        ])
        ->assertRedirect();
});

test('workspace owner can create task without project role', function () {
    actingAs($this->wsOwner)
        ->post(route('tasks.store', [$this->workspace->id, $this->space->id, $this->list->id]), [
            'name' => 'WS Owner Task',
        ])
        ->assertRedirect();
});

// Delete Task
test('developer cannot delete task', function () {
    actingAs($this->developer)
        ->delete(route('tasks.destroy', [
            $this->workspace->id,
            $this->space->id,
            $this->list->id,
            $this->task->id,
        ]))
        ->assertForbidden();
});

test('guest cannot delete task', function () {
    actingAs($this->guest)
        ->delete(route('tasks.destroy', [
            $this->workspace->id,
            $this->space->id,
            $this->list->id,
            $this->task->id,
        ]))
        ->assertForbidden();
});

// Change Status (canOperateTasks: developer & up)
test('guest cannot change task status', function () {
    $status = $this->space->statuses()->first();

    actingAs($this->guest)
        ->patch(route('tasks.change-status', [
            $this->workspace->id,
            $this->space->id,
            $this->list->id,
            $this->task->id,
        ]), ['status_id' => $status->id])
        ->assertForbidden();
});

test('developer can change task status', function () {
    $status = $this->space->statuses()->skip(1)->first();

    actingAs($this->developer)
        ->patch(route('tasks.change-status', [
            $this->workspace->id,
            $this->space->id,
            $this->list->id,
            $this->task->id,
        ]), ['status_id' => $status->id])
        ->assertRedirect();
});

// Assign Tasks (canAssignTasks: project_owner & project_manager only)
test('developer cannot assign tasks', function () {
    $assignee = $this->createUser();
    $this->workspace->addMember($assignee, 'member');

    actingAs($this->developer)
        ->post(route('tasks.assign', [
            $this->workspace->id,
            $this->space->id,
            $this->list->id,
            $this->task->id,
        ]), ['user_id' => $assignee->id])
        ->assertForbidden();
});

// Manage Labels (canManageLabels = canManageTaskStructure)
test('developer cannot add label to task', function () {
    $label = Label::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Bug',
        'color' => '#EF4444',
    ]);

    actingAs($this->developer)
        ->post(route('tasks.labels.add', [
            $this->workspace->id,
            $this->space->id,
            $this->list->id,
            $this->task->id,
        ]), ['label_id' => $label->id])
        ->assertForbidden();
});

test('project_manager can add label to task', function () {
    $label = Label::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Feature',
        'color' => '#10B981',
    ]);

    actingAs($this->projectManager)
        ->post(route('tasks.labels.add', [
            $this->workspace->id,
            $this->space->id,
            $this->list->id,
            $this->task->id,
        ]), ['label_id' => $label->id])
        ->assertRedirect();
});

// Subtasks (canOperateTasks)
test('guest cannot create subtask', function () {
    actingAs($this->guest)
        ->post(route('tasks.subtasks.store', [
            $this->workspace->id,
            $this->space->id,
            $this->list->id,
            $this->task->id,
        ]), ['name' => 'New Subtask', 'task_id' => $this->task->id])
        ->assertForbidden();
});

test('developer cannot create subtask (only project_owner/manager can)', function () {
    actingAs($this->developer)
        ->post(route('tasks.subtasks.store', [
            $this->workspace->id,
            $this->space->id,
            $this->list->id,
            $this->task->id,
        ]), ['name' => 'Dev Subtask', 'task_id' => $this->task->id])
        ->assertForbidden();
});

test('project_manager can create subtask', function () {
    actingAs($this->projectManager)
        ->post(route('tasks.subtasks.store', [
            $this->workspace->id,
            $this->space->id,
            $this->list->id,
            $this->task->id,
        ]), ['name' => 'Manager Subtask', 'task_id' => $this->task->id])
        ->assertRedirect();
});
