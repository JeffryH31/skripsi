<?php

use App\Models\Comment;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;

uses(CreatesWorkspaceHierarchy::class);

// Shared setup
beforeEach(function () {
    $this->owner = $this->createUser();
    $this->h = $this->createFullHierarchy($this->owner);
});

// Route helper — comment store is scoped to workspace/space/list/task
function commentRoute(array $h, string $name, ?Comment $comment = null): string
{
    $params = [$h['workspace'], $h['space'], $h['list'], $h['task']];
    if ($comment) {
        $params[] = $comment;
    }

    return route($name, $params);
}

// store
test('owner can create a comment on a task', function () {
    actingAs($this->owner)
        ->from(commentRoute($this->h, 'projects.show'))
        ->post(commentRoute($this->h, 'tasks.comments.store'), [
            'content' => 'Great progress!',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('comments', [
        'task_id' => $this->h['task']->id,
        'user_id' => $this->owner->id,
        'content' => 'Great progress!',
    ]);
});

test('unauthenticated user cannot create a comment', function () {
    $this->post(commentRoute($this->h, 'tasks.comments.store'), [
        'content' => 'Ghost comment',
    ])
        ->assertRedirectToRoute('login');
});

test('non-member (no project view access) gets 403 when commenting', function () {
    $stranger = $this->createUser();
    // workspace member but no space/project membership; list has members → project is locked
    $this->h['workspace']->addMember($stranger, 'member');
    $this->h['list']->addMember($this->owner, 'project_owner');

    actingAs($stranger)
        ->post(commentRoute($this->h, 'tasks.comments.store'), [
            'content' => 'Sneaky comment',
        ])
        ->assertForbidden();
});

test('comment with empty content returns a validation error', function () {
    actingAs($this->owner)
        ->from(commentRoute($this->h, 'projects.show'))
        ->post(commentRoute($this->h, 'tasks.comments.store'), ['content' => ''])
        ->assertSessionHasErrors(['content']);
});

test('comment content cannot exceed 10000 characters', function () {
    actingAs($this->owner)
        ->from(commentRoute($this->h, 'projects.show'))
        ->post(commentRoute($this->h, 'tasks.comments.store'), [
            'content' => str_repeat('x', 10001),
        ])
        ->assertSessionHasErrors(['content']);
});

test('developer (project member) can comment', function () {
    $dev = $this->createUser();
    $this->h['workspace']->addMember($dev, 'member');
    $this->h['list']->addMember($dev, 'development_team');

    actingAs($dev)
        ->from(commentRoute($this->h, 'projects.show'))
        ->post(commentRoute($this->h, 'tasks.comments.store'), [
            'content' => 'Dev comment',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('comments', [
        'task_id' => $this->h['task']->id,
        'user_id' => $dev->id,
        'content' => 'Dev comment',
    ]);
});

// update
test('comment author can update their own comment', function () {
    $comment = Comment::create([
        'task_id' => $this->h['task']->id,
        'user_id' => $this->owner->id,
        'content' => 'Original content',
    ]);

    actingAs($this->owner)
        ->from(commentRoute($this->h, 'projects.show'))
        ->patch(route('comments.update', $comment), [
            'content' => 'Edited content',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('comments', [
        'id' => $comment->id,
        'content' => 'Edited content',
    ]);
});

test('another user cannot update someone else comment', function () {
    $comment = Comment::create([
        'task_id' => $this->h['task']->id,
        'user_id' => $this->owner->id,
        'content' => 'Owner comment',
    ]);

    $other = $this->createUser();
    $this->h['workspace']->addMember($other, 'member');

    actingAs($other)
        ->patch(route('comments.update', $comment), [
            'content' => 'Hijacked',
        ])
        ->assertRedirect();

    // CommentController catches AuthorizationException and redirects with error
    $this->assertDatabaseHas('comments', ['id' => $comment->id, 'content' => 'Owner comment']);
});

// destroy
test('comment author can delete their own comment', function () {
    $comment = Comment::create([
        'task_id' => $this->h['task']->id,
        'user_id' => $this->owner->id,
        'content' => 'Delete me',
    ]);

    actingAs($this->owner)
        ->delete(route('comments.destroy', $comment))
        ->assertRedirect();

    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

test('another user cannot delete someone else comment', function () {
    $comment = Comment::create([
        'task_id' => $this->h['task']->id,
        'user_id' => $this->owner->id,
        'content' => 'Protected comment',
    ]);

    $other = $this->createUser();
    $this->h['workspace']->addMember($other, 'member');

    actingAs($other)
        ->delete(route('comments.destroy', $comment))
        ->assertRedirect();

    // CommentController catches AuthorizationException and redirects with error
    $this->assertDatabaseHas('comments', ['id' => $comment->id]);
});

test('workspace admin (owner) can delete any comment', function () {
    $dev = $this->createUser();
    $this->h['workspace']->addMember($dev, 'member');

    $comment = Comment::create([
        'task_id' => $this->h['task']->id,
        'user_id' => $dev->id,
        'content' => 'Dev comment to moderate',
    ]);

    // Owner has workspace 'owner' role — canManageComment returns true
    actingAs($this->owner)
        ->delete(route('comments.destroy', $comment))
        ->assertRedirect();

    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

// resolve / unresolve
test('comment author can resolve their own comment', function () {
    $comment = Comment::create([
        'task_id' => $this->h['task']->id,
        'user_id' => $this->owner->id,
        'content' => 'Resolve me',
    ]);

    actingAs($this->owner)
        ->from(commentRoute($this->h, 'projects.show'))
        ->post(route('comments.resolve', $comment))
        ->assertRedirect();

    $this->assertDatabaseHas('comments', [
        'id' => $comment->id,
        'is_resolved' => true,
    ]);
});

test('comment author can unresolve their own comment', function () {
    $comment = Comment::create([
        'task_id' => $this->h['task']->id,
        'user_id' => $this->owner->id,
        'content' => 'Unresolve me',
        'is_resolved' => true,
    ]);

    actingAs($this->owner)
        ->from(commentRoute($this->h, 'projects.show'))
        ->post(route('comments.unresolve', $comment))
        ->assertRedirect();

    $this->assertDatabaseHas('comments', [
        'id' => $comment->id,
        'is_resolved' => false,
    ]);
});

test('developer cannot resolve another user comment', function () {
    $dev = $this->createUser();
    $this->h['workspace']->addMember($dev, 'member');
    $this->h['list']->addMember($dev, 'development_team');

    $comment = Comment::create([
        'task_id' => $this->h['task']->id,
        'user_id' => $this->owner->id,
        'content' => 'Owners comment',
    ]);

    actingAs($dev)
        ->post(route('comments.resolve', $comment))
        ->assertForbidden();
});

test('project manager can resolve any comment in their project', function () {
    $pm = $this->createUser();
    $this->h['workspace']->addMember($pm, 'member');
    $this->h['list']->addMember($pm, 'project_manager');

    $comment = Comment::create([
        'task_id' => $this->h['task']->id,
        'user_id' => $this->owner->id,
        'content' => 'PM can resolve this',
    ]);

    actingAs($pm)
        ->from(commentRoute($this->h, 'projects.show'))
        ->post(route('comments.resolve', $comment))
        ->assertRedirect();

    $this->assertDatabaseHas('comments', [
        'id' => $comment->id,
        'is_resolved' => true,
    ]);
});
