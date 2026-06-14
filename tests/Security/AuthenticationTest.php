
<?php

use App\Models\Workspace;
use Tests\Traits\CreatesWorkspaceHierarchy;

uses(CreatesWorkspaceHierarchy::class);

// Dashboard & Core Endpoints
test('unauthenticated user is redirected to login when accessing dashboard', function () {
    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('unauthenticated user cannot access my-tasks page', function () {
    $this->get(route('my-tasks'))
        ->assertRedirect(route('login'));
});

test('unauthenticated user cannot access search endpoint', function () {
    $this->get(route('search', ['q' => 'test']))
        ->assertRedirect(route('login'));
});

// Workspace Endpoints
test('unauthenticated user cannot list workspaces', function () {
    $this->get(route('workspaces.index'))
        ->assertRedirect(route('login'));
});

test('unauthenticated user cannot create workspace', function () {
    $this->post(route('workspaces.store'), ['name' => 'Hack'])
        ->assertRedirect(route('login'));
});

test('unauthenticated user cannot view workspace', function () {
    $owner = $this->createUser();
    $hierarchy = $this->createFullHierarchy($owner);

    $this->get(route('workspaces.show', $hierarchy['workspace']->id))
        ->assertRedirect(route('login'));
});

test('unauthenticated user cannot delete workspace', function () {
    $owner = $this->createUser();
    $hierarchy = $this->createFullHierarchy($owner);

    $this->delete(route('workspaces.destroy', $hierarchy['workspace']->id))
        ->assertRedirect(route('login'));
});

// Space Endpoints
test('unauthenticated user cannot view space', function () {
    $owner = $this->createUser();
    $hierarchy = $this->createFullHierarchy($owner);

    $this->get(route('spaces.show', [$hierarchy['workspace']->id, $hierarchy['space']->id]))
        ->assertRedirect(route('login'));
});

test('unauthenticated user cannot create space', function () {
    $owner = $this->createUser();
    $hierarchy = $this->createFullHierarchy($owner);

    $this->post(route('spaces.store', $hierarchy['workspace']->id), ['name' => 'Hack'])
        ->assertRedirect(route('login'));
});

// Task Endpoints
test('unauthenticated user cannot create task', function () {
    $owner = $this->createUser();
    $hierarchy = $this->createFullHierarchy($owner);

    $this->post(route('tasks.store', [
        $hierarchy['workspace']->id,
        $hierarchy['space']->id,
        $hierarchy['list']->id,
    ]), ['name' => 'Unauthorized Task'])
        ->assertRedirect(route('login'));
});

test('unauthenticated user cannot update task', function () {
    $owner = $this->createUser();
    $hierarchy = $this->createFullHierarchy($owner);

    $this->patch(route('tasks.update', [
        $hierarchy['workspace']->id,
        $hierarchy['space']->id,
        $hierarchy['list']->id,
        $hierarchy['task']->id,
    ]), ['name' => 'Hacked'])
        ->assertRedirect(route('login'));
});

test('unauthenticated user cannot delete task', function () {
    $owner = $this->createUser();
    $hierarchy = $this->createFullHierarchy($owner);

    $this->delete(route('tasks.destroy', [
        $hierarchy['workspace']->id,
        $hierarchy['space']->id,
        $hierarchy['list']->id,
        $hierarchy['task']->id,
    ]))->assertRedirect(route('login'));
});

// Notification Endpoints
test('unauthenticated user cannot mark notifications as read', function () {
    $this->post(route('notifications.read'))
        ->assertRedirect(route('login'));
});

// Time Tracking Endpoints
test('unauthenticated user cannot view time tracking', function () {
    $this->get(route('time-tracking.index'))
        ->assertRedirect(route('login'));
});

test('unauthenticated user cannot view running timer', function () {
    $this->get(route('time-tracking.running'))
        ->assertRedirect(route('login'));
});
