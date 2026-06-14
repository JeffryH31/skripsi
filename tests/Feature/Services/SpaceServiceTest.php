<?php

use App\Models\Activity;
use App\Models\Space;
use App\Models\Status;
use App\Services\SpaceService;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new SpaceService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('create space in workspace', function () {
    $space = $this->service->create([
        'name' => 'Engineering',
    ], $this->hierarchy['workspace'], $this->owner);

    expect($space)->toBeInstanceOf(Space::class);
    expect($space->name)->toBe('Engineering');
    expect($space->workspace_id)->toBe($this->hierarchy['workspace']->id);
    expect($space->created_by)->toBe($this->owner->id);
});

test('create space logs created activity', function () {
    $this->service->create([
        'name' => 'Activity Space',
    ], $this->hierarchy['workspace'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Space::class)
        ->first();

    expect($activity)->not->toBeNull();
});

test('create space with custom color', function () {
    $space = $this->service->create([
        'name' => 'Custom Space',
        'color' => '#FF0000',
    ], $this->hierarchy['workspace'], $this->owner);

    expect($space->color)->toBe('#FF0000');
});

test('update space changes name and logs activity', function () {
    $updated = $this->service->update($this->hierarchy['space'], [
        'name' => 'Updated Space',
    ], $this->owner);

    expect($updated->name)->toBe('Updated Space');

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Space::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('update space without actual changes does not log activity', function () {
    $this->service->update($this->hierarchy['space'], [
        'name' => $this->hierarchy['space']->name,
    ], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Space::class)
        ->first();
    expect($activity)->toBeNull();
});

test('delete space removes it and logs activity', function () {
    $space = $this->service->create(['name' => 'Delete Me'], $this->hierarchy['workspace'], $this->owner);

    $this->service->delete($space, $this->owner);

    expect(Space::find($space->id))->toBeNull();

    $activity = Activity::where('action', 'deleted')
        ->where('subject_type', Space::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('toggleStar adds and removes star', function () {
    $result1 = $this->service->toggleStar($this->hierarchy['space'], $this->owner);
    expect($result1)->toBeTrue();

    $result2 = $this->service->toggleStar($this->hierarchy['space'], $this->owner);
    expect($result2)->toBeFalse();
});

test('reorder updates space positions', function () {
    $space2 = $this->service->create(['name' => 'Space 2'], $this->hierarchy['workspace'], $this->owner);

    $this->service->reorder($this->hierarchy['workspace'], [$space2->id, $this->hierarchy['space']->id]);

    expect($space2->fresh()->position)->toBe(0);
    expect($this->hierarchy['space']->fresh()->position)->toBe(1);
});

test('addMember adds user with role', function () {
    $member = $this->createUser();

    $this->service->addMember($this->hierarchy['space'], $member, 'member', $this->owner);

    $spaceMember = $this->hierarchy['space']->members()->where('user_id', $member->id)->first();
    expect($spaceMember)->not->toBeNull();
    expect($spaceMember->pivot->role)->toBe('member');
});

test('updateMemberRole changes role', function () {
    $member = $this->createUser();
    $this->hierarchy['space']->members()->attach($member->id, ['role' => 'member']);

    $this->service->updateMemberRole($this->hierarchy['space'], $member, 'admin', $this->owner);

    $updated = $this->hierarchy['space']->members()->where('user_id', $member->id)->first();
    expect($updated->pivot->role)->toBe('admin');
});

test('removeMember detaches user', function () {
    $member = $this->createUser();
    $this->hierarchy['space']->members()->attach($member->id, ['role' => 'member']);

    $this->service->removeMember($this->hierarchy['space'], $member, $this->owner);

    expect($this->hierarchy['space']->members()->where('user_id', $member->id)->exists())->toBeFalse();
});

test('addStatus creates custom status', function () {
    $status = $this->service->addStatus($this->hierarchy['space'], [
        'name' => 'QA Testing',
        'color' => '#8B5CF6',
    ]);

    expect($status->name)->toBe('QA Testing');
    expect($status->type)->toBe('custom');
    expect($status->space_id)->toBe($this->hierarchy['space']->id);
});

test('updateStatus changes status properties', function () {
    $status = $this->service->addStatus($this->hierarchy['space'], ['name' => 'Draft']);

    $updated = $this->service->updateStatus($status, [
        'name' => 'In Review',
        'color' => '#F59E0B',
        'is_closed' => true,
    ]);

    expect($updated->name)->toBe('In Review');
    expect($updated->color)->toBe('#F59E0B');
    expect($updated->is_closed)->toBeTrue();
});

test('deleteStatus removes status', function () {
    $status = $this->service->addStatus($this->hierarchy['space'], ['name' => 'Temp Status']);

    $this->service->deleteStatus($status);

    expect(Status::find($status->id))->toBeNull();
});

test('deleteStatus moves items to replacement status', function () {
    $tempStatus = $this->service->addStatus($this->hierarchy['space'], ['name' => 'Old Status']);
    $defaultStatus = $this->hierarchy['statuses']->first();

    // Create a subtask with the temp status
    $subtask = $this->createSubtask($this->hierarchy['task'], [
        'status_id' => $tempStatus->id,
    ]);

    $this->service->deleteStatus($tempStatus, $defaultStatus->id);

    expect($subtask->fresh()->status_id)->toBe($defaultStatus->id);
});

test('reorderStatuses updates positions', function () {
    $statuses = $this->hierarchy['statuses'];

    $reversed = $statuses->reverse()->pluck('id')->values()->toArray();
    $this->service->reorderStatuses($this->hierarchy['space'], $reversed);

    $lastId = $statuses->last()->id;
    expect(Status::find($lastId)->position)->toBe(0);
});

test('getSpacesForWorkspace returns spaces with counts', function () {
    $spaces = $this->service->getSpacesForWorkspace($this->hierarchy['workspace']);

    expect($spaces->count())->toBeGreaterThanOrEqual(1);
    expect($spaces->first()->name)->toBe($this->hierarchy['space']->name);
});

test('getWithHierarchy loads full tree', function () {
    $loaded = $this->service->getWithHierarchy($this->hierarchy['space']);

    expect($loaded->relationLoaded('workspace'))->toBeTrue();
    expect($loaded->relationLoaded('statuses'))->toBeTrue();
    expect($loaded->relationLoaded('labels'))->toBeTrue();
});

test('getStatistics returns space metrics', function () {
    $stats = $this->service->getStatistics($this->hierarchy['space']);

    expect($stats)->toHaveKeys([
        'total_tasks',
        'completed_tasks',
        'in_progress_tasks',
        'overdue_tasks',
        'folders_count',
        'projects_count',
        'progress',
    ]);
    expect($stats['projects_count'])->toBeGreaterThanOrEqual(1);
});

test('getProjectsByStatus groups lists by status', function () {
    $statuses = $this->hierarchy['statuses'];
    $firstStatus = $statuses->first();

    // Assign a status to our list
    $this->hierarchy['list']->update(['status_id' => $firstStatus->id]);

    $grouped = $this->service->getProjectsByStatus($this->hierarchy['space']);

    // Result should be keyed by status id
    expect($grouped)->toBeArray();
    expect(array_key_exists($firstStatus->id, $grouped))->toBeTrue();

    // Our list should appear under its status
    $listsUnderStatus = collect($grouped[$firstStatus->id]);
    expect($listsUnderStatus->pluck('id'))->toContain($this->hierarchy['list']->id);
});

// ============================================================
// Merged from Unit/Services/SpaceServiceTest.php
// ============================================================
test('create space with name', function () {
    $space = $this->service->create(
        ['name' => 'Backend'],
        $this->hierarchy['workspace'],
        $this->owner
    );

    expect($space)->toBeInstanceOf(Space::class);
    expect($space->name)->toBe('Backend');
    expect($space->workspace_id)->toBe($this->hierarchy['workspace']->id);
});

test('create space logs activity', function () {
    $this->service->create(['name' => 'Logged'], $this->hierarchy['workspace'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Space::class)
        ->latest('id')->first();
    expect($activity)->not->toBeNull();
});

test('update space name', function () {
    $space = $this->hierarchy['space'];

    $updated = $this->service->update($space, ['name' => 'Renamed'], $this->owner);

    expect($updated->name)->toBe('Renamed');
});

test('delete space removes it', function () {
    $space = $this->service->create(['name' => 'To Delete'], $this->hierarchy['workspace'], $this->owner);

    $this->service->delete($space, $this->owner);

    expect(Space::find($space->id))->toBeNull();
});

test('addMember adds user to space with role', function () {
    $user = $this->createUser();

    $this->service->addMember($this->hierarchy['space'], $user, 'member', $this->owner);

    expect($this->hierarchy['space']->members()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('removeMember removes user from space', function () {
    $user = $this->createUser();
    $this->hierarchy['space']->members()->attach($user->id, ['role' => 'member']);

    $this->service->removeMember($this->hierarchy['space'], $user, $this->owner);

    expect($this->hierarchy['space']->members()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('updateMemberRole changes space member role', function () {
    $user = $this->createUser();
    $this->hierarchy['space']->members()->attach($user->id, ['role' => 'member']);

    $this->service->updateMemberRole($this->hierarchy['space'], $user, 'admin', $this->owner);

    $role = $this->hierarchy['space']->members()->where('user_id', $user->id)->first()->pivot->role;
    expect($role)->toBe('admin');
});
