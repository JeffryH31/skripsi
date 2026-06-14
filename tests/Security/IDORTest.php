<?php

use App\Models\Comment;
use App\Models\Project;
use App\Models\Space;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->userA = $this->createUser();
    $this->userB = $this->createUser();
});

// Cross-User Comment Manipulation
test('user cannot update another users comment', function () {
    $hierarchy = $this->createFullHierarchy($this->userA);
    $hierarchy['workspace']->addMember($this->userB, 'member');

    // User A creates comment
    $comment = Comment::create([
        'task_id' => $hierarchy['task']->id,
        'user_id' => $this->userA->id,
        'content' => 'User A comment',
    ]);

    // User B tries to edit it
    actingAs($this->userB)
        ->patch(route('comments.update', $comment->id), ['content' => 'HACKED'])
        ->assertSessionHasErrors(['error']);

    // Verify comment was NOT updated
    expect($comment->fresh()->content)->toBe('User A comment');
});

test('user cannot delete another users comment', function () {
    $hierarchy = $this->createFullHierarchy($this->userA);
    $hierarchy['workspace']->addMember($this->userB, 'member');

    $comment = Comment::create([
        'task_id' => $hierarchy['task']->id,
        'user_id' => $this->userA->id,
        'content' => 'User A private',
    ]);

    actingAs($this->userB)
        ->delete(route('comments.destroy', $comment->id))
        ->assertSessionHasErrors(['error']);

    // Verify comment still exists
    expect(Comment::find($comment->id))->not->toBeNull();
});

test('user can update their own comment', function () {
    $hierarchy = $this->createFullHierarchy($this->userA);

    $comment = Comment::create([
        'task_id' => $hierarchy['task']->id,
        'user_id' => $this->userA->id,
        'content' => 'My comment',
    ]);

    actingAs($this->userA)
        ->patch(route('comments.update', $comment->id), ['content' => 'Updated'])
        ->assertRedirect();
});

test('workspace admin can moderate any comment', function () {
    $hierarchy = $this->createFullHierarchy($this->userA);
    $hierarchy['workspace']->addMember($this->userB, 'admin');

    $comment = Comment::create([
        'task_id' => $hierarchy['task']->id,
        'user_id' => $this->userA->id,
        'content' => 'User A comment',
    ]);

    actingAs($this->userB)
        ->delete(route('comments.destroy', $comment->id))
        ->assertRedirect();
});

// Cross-User Time Entry Manipulation
test('user cannot update another users time entry', function () {
    $hierarchy = $this->createFullHierarchy($this->userA);
    $hierarchy['workspace']->addMember($this->userB, 'member');

    $subtask = $this->createSubtask($hierarchy['task']);

    $entry = TimeEntry::create([
        'subtask_id' => $subtask->id,
        'user_id' => $this->userA->id,
        'duration' => 60,
        'started_at' => now(),
        'ended_at' => now()->addHour(),
    ]);

    actingAs($this->userB)
        ->patch(route('time-entries.update', $entry->id), ['duration' => 120])
        ->assertSessionHasErrors(['error']);

    // Verify duration was NOT updated
    expect($entry->fresh()->duration)->toBe(60);
});

test('user can update their own time entry', function () {
    $hierarchy = $this->createFullHierarchy($this->userA);

    $subtask = $this->createSubtask($hierarchy['task']);

    $entry = TimeEntry::create([
        'subtask_id' => $subtask->id,
        'user_id' => $this->userA->id,
        'duration' => 60,
        'started_at' => now(),
        'ended_at' => now()->addHour(),
    ]);

    actingAs($this->userA)
        ->patch(route('time-entries.update', $entry->id), ['duration' => 90])
        ->assertRedirect();
});

// Cross-Workspace Resource Access
test('user cannot update task in workspace they have no access to', function () {
    $hierarchyA = $this->createFullHierarchy($this->userA, 'A');
    $hierarchyB = $this->createFullHierarchy($this->userB, 'B');

    // User A tries to access User B's task using User B's workspace IDs
    actingAs($this->userA)
        ->patch(route('tasks.update', [
            $hierarchyB['workspace']->id,
            $hierarchyB['space']->id,
            $hierarchyB['list']->id,
            $hierarchyB['task']->id,
        ]), ['name' => 'Hijacked'])
        ->assertForbidden();
});

test('user cannot use task ID from another workspace through their own workspace URL', function () {
    $hierarchyA = $this->createFullHierarchy($this->userA, 'A');
    $hierarchyB = $this->createFullHierarchy($this->userB, 'B');

    // Try to manipulate URL: own workspace prefix + foreign task ID
    actingAs($this->userA)
        ->patch(route('tasks.update', [
            $hierarchyA['workspace']->id,
            $hierarchyA['space']->id,
            $hierarchyA['list']->id,
            $hierarchyB['task']->id, // foreign task ID
        ]), ['name' => 'Hijack Attempt'])
        ->assertNotFound();
});

// Cross-Space Resource Access
test('cannot move task to a list in another space', function () {
    $hierarchy = $this->createFullHierarchy($this->userA);

    // Create a different space + list in same workspace
    $otherSpace = Space::create([
        'workspace_id' => $hierarchy['workspace']->id,
        'name' => 'Other Space',
        'created_by' => $this->userA->id,
    ]);
    $otherList = Project::create([
        'space_id' => $otherSpace->id,
        'name' => 'Other List',
        'created_by' => $this->userA->id,
    ]);

    actingAs($this->userA)
        ->post(route('tasks.move', [
            $hierarchy['workspace']->id,
            $hierarchy['space']->id,
            $hierarchy['list']->id,
            $hierarchy['task']->id,
        ]), ['list_id' => $otherList->id])
        ->assertSessionHasErrors(['list_id']);
});

// Subtask Cross-Task Manipulation
test('cannot update subtask using wrong parent task ID in URL', function () {
    $hierarchy = $this->createFullHierarchy($this->userA);

    // Create subtask under task A
    $subtask = $this->createSubtask($hierarchy['task'], ['name' => 'Real Subtask']);

    // Create another task in same list
    $otherTask = Task::create([
        'project_id' => $hierarchy['list']->id,
        'name' => 'Other Task',
        'created_by' => $this->userA->id,
    ]);

    // Try to update subtask using OTHER task ID
    actingAs($this->userA)
        ->patch(route('tasks.subtasks.update', [
            $hierarchy['workspace']->id,
            $hierarchy['space']->id,
            $hierarchy['list']->id,
            $otherTask->id, // wrong parent
            $subtask->id,
        ]), ['name' => 'Hijacked'])
        ->assertNotFound();
});

// Status Cross-Space Manipulation
test('cannot use status from different space when changing task status', function () {
    $hierarchyA = $this->createFullHierarchy($this->userA, 'A');
    $hierarchyB = $this->createFullHierarchy($this->userA, 'B');

    $foreignStatus = $hierarchyB['statuses']->first();

    actingAs($this->userA)
        ->patch(route('tasks.change-status', [
            $hierarchyA['workspace']->id,
            $hierarchyA['space']->id,
            $hierarchyA['list']->id,
            $hierarchyA['task']->id,
        ]), ['status_id' => $foreignStatus->id])
        ->assertSessionHasErrors(['status_id']);
});
