<?php

use App\Models\Activity;
use App\Models\Folder;
use App\Services\FolderService;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->service = new FolderService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

test('create folder in space', function () {
    $folder = $this->service->create([
        'name' => 'Backend',
    ], $this->hierarchy['space'], $this->owner);

    expect($folder)->toBeInstanceOf(Folder::class);
    expect($folder->name)->toBe('Backend');
    expect($folder->space_id)->toBe($this->hierarchy['space']->id);
    expect($folder->created_by)->toBe($this->owner->id);
});

test('create folder logs created activity', function () {
    $this->service->create([
        'name' => 'Logged Folder',
    ], $this->hierarchy['space'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Folder::class)
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties['space_name'])->toBe($this->hierarchy['space']->name);
});

test('create nested folder with parent', function () {
    $parent = $this->service->create(['name' => 'Parent'], $this->hierarchy['space'], $this->owner);
    $child = $this->service->create(['name' => 'Child'], $this->hierarchy['space'], $this->owner, $parent);

    expect($child->parent_id)->toBe($parent->id);
});

test('update folder changes name and logs activity', function () {
    $folder = $this->service->create(['name' => 'Old Name'], $this->hierarchy['space'], $this->owner);

    $updated = $this->service->update($folder, ['name' => 'New Name'], $this->owner);

    expect($updated->name)->toBe('New Name');

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Folder::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('update folder without name change does not log activity', function () {
    $folder = $this->service->create(['name' => 'Same Name'], $this->hierarchy['space'], $this->owner);

    $this->service->update($folder, ['description' => 'New description'], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Folder::class)
        ->first();
    expect($activity)->toBeNull();
});

test('delete folder removes it and logs activity', function () {
    $folder = $this->service->create(['name' => 'Delete Me'], $this->hierarchy['space'], $this->owner);

    $this->service->delete($folder, $this->owner);

    expect(Folder::find($folder->id))->toBeNull();

    $activity = Activity::where('action', 'deleted')
        ->where('subject_type', Folder::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('move folder to new parent', function () {
    $folder = $this->service->create(['name' => 'Movable'], $this->hierarchy['space'], $this->owner);
    $newParent = $this->service->create(['name' => 'New Parent'], $this->hierarchy['space'], $this->owner);

    $moved = $this->service->move($folder, $newParent, $this->owner);

    expect($moved->parent_id)->toBe($newParent->id);

    $activity = Activity::where('action', 'moved')
        ->where('subject_type', Folder::class)
        ->first();
    expect($activity)->not->toBeNull();
});

test('move folder to root clears parent_id', function () {
    $parent = $this->service->create(['name' => 'Parent'], $this->hierarchy['space'], $this->owner);
    $child = $this->service->create(['name' => 'Child'], $this->hierarchy['space'], $this->owner, $parent);

    $moved = $this->service->move($child, null, $this->owner);

    expect($moved->parent_id)->toBeNull();
});

test('reorder updates folder positions', function () {
    $f1 = $this->service->create(['name' => 'Folder 1'], $this->hierarchy['space'], $this->owner);
    $f2 = $this->service->create(['name' => 'Folder 2'], $this->hierarchy['space'], $this->owner);

    $this->service->reorder($this->hierarchy['space'], [$f2->id, $f1->id]);

    expect($f2->fresh()->position)->toBe(0);
    expect($f1->fresh()->position)->toBe(1);
});

test('getFoldersForSpace returns folders with children and lists', function () {
    $this->service->create(['name' => 'Folder A'], $this->hierarchy['space'], $this->owner);

    $folders = $this->service->getFoldersForSpace($this->hierarchy['space']);

    expect($folders->count())->toBeGreaterThanOrEqual(1);
});

// ============================================================
// Merged from Unit/Services/FolderServiceTest.php
// ============================================================
test('create folder with name', function () {
    $folder = $this->service->create(
        ['name' => 'New Folder'],
        $this->hierarchy['space'],
        $this->owner
    );

    expect($folder)->toBeInstanceOf(Folder::class);
    expect($folder->name)->toBe('New Folder');
    expect($folder->space_id)->toBe($this->hierarchy['space']->id);
});

test('create folder with parent', function () {
    $parent = $this->service->create(['name' => 'Parent'], $this->hierarchy['space'], $this->owner);

    $child = $this->service->create(['name' => 'Child'], $this->hierarchy['space'], $this->owner, $parent);

    expect($child->parent_id)->toBe($parent->id);
});

test('create folder logs activity', function () {
    $this->service->create(['name' => 'Logged'], $this->hierarchy['space'], $this->owner);

    $activity = Activity::where('action', 'created')
        ->where('subject_type', Folder::class)
        ->latest('id')->first();
    expect($activity)->not->toBeNull();
});

test('update folder name', function () {
    $folder = $this->service->create(['name' => 'Old'], $this->hierarchy['space'], $this->owner);

    $updated = $this->service->update($folder, ['name' => 'New'], $this->owner);

    expect($updated->name)->toBe('New');
});

test('update folder logs activity when name changes', function () {
    $folder = $this->service->create(['name' => 'Before'], $this->hierarchy['space'], $this->owner);

    $this->service->update($folder, ['name' => 'After'], $this->owner);

    $activity = Activity::where('action', 'updated')
        ->where('subject_type', Folder::class)
        ->where('subject_id', $folder->id)
        ->first();
    expect($activity)->not->toBeNull();
});

test('delete folder removes it', function () {
    $folder = $this->service->create(['name' => 'Delete Me'], $this->hierarchy['space'], $this->owner);

    $this->service->delete($folder, $this->owner);

    expect(Folder::find($folder->id))->toBeNull();
});
