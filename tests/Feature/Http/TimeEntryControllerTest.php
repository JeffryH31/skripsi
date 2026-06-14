<?php

use App\Models\TimeEntry;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;

uses(CreatesWorkspaceHierarchy::class);

// Shared setup
beforeEach(function () {
    $this->owner = $this->createUser();
    $this->h = $this->createFullHierarchy($this->owner);
    $this->subtask = $this->createSubtask($this->h['task']);
});

// store (log time)
test('owner can log time to a subtask', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(
            route('tasks.subtasks.time-entries.store', [
                $this->h['workspace'],
                $this->h['space'],
                $this->h['list'],
                $this->h['task'],
                $this->subtask,
            ]),
            ['duration' => 90]
        )
        ->assertRedirect();

    $this->assertDatabaseHas('time_entries', [
        'subtask_id' => $this->subtask->id,
        'user_id' => $this->owner->id,
        'duration' => 90,
    ]);
});

test('unauthenticated user cannot log time', function () {
    $this->post(
        route('tasks.subtasks.time-entries.store', [
            $this->h['workspace'],
            $this->h['space'],
            $this->h['list'],
            $this->h['task'],
            $this->subtask,
        ]),
        ['duration' => 30]
    )
        ->assertRedirectToRoute('login');
});

test('logging time with duration 0 returns a validation error', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(
            route('tasks.subtasks.time-entries.store', [
                $this->h['workspace'],
                $this->h['space'],
                $this->h['list'],
                $this->h['task'],
                $this->subtask,
            ]),
            ['duration' => 0]
        )
        ->assertSessionHasErrors(['duration']);
});

test('logging time over 1440 minutes (24 h) returns a validation error', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(
            route('tasks.subtasks.time-entries.store', [
                $this->h['workspace'],
                $this->h['space'],
                $this->h['list'],
                $this->h['task'],
                $this->subtask,
            ]),
            ['duration' => 1441]
        )
        ->assertSessionHasErrors(['duration']);
});

test('non-member gets 403 when logging time', function () {
    $stranger = $this->createUser();
    $this->h['workspace']->addMember($stranger, 'member');
    $this->h['list']->addMember($this->owner, 'project_owner'); // lock list

    actingAs($stranger)
        ->post(
            route('tasks.subtasks.time-entries.store', [
                $this->h['workspace'],
                $this->h['space'],
                $this->h['list'],
                $this->h['task'],
                $this->subtask,
            ]),
            ['duration' => 30]
        )
        ->assertForbidden();
});

// startTimer
test('owner can start a timer', function () {
    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(
            route('tasks.timer.start', [
                $this->h['workspace'],
                $this->h['space'],
                $this->h['list'],
                $this->h['task'],
            ])
        )
        ->assertRedirect();

    $this->assertDatabaseHas('time_entries', [
        'subtask_id' => $this->subtask->id,
        'user_id' => $this->owner->id,
        'is_running' => true,
    ]);
});

test('starting a second timer stops the first', function () {
    // First timer
    actingAs($this->owner)
        ->post(route('tasks.timer.start', [
            $this->h['workspace'],
            $this->h['space'],
            $this->h['list'],
            $this->h['task'],
        ]));

    $firstEntry = TimeEntry::where('user_id', $this->owner->id)->where('is_running', true)->first();
    expect($firstEntry)->not->toBeNull();

    // Second timer on different subtask
    $subtask2 = $this->createSubtask($this->h['task']);
    actingAs($this->owner)
        ->post(route('tasks.timer.start', [
            $this->h['workspace'],
            $this->h['space'],
            $this->h['list'],
            $this->h['task'],
        ]), ['subtask_id' => $subtask2->id]);

    // First timer is no longer running
    $this->assertDatabaseHas('time_entries', [
        'id' => $firstEntry->id,
        'is_running' => false,
    ]);
    // New timer is running
    $this->assertDatabaseHas('time_entries', [
        'subtask_id' => $subtask2->id,
        'is_running' => true,
    ]);
});

// stopTimer
test('owner can stop their own running timer', function () {
    $entry = TimeEntry::create([
        'subtask_id' => $this->subtask->id,
        'user_id' => $this->owner->id,
        'started_at' => now()->subMinutes(30),
        'is_running' => true,
        'duration' => 0,
    ]);

    actingAs($this->owner)
        ->from(route('projects.show', [$this->h['workspace'], $this->h['space'], $this->h['list']]))
        ->post(route('tasks.timer.stop', [
            $this->h['workspace'],
            $this->h['space'],
            $this->h['list'],
            $this->h['task'],
            $entry,
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('time_entries', [
        'id' => $entry->id,
        'is_running' => false,
    ]);
});

test('another user cannot stop someone else timer', function () {
    $entry = TimeEntry::create([
        'subtask_id' => $this->subtask->id,
        'user_id' => $this->owner->id,
        'started_at' => now()->subMinutes(10),
        'is_running' => true,
        'duration' => 0,
    ]);

    $other = $this->createUser();
    $this->h['workspace']->addMember($other, 'member');

    actingAs($other)
        ->post(route('tasks.timer.stop', [
            $this->h['workspace'],
            $this->h['space'],
            $this->h['list'],
            $this->h['task'],
            $entry,
        ]))
        ->assertForbidden();
});
