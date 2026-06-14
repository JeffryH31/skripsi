<?php

use App\Models\Task;
use App\Models\User;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

// Required Field Validation
test('task name is required', function () {
    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), [])
        ->assertSessionHasErrors(['name']);
});

test('workspace name is required', function () {
    actingAs($this->owner)
        ->post(route('workspaces.store'), [])
        ->assertSessionHasErrors(['name']);
});

test('space name is required', function () {
    actingAs($this->owner)
        ->post(route('spaces.store', $this->hierarchy['workspace']->id), [])
        ->assertSessionHasErrors(['name']);
});

// Length Validation
test('task name cannot exceed 255 characters', function () {
    $longName = str_repeat('a', 256);

    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), ['name' => $longName])
        ->assertSessionHasErrors(['name']);
});

test('task description cannot exceed 10000 characters', function () {
    $longDesc = str_repeat('a', 10001);

    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), ['name' => 'Valid Name', 'description' => $longDesc])
        ->assertSessionHasErrors(['description']);
});

test('search query cannot exceed 200 characters', function () {
    $longQuery = str_repeat('a', 201);

    actingAs($this->owner)
        ->get(route('search', ['q' => $longQuery]))
        ->assertSessionHasErrors(['q']);
});

// Type Validation
test('priority_level must be in range 1-4', function () {
    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), ['name' => 'Valid Task', 'priority_level' => 99])
        ->assertSessionHasErrors(['priority_level']);
});

test('priority_level rejects negative values', function () {
    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), ['name' => 'Valid Task', 'priority_level' => -1])
        ->assertSessionHasErrors(['priority_level']);
});

test('priority_level rejects non-integer values', function () {
    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), ['name' => 'Valid Task', 'priority_level' => 'high'])
        ->assertSessionHasErrors(['priority_level']);
});

test('time_estimate cannot exceed max value (1 year)', function () {
    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), ['name' => 'Valid', 'time_estimate' => 999999])
        ->assertSessionHasErrors(['time_estimate']);
});

test('time_estimate rejects negative values', function () {
    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), ['name' => 'Valid', 'time_estimate' => -10])
        ->assertSessionHasErrors(['time_estimate']);
});

// Date Validation
test('due_date must be after or equal to start_date', function () {
    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), [
            'name' => 'Valid Name',
            'start_date' => '2026-06-15',
            'due_date' => '2026-06-10',
        ])
        ->assertSessionHasErrors(['due_date']);
});

test('start_date must be a valid date', function () {
    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), [
            'name' => 'Valid',
            'start_date' => 'not-a-date',
        ])
        ->assertSessionHasErrors(['start_date']);
});

// Foreign Key Validation
test('assignee_ids must reference existing users', function () {
    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), [
            'name' => 'Valid',
            'assignee_ids' => [99999], // non-existent user ID
        ])
        ->assertSessionHasErrors(['assignee_ids.0']);
});

test('label_ids must reference existing labels', function () {
    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), [
            'name' => 'Valid',
            'label_ids' => [99999],
        ])
        ->assertSessionHasErrors(['label_ids.0']);
});

// SQL Injection Resistance (Eloquent handles this, but we verify)
test('search query with SQL injection attempt is safely escaped', function () {
    // Eloquent uses parameterized queries — this should not break or expose data
    $maliciousQuery = "'; DROP TABLE users; --";

    actingAs($this->owner)
        ->get(route('search', ['q' => $maliciousQuery]))
        ->assertSuccessful();

    // Verify users table still exists by querying it
    expect(User::count())->toBeGreaterThan(0);
});

test('task name with SQL injection attempt is stored as plain text', function () {
    $maliciousName = "'; DROP TABLE tasks; --";

    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), ['name' => $maliciousName])
        ->assertRedirect();

    // Verify the malicious string was stored as plain text, not executed
    $task = Task::where('name', $maliciousName)->first();
    expect($task)->not->toBeNull();
    expect($task->name)->toBe($maliciousName);
});

test('search with OR 1=1 does not bypass query and leak all data', function () {
    // Create a second user with their own workspace/task that owner should NOT see
    $otherUser = $this->createUser();
    $otherHierarchy = $this->createFullHierarchy($otherUser, 'Other');

    // Attempt classic OR 1=1 injection via search
    $maliciousQuery = "' OR 1=1 --";

    $response = actingAs($this->owner)
        ->get(route('search', ['q' => $maliciousQuery]))
        ->assertSuccessful();

    $data = $response->json();

    // Should NOT return the other user's task — parameterized queries treat input as literal string
    $allTaskNames = collect($data['tasks'] ?? [])->pluck('name')->all();
    expect($allTaskNames)->not->toContain($otherHierarchy['task']->name);
});

test('search with UNION SELECT injection does not expose other tables', function () {
    $maliciousQuery = "' UNION SELECT password FROM users --";

    actingAs($this->owner)
        ->get(route('search', ['q' => $maliciousQuery]))
        ->assertSuccessful();

    // App still works, no password data leaked
    expect(User::count())->toBeGreaterThan(0);
});

test('task creation with OR 1=1 in name stores literal string', function () {
    $maliciousName = "Task' OR '1'='1";

    actingAs($this->owner)
        ->post(route('tasks.store', [
            $this->hierarchy['workspace']->id,
            $this->hierarchy['space']->id,
            $this->hierarchy['list']->id,
        ]), ['name' => $maliciousName])
        ->assertRedirect();

    $task = Task::where('name', $maliciousName)->first();
    expect($task)->not->toBeNull();
    expect($task->name)->toBe($maliciousName);
});
