<?php

use App\Models\Activity;

test('created action returns "created"', function () {
    $activity = new Activity([
        'action' => 'created',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [],
    ]);

    expect($activity->description)->toBe('created My Subtask');
});

test('updated with single field set returns "set X to Y"', function () {
    $activity = new Activity([
        'action' => 'updated',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'due_date' => ['old' => null, 'new' => '2026-04-30'],
        ],
    ]);

    expect($activity->description)->toBe('set due date to Apr 30, 2026');
});

test('updated with field changed returns "changed X from A to B"', function () {
    $activity = new Activity([
        'action' => 'updated',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'name' => ['old' => 'Old Name', 'new' => 'New Name'],
        ],
    ]);

    expect($activity->description)->toBe('changed name from Old Name to New Name');
});

test('updated with field cleared returns "cleared X"', function () {
    $activity = new Activity([
        'action' => 'updated',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'description' => ['old' => 'Some text', 'new' => null],
        ],
    ]);

    expect($activity->description)->toBe('cleared description');
});

test('updated with two fields lists both changes', function () {
    $activity = new Activity([
        'action' => 'updated',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'name' => ['old' => 'A', 'new' => 'B'],
            'due_date' => ['old' => '2026-01-01', 'new' => '2026-06-15'],
        ],
    ]);

    $desc = $activity->description;
    expect($desc)->toContain('changed name from A to B');
    expect($desc)->toContain('changed due date from Jan 1, 2026 to Jun 15, 2026');
});

test('updated with more than 3 changes shows "and N more"', function () {
    $activity = new Activity([
        'action' => 'updated',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'name' => ['old' => 'A', 'new' => 'B'],
            'description' => ['old' => 'X', 'new' => 'Y'],
            'progress' => ['old' => 0, 'new' => 50],
            'start_date' => ['old' => null, 'new' => '2026-03-01'],
        ],
    ]);

    expect($activity->description)->toContain('and 1 more');
});

test('updated with empty changes returns "updated"', function () {
    $activity = new Activity([
        'action' => 'updated',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [],
    ]);

    expect($activity->description)->toBe('updated');
});

test('updated with array values skips them gracefully', function () {
    $activity = new Activity([
        'action' => 'updated',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'assignee_ids' => ['old' => [1, 2], 'new' => [1, 2, 3]],
        ],
    ]);

    expect($activity->description)->toBe('updated');
});

test('assigned with assignee_name', function () {
    $activity = new Activity([
        'action' => 'assigned',
        'properties' => ['name' => 'My Subtask', 'assignee_name' => 'John'],
        'changes' => [],
    ]);

    expect($activity->description)->toBe('assigned John');
});

test('unassigned with assignee_name', function () {
    $activity = new Activity([
        'action' => 'unassigned',
        'properties' => ['name' => 'My Subtask', 'assignee_name' => 'John'],
        'changes' => [],
    ]);

    expect($activity->description)->toBe('unassigned John');
});

test('status_changed with old and new', function () {
    $activity = new Activity([
        'action' => 'status_changed',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'status' => ['old' => 'Open', 'new' => 'Completed'],
        ],
    ]);

    expect($activity->description)->toBe('changed status from Open to Completed');
});

test('priority_changed from old to new', function () {
    $activity = new Activity([
        'action' => 'priority_changed',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'priority' => ['old' => 'Low', 'new' => 'High'],
        ],
    ]);

    expect($activity->description)->toBe('changed priority from Low to High');
});

test('priority_changed set only (no old)', function () {
    $activity = new Activity([
        'action' => 'priority_changed',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'priority' => ['old' => null, 'new' => 'High'],
        ],
    ]);

    expect($activity->description)->toBe('set priority to High');
});

test('priority_changed cleared (no new)', function () {
    $activity = new Activity([
        'action' => 'priority_changed',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'priority' => ['old' => 'High', 'new' => null],
        ],
    ]);

    expect($activity->description)->toBe('cleared priority');
});

test('time_updated shows formatted durations', function () {
    $activity = new Activity([
        'action' => 'time_updated',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'duration' => ['old' => 30, 'new' => 90],
        ],
    ]);

    expect($activity->description)->toBe('updated time entry from 30m to 1h 30m');
});

test('time_deleted shows formatted duration', function () {
    $activity = new Activity([
        'action' => 'time_deleted',
        'properties' => ['name' => 'My Subtask', 'duration' => 90],
        'changes' => [],
    ]);

    expect($activity->description)->toBe('deleted 1h 30m time entry');
});

test('label_added shows label name', function () {
    $activity = new Activity([
        'action' => 'label_added',
        'properties' => ['name' => 'My Subtask', 'label_name' => 'Bug'],
        'changes' => [],
    ]);

    expect($activity->description)->toBe("added label 'Bug'");
});

test('commented with preview', function () {
    $activity = new Activity([
        'action' => 'commented',
        'properties' => ['name' => 'My Subtask', 'comment_preview' => 'Fix the issue'],
        'changes' => [],
    ]);

    expect($activity->description)->toBe('commented: "Fix the issue"');
});

test('date values are formatted as M j, Y', function () {
    $activity = new Activity([
        'action' => 'updated',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'baseline_due_date' => ['old' => '2026-01-15', 'new' => '2026-04-30'],
        ],
    ]);

    expect($activity->description)->toBe('changed baseline due date from Jan 15, 2026 to Apr 30, 2026');
});

test('deleted action returns "deleted"', function () {
    $activity = new Activity([
        'action' => 'deleted',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [],
    ]);

    expect($activity->description)->toBe('deleted My Subtask');
});

test('deleted_subtask includes subject name', function () {
    $activity = new Activity([
        'action' => 'deleted_subtask',
        'properties' => ['name' => 'Removed Task'],
        'changes' => [],
    ]);

    expect($activity->description)->toBe('deleted subtask Removed Task');
});

test('unknown action returns "performed X"', function () {
    $activity = new Activity([
        'action' => 'some_custom_action',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [],
    ]);

    expect($activity->description)->toBe('performed some_custom_action');
});

test('long text values are truncated', function () {
    $longText = str_repeat('x', 100);

    $activity = new Activity([
        'action' => 'updated',
        'properties' => ['name' => 'My Subtask'],
        'changes' => [
            'description' => ['old' => null, 'new' => $longText],
        ],
    ]);

    expect($activity->description)->toContain('...');
    expect(strlen($activity->description))->toBeLessThan(100);
});
