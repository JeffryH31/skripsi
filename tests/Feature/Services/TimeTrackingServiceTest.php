<?php

use App\Models\Activity;
use App\Models\TimeEntry;
use App\Services\TimeTrackingService;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new TimeTrackingService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
    $this->subtask = $this->createSubtask($this->hierarchy['task']);
});

test('logTime creates time entry with duration', function () {
    $entry = $this->service->logTime($this->subtask, $this->owner, [
        'duration' => 90,
    ]);

    expect($entry)->toBeInstanceOf(TimeEntry::class);
    expect($entry->duration)->toBe(90);
    expect($entry->user_id)->toBe($this->owner->id);
    expect($entry->subtask_id)->toBe($this->subtask->id);
});

test('logTime logs time_logged activity', function () {
    $this->service->logTime($this->subtask, $this->owner, ['duration' => 30]);

    $activity = Activity::where('action', 'time_logged')->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['duration'])->toBe(30);
});

test('logTime defaults to billable false', function () {
    $entry = $this->service->logTime($this->subtask, $this->owner, ['duration' => 10]);

    expect($entry->is_billable)->toBeFalse();
});

test('logTime with billable flag', function () {
    $entry = $this->service->logTime($this->subtask, $this->owner, [
        'duration' => 60,
        'is_billable' => true,
    ]);

    expect($entry->is_billable)->toBeTrue();
});

test('startTimer creates running entry and stops previous', function () {
    // Start first timer
    $entry1 = $this->service->startTimer($this->subtask, $this->owner);
    expect($entry1->is_running)->toBeTrue();

    // Start second timer — should stop first
    $subtask2 = $this->createSubtask($this->hierarchy['task'], ['name' => 'Subtask 2']);
    $entry2 = $this->service->startTimer($subtask2, $this->owner);

    expect($entry2->is_running)->toBeTrue();
    expect($entry1->fresh()->is_running)->toBeFalse();
});

test('startTimer logs timer_started activity', function () {
    $this->service->startTimer($this->subtask, $this->owner);

    $activity = Activity::where('action', 'timer_started')->first();
    expect($activity)->not->toBeNull();
});

test('stopTimer stops running entry', function () {
    $entry = $this->service->startTimer($this->subtask, $this->owner);

    $stopped = $this->service->stopTimer($entry, $this->owner);

    expect($stopped->is_running)->toBeFalse();
    expect($stopped->ended_at)->not->toBeNull();
});

test('stopTimer logs timer_stopped activity', function () {
    $entry = $this->service->startTimer($this->subtask, $this->owner);

    $this->service->stopTimer($entry, $this->owner);

    $activity = Activity::where('action', 'timer_stopped')->first();
    expect($activity)->not->toBeNull();
});

test('getRunningTimer returns active timer for user', function () {
    $this->service->startTimer($this->subtask, $this->owner);

    $running = $this->service->getRunningTimer($this->owner);

    expect($running)->not->toBeNull();
    expect($running->is_running)->toBeTrue();
    expect($running->user_id)->toBe($this->owner->id);
});

test('getRunningTimer returns null when no timer running', function () {
    $running = $this->service->getRunningTimer($this->owner);

    expect($running)->toBeNull();
});

test('updateEntry updates duration and logs activity on change', function () {
    $entry = $this->service->logTime($this->subtask, $this->owner, ['duration' => 30]);

    $updated = $this->service->updateEntry($entry, ['duration' => 60], $this->owner);

    expect($updated->duration)->toBe(60);

    $activity = Activity::where('action', 'time_updated')->first();
    expect($activity)->not->toBeNull();
    expect($activity->changes['duration']['old'])->toBe(30);
    expect($activity->changes['duration']['new'])->toBe(60);
});

test('updateEntry does not log activity when duration unchanged', function () {
    $entry = $this->service->logTime($this->subtask, $this->owner, ['duration' => 30]);

    $this->service->updateEntry($entry, ['is_billable' => true], $this->owner);

    $activity = Activity::where('action', 'time_updated')->first();
    expect($activity)->toBeNull();
});

