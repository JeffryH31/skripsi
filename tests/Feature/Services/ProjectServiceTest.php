<?php

use App\Models\Activity;
use App\Models\Folder;
use App\Models\Project;
use App\Services\ProjectService;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new ProjectService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('create list in space', function () {
    $list = $this->service->create([
        'name' => 'Product Backlog',
    ], $this->hierarchy['space'], $this->owner);

    expect($list)->toBeInstanceOf(Project::class);
    expect($list->name)->toBe('Product Backlog');
    expect($list->space_id)->toBe($this->hierarchy['space']->id);
    expect($list->created_by)->toBe($this->owner->id);
});

test('create list adds creator as project_owner member', function () {
    $list = $this->service->create([
        'name' => 'New Product',
    ], $this->hierarchy['space'], $this->owner);

    $member = $list->members()->where('user_id', $this->owner->id)->first();
    expect($member)->not->toBeNull();
    expect($member->pivot->role)->toBe('project_owner');
});

test('create list logs created activity', function () {
    $this->service->create([
        'name' => 'Logged List',
    ], $this->hierarchy['space'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Project::class)
        ->first();

    expect($activity)->not->toBeNull();
});

test('create list in folder', function () {
    $folder = Folder::create([
        'space_id' => $this->hierarchy['space']->id,
        'name' => 'Dev Folder',
        'created_by' => $this->owner->id,
    ]);

    $list = $this->service->create([
        'name' => 'In Folder',
    ], $this->hierarchy['space'], $this->owner, $folder);

    expect($list->folder_id)->toBe($folder->id);
});

test('update list changes name and logs activity', function () {
    $updated = $this->service->update($this->hierarchy['list'], [
        'name' => 'Renamed List',
    ], $this->owner);

    expect($updated->name)->toBe('Renamed List');

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Project::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('delete list soft deletes and logs activity', function () {
    $list = $this->service->create(['name' => 'Delete Me'], $this->hierarchy['space'], $this->owner);

    $this->service->delete($list, $this->owner);

    expect(Project::find($list->id))->toBeNull();
    expect(Project::withTrashed()->find($list->id))->not->toBeNull();
});

test('addMember adds user with role', function () {
    $dev = $this->createUser();

    $this->service->addMember($this->hierarchy['list'], $dev, 'development_team', $this->owner);

    $member = $this->hierarchy['list']->members()->where('user_id', $dev->id)->first();
    expect($member)->not->toBeNull();
    expect($member->pivot->role)->toBe('development_team');
});

test('updateMemberRole changes role', function () {
    $dev = $this->createUser();
    $this->hierarchy['list']->addMember($dev, 'development_team');

    $this->service->updateMemberRole($this->hierarchy['list'], $dev, 'project_manager', $this->owner);

    $member = $this->hierarchy['list']->members()->where('user_id', $dev->id)->first();
    expect($member->pivot->role)->toBe('project_manager');
});

test('removeMember detaches user', function () {
    $dev = $this->createUser();
    $this->hierarchy['list']->addMember($dev, 'development_team');

    $this->service->removeMember($this->hierarchy['list'], $dev, $this->owner);

    expect($this->hierarchy['list']->members()->where('user_id', $dev->id)->exists())->toBeFalse();
});

test('moveToFolder changes folder_id', function () {
    $folder = Folder::create([
        'space_id' => $this->hierarchy['space']->id,
        'name' => 'Target Folder',
        'created_by' => $this->owner->id,
    ]);

    $moved = $this->service->moveToFolder($this->hierarchy['list'], $folder, $this->owner);

    expect($moved->folder_id)->toBe($folder->id);

    $activity = Activity::where('action', 'moved')
        ->where('subject_type', Project::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('reorder updates list positions', function () {
    $list1 = $this->service->create(['name' => 'List 1'], $this->hierarchy['space'], $this->owner);
    $list2 = $this->service->create(['name' => 'List 2'], $this->hierarchy['space'], $this->owner);

    $this->service->reorder($this->hierarchy['space'], [$list2->id, $list1->id]);

    expect($list2->fresh()->position)->toBe(0);
    expect($list1->fresh()->position)->toBe(1);
});

test('duplicate list clones tasks and subtasks', function () {
    $this->createSubtask($this->hierarchy['task'], ['name' => 'Subtask A']);

    $duplicate = $this->service->duplicate($this->hierarchy['list'], $this->owner);

    expect($duplicate->name)->toContain('(Copy)');
    expect($duplicate->tasks)->toHaveCount($this->hierarchy['list']->tasks()->count());

    $activity = Activity::where('action', 'duplicated')
        ->where('subject_type', Project::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('getListsForSpace returns lists ordered by position', function () {
    $this->service->create(['name' => 'Extra List'], $this->hierarchy['space'], $this->owner);

    $lists = $this->service->getListsForSpace($this->hierarchy['space']);

    expect($lists->count())->toBeGreaterThanOrEqual(2);
});

test('getWithTasksByStatus groups items by status', function () {
    $grouped = $this->service->getWithTasksByStatus($this->hierarchy['list']);

    expect($grouped)->toBeArray();

    // Should have keys for each status
    foreach ($this->hierarchy['statuses'] as $status) {
        expect(array_key_exists($status->id, $grouped))->toBeTrue();
    }
});

// ============================================================
// Merged from Unit/Services/ProjectServiceTest.php
// ============================================================
test('create list adds creator as project_owner', function () {
    $list = $this->service->create(
        ['name' => 'My List'],
        $this->hierarchy['space'],
        $this->owner
    );

    $member = $list->members()->where('user_id', $this->owner->id)->first();
    expect($member)->not->toBeNull();
    expect($member->pivot->role)->toBe('project_owner');
});

test('create list logs activity', function () {
    $this->service->create(['name' => 'Logged'], $this->hierarchy['space'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Project::class)
        ->latest('id')->first();
    expect($activity)->not->toBeNull();
});

test('update list name', function () {
    $list = $this->hierarchy['list'];

    $updated = $this->service->update($list, ['name' => 'Renamed List'], $this->owner);

    expect($updated->name)->toBe('Renamed List');
});

test('update list logs activity when changed', function () {
    $list = $this->hierarchy['list'];
    Activity::truncate();

    $this->service->update($list, ['name' => 'Different Name'], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Project::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('delete list soft deletes it', function () {
    $list = $this->service->create(['name' => 'Delete Me'], $this->hierarchy['space'], $this->owner);

    $this->service->delete($list, $this->owner);

    expect(Project::find($list->id))->toBeNull();
    expect(Project::withTrashed()->find($list->id))->not->toBeNull();
});

test('addMember adds user to list', function () {
    $user = $this->createUser();

    $this->service->addMember($this->hierarchy['list'], $user, 'development_team', $this->owner);

    expect($this->hierarchy['list']->members()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('removeMember removes user from list', function () {
    $user = $this->createUser();
    $this->hierarchy['list']->addMember($user, 'development_team');

    $this->service->removeMember($this->hierarchy['list'], $user, $this->owner);

    expect($this->hierarchy['list']->members()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('moveToFolder updates folder_id', function () {
    $list = $this->service->create(['name' => 'Movable'], $this->hierarchy['space'], $this->owner);
    $folder = Folder::create([
        'space_id' => $this->hierarchy['space']->id,
        'name' => 'Destination',
        'slug' => 'destination',
        'created_by' => $this->owner->id,
    ]);

    $moved = $this->service->moveToFolder($list, $folder, $this->owner);

    expect($moved->folder_id)->toBe($folder->id);
});
