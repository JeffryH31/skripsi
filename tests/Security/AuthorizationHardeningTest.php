<?php

use App\Models\Folder;
use App\Models\Label;
use App\Models\Project;
use App\Models\Space;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;

uses(CreatesWorkspaceHierarchy::class);

test('workspace member cannot delete workspace', function () {
    $admin = User::factory()->create();
    $member = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Delete Guard',
        'color' => '#0EA5E9',
    ]);
    $workspace->addMember($admin, 'admin');
    $workspace->addMember($member, 'member');

    actingAs($member)
        ->delete(route('workspaces.destroy', $workspace->id))
        ->assertForbidden();

    expect(Workspace::find($workspace->id))->not->toBeNull();
});

test('cannot attach label from different workspace to task', function () {
    $owner = User::factory()->create();

    $primary = $this->createFullHierarchy($owner, 'Primary');
    $secondaryWorkspace = Workspace::create([
        'name' => 'Secondary Workspace',
        'color' => '#DC2626',
    ]);
    $secondaryWorkspace->addMember($owner, 'admin');

    $foreignLabel = Label::create([
        'workspace_id' => $secondaryWorkspace->id,
        'name' => 'Foreign Label',
        'color' => '#EF4444',
    ]);

    actingAs($owner)
        ->post(route('tasks.labels.add', [
            $primary['workspace']->id,
            $primary['space']->id,
            $primary['list']->id,
            $primary['task']->id,
        ]), [
            'label_id' => $foreignLabel->id,
        ])
        ->assertSessionHasErrors(['label_id']);

    expect($primary['task']->fresh()->labels()->where('label_id', $foreignLabel->id)->exists())->toBeFalse();
});

test('scoped bindings reject task outside list context', function () {
    $owner = User::factory()->create();
    $hierarchy = $this->createFullHierarchy($owner, 'Scope');

    $otherList = Project::create([
        'space_id' => $hierarchy['space']->id,
        'name' => 'Other List',
        'description' => 'Other List description',
        'created_by' => $owner->id,
    ]);

    $otherTask = Task::create([
        'project_id' => $otherList->id,
        'name' => 'Task In Other List',
        'description' => 'Task In Other List description',
        'created_by' => $owner->id,
    ]);

    actingAs($owner)
        ->patch(route('tasks.update', [
            $hierarchy['workspace']->id,
            $hierarchy['space']->id,
            $hierarchy['list']->id,
            $otherTask->id,
        ]), ['name' => 'Hacked'])
        ->assertNotFound();

    expect($otherTask->fresh()->name)->toBe('Task In Other List');
});

test('cannot create folder using parent from another space', function () {
    $owner = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Folder Scope Guard',
        'description' => 'Folder Scope Guard description',
        'color' => '#16A34A',
    ]);
    $workspace->addMember($owner, 'admin');

    $spaceA = Space::create([
        'workspace_id' => $workspace->id,
        'name' => 'Space A',
        'description' => 'Space A description',
        'created_by' => $owner->id,
    ]);

    $spaceB = Space::create([
        'workspace_id' => $workspace->id,
        'name' => 'Space B',
        'description' => 'Space B description',
        'created_by' => $owner->id,
    ]);

    $foreignParent = Folder::create([
        'space_id' => $spaceB->id,
        'name' => 'Foreign Parent Folder',
        'created_by' => $owner->id,
    ]);

    actingAs($owner)
        ->post(route('folders.store', [$workspace->id, $spaceA->id]), [
            'name' => 'New Folder',
            'parent_id' => $foreignParent->id,
        ])
        ->assertSessionHasErrors(['error']);

    expect(Folder::where('space_id', $spaceA->id)->where('name', 'New Folder')->exists())->toBeFalse();
});

test('workspace member without manage permission cannot create folder', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $workspace = Workspace::create([
        'name' => 'Folder Permission Guard',
        'description' => 'Folder Permission Guard description',
        'color' => '#9333EA',
    ]);
    $workspace->addMember($owner, 'admin');
    $workspace->addMember($member, 'member');

    $space = Space::create([
        'workspace_id' => $workspace->id,
        'name' => 'Public Space',
        'description' => 'Public Space description',
        'created_by' => $owner->id,
    ]);

    $space->members()->attach($member->id, ['role' => 'member']);

    actingAs($member)
        ->post(route('folders.store', [$workspace->id, $space->id]), [
            'name' => 'Unauthorized Folder',
        ])
        ->assertForbidden();

    expect(Folder::where('space_id', $space->id)->where('name', 'Unauthorized Folder')->exists())->toBeFalse();
});
