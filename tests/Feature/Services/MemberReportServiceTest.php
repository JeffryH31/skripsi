<?php

use App\Models\Activity;
use App\Models\TimeEntry;
use App\Services\MemberReportService;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new MemberReportService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('getReport returns all expected keys', function () {
    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    expect($report)->toHaveKeys([
        'stats',
        'runningTimer',
        'dailyData',
        'weeklyData',
        'activeSubtasks',
        'recentlyCompleted',
        'recentEntries',
        'recentActivity',
    ]);
});

test('getReport stats reflect logged time entries', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Work Item']);

    // Log a time entry today
    TimeEntry::create([
        'subtask_id' => $subtask->id,
        'user_id' => $this->owner->id,
        'duration' => 90,
        'is_billable' => true,
        'is_running' => false,
        'started_at' => now(),
        'ended_at' => now()->addMinutes(90),
    ]);

    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    expect($report['stats']['today_minutes'])->toBe(90);
    expect($report['stats']['week_minutes'])->toBeGreaterThanOrEqual(90);
    expect($report['stats']['month_minutes'])->toBeGreaterThanOrEqual(90);
    expect($report['stats']['billable_minutes'])->toBe(90);
    expect($report['stats']['all_time_minutes'])->toBe(90);
});

test('getReport includes active subtasks assigned to member', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Active Work']);
    $subtask->assignees()->attach($this->owner->id, [
        'assigned_by' => $this->owner->id,
    ]);

    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    $activeNames = collect($report['activeSubtasks'])->pluck('name');
    expect($activeNames)->toContain('Active Work');
});

test('getReport excludes completed subtasks from active list', function () {
    $completed = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Done Item',
        'completed_at' => now(),
    ]);
    $completed->assignees()->attach($this->owner->id, [
        'assigned_by' => $this->owner->id,
    ]);

    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    $activeNames = collect($report['activeSubtasks'])->pluck('name');
    expect($activeNames)->not->toContain('Done Item');
});

test('getReport includes recently completed subtasks', function () {
    $completed = $this->createSubtask($this->hierarchy['task'], [
        'name' => 'Just Finished',
        'completed_at' => now()->subDays(2),
    ]);
    $completed->assignees()->attach($this->owner->id, [
        'assigned_by' => $this->owner->id,
    ]);

    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    $completedNames = collect($report['recentlyCompleted'])->pluck('name');
    expect($completedNames)->toContain('Just Finished');
});

test('getReport dailyData has 14 entries', function () {
    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    expect($report['dailyData'])->toHaveCount(14);
    expect($report['dailyData'][0])->toHaveKeys(['date', 'label', 'minutes', 'hours', 'pct']);
});

test('getReport weeklyData has 7 entries for Mon to Sun', function () {
    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    expect($report['weeklyData'])->toHaveCount(7);
    expect($report['weeklyData'][0])->toHaveKeys(['day', 'date', 'minutes', 'hours']);
});

test('getReport detects running timer', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Timer Task']);

    TimeEntry::create([
        'subtask_id' => $subtask->id,
        'user_id' => $this->owner->id,
        'duration' => 0,
        'is_billable' => false,
        'is_running' => true,
        'started_at' => now()->subMinutes(30),
    ]);

    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    expect($report['runningTimer'])->not->toBeNull();
    expect($report['runningTimer']['subtask'])->toBe('Timer Task');
    expect($report['runningTimer']['elapsed_minutes'])->toBeGreaterThanOrEqual(29);
});

test('getReport returns null when no running timer', function () {
    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    expect($report['runningTimer'])->toBeNull();
});

test('getReport includes recent activity log', function () {
    Activity::log($this->hierarchy['workspace'], $this->owner, $this->hierarchy['task'], 'created', [
        'name' => 'Test Task',
    ]);

    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    expect($report['recentActivity'])->not->toBeEmpty();
    $actions = collect($report['recentActivity'])->pluck('action');
    expect($actions)->toContain('created');
});

test('getReport recent entries reflect time logs', function () {
    $subtask = $this->createSubtask($this->hierarchy['task'], ['name' => 'Tracked Work']);

    TimeEntry::create([
        'subtask_id' => $subtask->id,
        'user_id' => $this->owner->id,
        'duration' => 45,
        'is_billable' => false,
        'is_running' => false,
        'started_at' => now()->subHours(2),
        'ended_at' => now()->subHours(2)->addMinutes(45),
    ]);

    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    expect($report['recentEntries'])->not->toBeEmpty();
    expect($report['recentEntries'][0]['duration'])->toBe(45);
    expect($report['recentEntries'][0]['subtask']['name'])->toBe('Tracked Work');
});

test('getReport scopes data to workspace', function () {
    $otherOwner = $this->createUser();
    $otherHierarchy = $this->createFullHierarchy($otherOwner, 'B');

    $otherSubtask = $this->createSubtask($otherHierarchy['task'], ['name' => 'Other Workspace Work']);
    $otherSubtask->assignees()->attach($this->owner->id, [
        'assigned_by' => $otherOwner->id,
    ]);

    // Time entry in the other workspace
    TimeEntry::create([
        'subtask_id' => $otherSubtask->id,
        'user_id' => $this->owner->id,
        'duration' => 120,
        'is_billable' => false,
        'is_running' => false,
        'started_at' => now(),
        'ended_at' => now()->addMinutes(120),
    ]);

    $report = $this->service->getReport($this->hierarchy['workspace'], $this->owner);

    // Time from other workspace should NOT appear in this workspace's report
    expect($report['stats']['today_minutes'])->toBe(0);
    $activeNames = collect($report['activeSubtasks'])->pluck('name');
    expect($activeNames)->not->toContain('Other Workspace Work');
});
