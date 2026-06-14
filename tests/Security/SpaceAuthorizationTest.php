<?php

use App\Models\Space;
use App\Models\Status;
use App\Models\Workspace;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->owner = $this->createUser();
    $this->wsAdmin = $this->createUser();
    $this->wsMember = $this->createUser();
    $this->spaceAdmin = $this->createUser();
    $this->spaceMember = $this->createUser();
    $this->stranger = $this->createUser();

    // Set up isolated workspace and space for these tests
    $this->workspace = Workspace::create(['name' => 'WS', 'color' => '#1D4ED8']);
    $this->workspace->addMember($this->owner, 'owner');
    $this->workspace->addMember($this->wsAdmin, 'admin');
    $this->workspace->addMember($this->wsMember, 'member');
    $this->workspace->addMember($this->spaceAdmin, 'member');
    $this->workspace->addMember($this->spaceMember, 'member');

    $this->space = Space::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Test Space',
        'created_by' => $this->owner->id,
    ]);

    $this->space->members()->attach($this->spaceAdmin->id, ['role' => 'admin']);
    $this->space->members()->attach($this->spaceMember->id, ['role' => 'member']);

    // Create separate workspace for stranger
    $strangerWs = Workspace::create(['name' => 'Other WS', 'color' => '#000']);
    $strangerWs->addMember($this->stranger, 'owner');
});

// View Space
test('non-workspace member cannot view space', function () {
    actingAs($this->stranger)
        ->get(route('spaces.show', [$this->workspace->id, $this->space->id]))
        ->assertForbidden();
});

test('workspace member without space membership cannot view private space', function () {
    actingAs($this->wsMember)
        ->get(route('spaces.show', [$this->workspace->id, $this->space->id]))
        ->assertForbidden();
});

test('space member can view space', function () {
    actingAs($this->spaceMember)
        ->get(route('spaces.show', [$this->workspace->id, $this->space->id]))
        ->assertSuccessful();
});

test('workspace admin can view any space', function () {
    actingAs($this->wsAdmin)
        ->get(route('spaces.show', [$this->workspace->id, $this->space->id]))
        ->assertSuccessful();
});

// Update Space
test('space member cannot update space', function () {
    actingAs($this->spaceMember)
        ->patch(route('spaces.update', [$this->workspace->id, $this->space->id]), ['name' => 'Hacked'])
        ->assertForbidden();

    expect($this->space->fresh()->name)->toBe('Test Space');
});

test('workspace admin can update space', function () {
    actingAs($this->wsAdmin)
        ->patch(route('spaces.update', [$this->workspace->id, $this->space->id]), ['name' => 'Renamed'])
        ->assertRedirect();
});

// Delete Space
test('space member cannot delete space', function () {
    actingAs($this->spaceMember)
        ->delete(route('spaces.destroy', [$this->workspace->id, $this->space->id]))
        ->assertForbidden();

    expect(Space::find($this->space->id))->not->toBeNull();
});

test('space admin cannot delete space (workspace admin only)', function () {
    actingAs($this->spaceAdmin)
        ->delete(route('spaces.destroy', [$this->workspace->id, $this->space->id]))
        ->assertForbidden();

    expect(Space::find($this->space->id))->not->toBeNull();
});

// Status Management
test('space member cannot add status', function () {
    actingAs($this->spaceMember)
        ->post(route('spaces.statuses.add', [$this->workspace->id, $this->space->id]), [
            'name' => 'Custom Status',
            'color' => '#FF0000',
        ])
        ->assertForbidden();

    expect(Status::where('space_id', $this->space->id)->where('name', 'Custom Status')->exists())->toBeFalse();
});

test('space admin can add status', function () {
    actingAs($this->spaceAdmin)
        ->post(route('spaces.statuses.add', [$this->workspace->id, $this->space->id]), [
            'name' => 'Custom Status',
            'color' => '#FF0000',
        ])
        ->assertRedirect();
});

// Space Members
test('space member cannot add another member', function () {
    $newUser = $this->createUser();
    $this->workspace->addMember($newUser, 'member');

    actingAs($this->spaceMember)
        ->post(route('spaces.members.add', [$this->workspace->id, $this->space->id]), [
            'user_id' => $newUser->id,
            'role' => 'member',
        ])
        ->assertForbidden();

    expect($this->space->members()->where('user_id', $newUser->id)->exists())->toBeFalse();
});

test('space admin can add space member', function () {
    $newUser = $this->createUser();
    $this->workspace->addMember($newUser, 'member');

    actingAs($this->spaceAdmin)
        ->post(route('spaces.members.add', [$this->workspace->id, $this->space->id]), [
            'user_id' => $newUser->id,
            'role' => 'member',
        ])
        ->assertRedirect();
});

test('cannot add user as space member if not a workspace member', function () {
    $stranger = $this->createUser();

    actingAs($this->spaceAdmin)
        ->post(route('spaces.members.add', [$this->workspace->id, $this->space->id]), [
            'user_id' => $stranger->id,
            'role' => 'member',
        ])
        ->assertSessionHasErrors(['error']);
});

// Cross-Workspace Space Access
test('space ID from another workspace returns 404', function () {
    $otherWs = Workspace::create(['name' => 'Foreign', 'color' => '#000']);
    $otherWs->addMember($this->owner, 'owner');
    $otherSpace = Space::create([
        'workspace_id' => $otherWs->id,
        'name' => 'Foreign Space',
        'created_by' => $this->owner->id,
    ]);

    actingAs($this->owner)
        ->get(route('spaces.show', [$this->workspace->id, $otherSpace->id]))
        ->assertNotFound();
});
