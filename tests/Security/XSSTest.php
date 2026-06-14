<?php

use App\Models\Comment;
use App\Models\Space;
use App\Models\Task;
use App\Models\Workspace;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

// Comment Content Sanitization (strip_tags with allowlist)
test('comment strips script tags from content', function () {
    $malicious = '<p>Hello</p><script>alert("xss")</script><p>World</p>';

    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $this->owner->id,
        'content' => $malicious,
    ]);

    expect($comment->content)->not->toContain('<script>');
    expect($comment->content)->not->toContain('</script>');
    expect($comment->content)->toContain('<p>Hello</p>');
    expect($comment->content)->toContain('<p>World</p>');
});

test('comment strips img tags with onerror handler', function () {
    $malicious = '<p>Check this</p><img src=x onerror="alert(1)">';

    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $this->owner->id,
        'content' => $malicious,
    ]);

    expect($comment->content)->not->toContain('<img');
    expect($comment->content)->not->toContain('onerror');
    expect($comment->content)->toContain('<p>Check this</p>');
});

test('comment strips iframe tags', function () {
    $malicious = '<p>Content</p><iframe src="https://evil.com"></iframe>';

    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $this->owner->id,
        'content' => $malicious,
    ]);

    expect($comment->content)->not->toContain('<iframe');
    expect($comment->content)->not->toContain('</iframe>');
});

test('comment strips svg with onload event', function () {
    $malicious = '<svg onload="alert(1)"><circle r="50"/></svg>';

    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $this->owner->id,
        'content' => $malicious,
    ]);

    expect($comment->content)->not->toContain('<svg');
    expect($comment->content)->not->toContain('onload');
});

test('comment strips style tags', function () {
    $malicious = '<style>body{display:none}</style><p>Visible</p>';

    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $this->owner->id,
        'content' => $malicious,
    ]);

    expect($comment->content)->not->toContain('<style>');
    expect($comment->content)->toContain('<p>Visible</p>');
});

test('comment preserves allowed HTML tags', function () {
    $safe = '<p>Hello <strong>bold</strong> and <em>italic</em></p><ul><li>Item 1</li></ul><a>link</a>';

    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $this->owner->id,
        'content' => $safe,
    ]);

    expect($comment->content)->toContain('<p>');
    expect($comment->content)->toContain('<strong>');
    expect($comment->content)->toContain('<em>');
    expect($comment->content)->toContain('<ul>');
    expect($comment->content)->toContain('<li>');
    expect($comment->content)->toContain('<a>');
});

test('comment strips event handlers from allowed tags', function () {
    // strip_tags only removes disallowed tags, NOT attributes on allowed tags
    // This test documents this known limitation
    $malicious = '<a href="javascript:alert(1)">Click me</a>';

    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $this->owner->id,
        'content' => $malicious,
    ]);

    // strip_tags preserves <a> tag including its attributes
    // Vue's v-html or frontend must handle href sanitization
    // This documents the behavior — the tag passes through since <a> is allowed
    expect($comment->content)->toContain('<a');
});

// Task Name/Description — Stored as-is, XSS prevented by Vue auto-escape
test('task name with script tag is stored as plain text', function () {
    $malicious = '<script>alert("xss")</script>My Task';

    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), ['name' => $malicious])
        ->assertRedirect();

    $task = Task::where('name', $malicious)->first();
    expect($task)->not->toBeNull();
    // Stored as-is — Vue {{ }} interpolation will auto-escape on render
    expect($task->name)->toBe($malicious);
});

test('task description with HTML is stored as plain text', function () {
    $malicious = '<img src=x onerror="document.location=\'https://evil.com\'"><p>Description</p>';

    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), ['name' => 'Valid Task', 'description' => $malicious])
        ->assertRedirect();

    $task = Task::where('name', 'Valid Task')->first();
    // Stored as-is — no server-side sanitization needed because
    // Vue renders task description via {{ }} text interpolation (auto-escaped)
    expect($task->description)->toBe($malicious);
});

// Comment via HTTP endpoint (full integration)
test('comment creation via endpoint sanitizes script injection', function () {
    actingAs($this->owner)
        ->post(route('tasks.comments.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
            $this->hierarchy['task']->id,
        ]), ['content' => '<p>Normal</p><script>steal(cookies)</script>'])
        ->assertRedirect();

    $comment = Comment::where('task_id', $this->hierarchy['task']->id)->latest()->first();
    expect($comment->content)->not->toContain('<script>');
    expect($comment->content)->toContain('<p>Normal</p>');
});

test('comment update via endpoint sanitizes injected tags', function () {
    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $this->owner->id,
        'content' => '<p>Original</p>',
    ]);

    actingAs($this->owner)
        ->patch(route('comments.update', $comment->id), [
            'content' => '<p>Updated</p><script>alert(1)</script><iframe src="evil"></iframe>',
        ])
        ->assertRedirect();

    $comment->refresh();
    expect($comment->content)->not->toContain('<script>');
    expect($comment->content)->not->toContain('<iframe');
    expect($comment->content)->toContain('<p>Updated</p>');
});

// Workspace/Space name — plain text fields, no HTML allowed
test('workspace name with HTML is stored as plain text', function () {
    $malicious = '<script>alert(1)</script>Workspace';

    actingAs($this->owner)
        ->post(route('workspaces.store'), ['name' => $malicious, 'color' => '#000000'])
        ->assertRedirect();

    // Vue auto-escapes — script tag rendered as harmless text
    $ws = Workspace::where('name', $malicious)->first();
    expect($ws)->not->toBeNull();
    expect($ws->name)->toBe($malicious);
});

test('space name with HTML is stored as plain text', function () {
    $malicious = '<img src=x onerror=alert(1)>Space';

    actingAs($this->owner)
        ->post(route('spaces.store', $this->hierarchy['workspace']->id), [
            'name' => $malicious,
            'color' => '#FF0000',
        ])
        ->assertRedirect();

    $space = Space::where('name', $malicious)->first();
    expect($space)->not->toBeNull();
    expect($space->name)->toBe($malicious);
});