test('deleteEntry soft deletes and logs activity', function () {
    $entry = $this->service->logTime($this->subtask, $this->owner, ['duration' => 45]);

    $this->service->deleteEntry($entry, $this->owner);

    expect(TimeEntry::find($entry->id))->toBeNull();
    expect(TimeEntry::withTrashed()->find($entry->id))->not->toBeNull();

    $activity = Activity::where('action', 'time_deleted')->first();
    expect($activity)->not->toBeNull();
});

test('getEntriesForTask returns entries ordered by started_at desc', function () {
    $this->service->logTime($this->subtask, $this->owner, [
        'duration' => 10,
        'started_at' => now()->subHours(2)->toDateTimeString(),
    ]);
    $this->service->logTime($this->subtask, $this->owner, [
        'duration' => 20,
        'started_at' => now()->subHour()->toDateTimeString(),
    ]);

    $entries = $this->service->getEntriesForTask($this->hierarchy['task']);

    expect($entries)->toHaveCount(2);
    expect($entries->first()->started_at->gte($entries->last()->started_at))->toBeTrue();
});

test('getEntriesForUser filters by date range', function () {
    $this->service->logTime($this->subtask, $this->owner, [
        'duration' => 10,
        'started_at' => now()->subDays(5)->toDateTimeString(),
    ]);
    $this->service->logTime($this->subtask, $this->owner, [
        'duration' => 20,
        'started_at' => now()->toDateTimeString(),
    ]);

    $filtered = $this->service->getEntriesForUser(
        $this->owner,
        now()->subDay()->toDateTimeString(),
        now()->addDay()->toDateTimeString(),
    );

    expect($filtered)->toHaveCount(1);
    expect($filtered->first()->duration)->toBe(20);
});

test('getTaskTimeSummary returns correct summary', function () {
    $this->service->logTime($this->subtask, $this->owner, ['duration' => 60]);
    $this->service->logTime($this->subtask, $this->owner, [
        'duration' => 30,
        'is_billable' => true,
    ]);

    $summary = $this->service->getTaskTimeSummary($this->hierarchy['task']->fresh());

    expect($summary['total_minutes'])->toBe(90);
    expect($summary['total_formatted'])->toBe('1h 30m');
    expect($summary['entries_count'])->toBe(2);
    expect($summary['billable_minutes'])->toBe(30);
});

test('getUserTimeSummary returns grouped data for current week', function () {
    $this->service->logTime($this->subtask, $this->owner, [
        'duration' => 45,
        'started_at' => now()->toDateTimeString(),
    ]);

    $summary = $this->service->getUserTimeSummary($this->owner, 'week');

    expect($summary['total_minutes'])->toBe(45);
    expect($summary['total_formatted'])->toBe('45m');
    expect($summary['entries_count'])->toBe(1);
});

test('getWorkspaceTimeReport returns by user and by space breakdown', function () {
    $this->service->logTime($this->subtask, $this->owner, [
        'duration' => 120,
        'started_at' => now()->toDateTimeString(),
    ]);

    $report = $this->service->getWorkspaceTimeReport($this->hierarchy['workspace']);

    expect($report['total_minutes'])->toBe(120);
    expect($report['total_formatted'])->toBe('2h 0m');
    expect($report['by_user'])->not->toBeEmpty();
    expect($report['by_space'])->not->toBeEmpty();
});

// ============================================================
// Merged from Unit/Services/TimeTrackingServiceTest.php
// ============================================================
test('logTime calculates duration from start and end times', function () {
    $entry = $this->service->logTime($this->subtask, $this->owner, [
        'started_at' => '2026-06-01 10:00:00',
        'ended_at' => '2026-06-01 11:30:00',
    ]);

    expect($entry->duration)->toBe(90);
});

test('startTimer stops previous running timer', function () {
    $entry1 = $this->service->startTimer($this->subtask, $this->owner);
    $subtask2 = $this->createSubtask($this->hierarchy['task'], ['name' => 'Another']);
    $entry2 = $this->service->startTimer($subtask2, $this->owner);

    expect($entry1->fresh()->is_running)->toBeFalse();
    expect($entry2->is_running)->toBeTrue();
});
