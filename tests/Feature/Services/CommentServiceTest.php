<?php

use App\Models\Activity;
use App\Models\Comment;
use App\Services\CommentService;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new CommentService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('create comment on task', function () {
    $comment = $this->service->create($this->hierarchy['task'], $this->owner, [
        'content' => 'This is a test comment',
    ]);

    expect($comment)->toBeInstanceOf(Comment::class);
    expect($comment->content)->toBe('This is a test comment');
    expect($comment->user_id)->toBe($this->owner->id);
    expect($comment->task_id)->toBe($this->hierarchy['task']->id);
});

test('create comment logs commented activity', function () {
    $this->service->create($this->hierarchy['task'], $this->owner, [
        'content' => 'Logging test',
    ]);

    $activity = Activity::where('action', 'commented')->first();

    expect($activity)->not->toBeNull();
    expect($activity->user_id)->toBe($this->owner->id);
    expect($activity->properties['comment_preview'])->toBe('Logging test');
});

test('update comment edits content and sets edited_at', function () {
    $comment = $this->service->create($this->hierarchy['task'], $this->owner, [
        'content' => 'Original content',
    ]);

    $updated = $this->service->update($comment, ['content' => 'Updated content'], $this->owner);

    expect($updated->content)->toBe('Updated content');
    expect($updated->edited_at)->not->toBeNull();
});

test('delete comment soft deletes and logs activity', function () {
    $comment = $this->service->create($this->hierarchy['task'], $this->owner, [
        'content' => 'To be deleted',
    ]);

    $this->service->delete($comment, $this->owner);

    expect(Comment::find($comment->id))->toBeNull();

    $activity = Activity::where('action', 'comment_deleted')->first();
    expect($activity)->not->toBeNull();
});

test('reply creates comment with parent_id', function () {
    $parentComment = $this->service->create($this->hierarchy['task'], $this->owner, [
        'content' => 'Parent comment',
    ]);

    $replier = $this->createUser();
    $reply = $this->service->reply($parentComment, $replier, [
        'content' => 'Reply content',
    ]);

    expect($reply->parent_id)->toBe($parentComment->id);
    expect($reply->content)->toBe('Reply content');
    expect($reply->user_id)->toBe($replier->id);
});

test('resolve comment sets is_resolved and logs activity', function () {
    $comment = $this->service->create($this->hierarchy['task'], $this->owner, [
        'content' => 'Needs resolution',
    ]);

    $resolved = $this->service->resolve($comment, $this->owner);

    expect($resolved->is_resolved)->toBeTrue();

    $activity = Activity::where('action', 'comment_resolved')->first();
    expect($activity)->not->toBeNull();
});

test('unresolve comment clears is_resolved', function () {
    $comment = $this->service->create($this->hierarchy['task'], $this->owner, [
        'content' => 'Resolved then undone',
    ]);

    $this->service->resolve($comment, $this->owner);
    $unresolved = $this->service->unresolve($comment, $this->owner);

    expect($unresolved->is_resolved)->toBeFalse();
});

test('getCommentsForTask returns comments with user and replies', function () {
    $this->service->create($this->hierarchy['task'], $this->owner, [
        'content' => 'First comment',
    ]);

    $parent = $this->service->create($this->hierarchy['task'], $this->owner, [
        'content' => 'Parent with reply',
    ]);

    $replier = $this->createUser();
    $this->service->reply($parent, $replier, ['content' => 'A reply']);

    $comments = $this->service->getCommentsForTask($this->hierarchy['task']);

    // Only top-level comments (parent_id is set on replies via create with parent_id,
    // but getCommentsForTask fetches all comments where task_id matches)
    expect($comments->count())->toBeGreaterThanOrEqual(2);
    expect($comments->first()->user)->not->toBeNull();
});

test('extractMentions finds mentioned users', function () {
    $alice = $this->createUser(['name' => 'Alice']);
    $bob = $this->createUser(['name' => 'Bob']);

    $ids = $this->service->extractMentions('Hey @Alice and @Bob please review');

    expect($ids)->toContain($alice->id);
    expect($ids)->toContain($bob->id);
});

test('extractMentions returns empty for no mentions', function () {
    $ids = $this->service->extractMentions('No mentions here');

    expect($ids)->toBeEmpty();
});

// ============================================================
// Merged from Unit/Services/CommentServiceTest.php
// ============================================================
test('create comment with mentions and attachments', function () {
    $task = $this->hierarchy['task'];

    $comment = $this->service->create($task, $this->owner, [
        'content' => 'Hey @john',
        'mentions' => ['john'],
        'attachments' => ['file.pdf'],
    ]);

    expect($comment->mentions)->toBe(['john']);
    expect($comment->attachments)->toBe(['file.pdf']);
});

test('extractMentions regex extracts single username', function () {
    preg_match_all('/@(\w+)/', 'Hello @john please review', $matches);
    expect($matches[1])->toBe(['john']);
});

test('extractMentions regex extracts multiple usernames', function () {
    preg_match_all('/@(\w+)/', '@alice and @bob cc @charlie', $matches);
    expect($matches[1])->toBe(['alice', 'bob', 'charlie']);
});

test('extractMentions regex returns empty for no mentions', function () {
    preg_match_all('/@(\w+)/', 'No mentions here', $matches);
    expect($matches[1])->toBe([]);
});

test('extractMentions regex handles underscore in usernames', function () {
    preg_match_all('/@(\w+)/', 'Hey @john_doe', $matches);
    expect($matches[1])->toBe(['john_doe']);
});
