
<?php

use App\Models\Workspace;
use Tests\Traits\CreatesWorkspaceHierarchy;

use function Pest\Laravel\actingAs;

uses(CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->owner = $this->createUser();
    $this->admin = $this->createUser();
    $this->member = $this->createUser();

    $this->workspace = Workspace::create([
        'name' => 'Test Workspace',
        'color' => '#1D4ED8',
    ]);
    $this->workspace->addMember($this->owner, 'owner');
    $this->workspace->addMember($this->admin, 'admin');
    $this->workspace->addMember($this->member, 'member');
});

// Update Workspace
test('member cannot update workspace', function () {
    actingAs($this->member)
        ->patch(route('workspaces.update', $this->workspace->id), ['name' => 'Hacked'])
        ->assertForbidden();

    expect($this->workspace->fresh()->name)->toBe('Test Workspace');
});

test('admin can update workspace', function () {
    actingAs($this->admin)
        ->patch(route('workspaces.update', $this->workspace->id), ['name' => 'Updated by Admin'])
        ->assertRedirect();

    expect($this->workspace->fresh()->name)->toBe('Updated by Admin');
});

test('owner can update workspace', function () {
    actingAs($this->owner)
        ->patch(route('workspaces.update', $this->workspace->id), ['name' => 'Updated by Owner'])
        ->assertRedirect();
});

// Add/Remove Members
test('member cannot add workspace members', function () {
    $newUser = $this->createUser();

    actingAs($this->member)
        ->post(route('workspaces.members.add', $this->workspace->id), [
            'user_id' => $newUser->id,
            'role' => 'member',
        ])
        ->assertForbidden();

    expect($this->workspace->members()->where('user_id', $newUser->id)->exists())->toBeFalse();
});

test('admin can add workspace members', function () {
    $newUser = $this->createUser();

    actingAs($this->admin)
        ->post(route('workspaces.members.add', $this->workspace->id), [
            'user_id' => $newUser->id,
            'role' => 'member',
        ])
        ->assertRedirect();

    expect($this->workspace->members()->where('user_id', $newUser->id)->exists())->toBeTrue();
});

test('member cannot remove workspace members', function () {
    actingAs($this->member)
        ->delete(route('workspaces.members.remove', $this->workspace->id), [
            'user_id' => $this->admin->id,
        ])
        ->assertForbidden();

    expect($this->workspace->members()->where('user_id', $this->admin->id)->exists())->toBeTrue();
});

test('admin cannot remove themselves', function () {
    actingAs($this->admin)
        ->delete(route('workspaces.members.remove', $this->workspace->id), [
            'user_id' => $this->admin->id,
        ])
        ->assertSessionHasErrors(['error']);
});

// Update Member Role
test('member cannot update member roles', function () {
    actingAs($this->member)
        ->patch(route('workspaces.members.role', $this->workspace->id), [
            'user_id' => $this->member->id,
            'role' => 'admin',
        ])
        ->assertForbidden();

    $role = $this->workspace->members()->where('user_id', $this->member->id)->first()->pivot->role;
    expect($role)->toBe('member');
});

test('admin can update member roles', function () {
    actingAs($this->admin)
        ->patch(route('workspaces.members.role', $this->workspace->id), [
            'user_id' => $this->member->id,
            'role' => 'admin',
        ])
        ->assertRedirect();

    $newRole = $this->workspace->members()->where('user_id', $this->member->id)->first()->pivot->role;
    expect($newRole)->toBe('admin');
});

// Delete Workspace
test('admin cannot delete workspace, only owner can', function () {
    actingAs($this->admin)
        ->delete(route('workspaces.destroy', $this->workspace->id))
        ->assertForbidden();
});

test('owner can delete workspace', function () {
    actingAs($this->owner)
        ->delete(route('workspaces.destroy', $this->workspace->id))
        ->assertRedirect(route('dashboard'));
});

// View Workspace Settings
test('non-member cannot view workspace settings', function () {
    $stranger = $this->createUser();

    actingAs($stranger)
        ->get(route('workspaces.settings', $this->workspace->id))
        ->assertForbidden();
});

test('member can view workspace settings (read-only)', function () {
    actingAs($this->member)
        ->get(route('workspaces.settings', $this->workspace->id))
        ->assertSuccessful();
});

// Recycle Bin
test('non-member cannot access recycle bin', function () {
    $stranger = $this->createUser();

    actingAs($stranger)
        ->get(route('workspaces.recycle-bin.index', $this->workspace->id))
        ->assertForbidden();
});

test('member can access recycle bin', function () {
    actingAs($this->member)
        ->get(route('workspaces.recycle-bin.index', $this->workspace->id))
        ->assertSuccessful();
});

// Switch Workspace
test('non-member cannot switch to workspace', function () {
    $stranger = $this->createUser();
    // Stranger needs to be in another workspace to be authenticated context
    $otherWs = Workspace::create(['name' => 'Other', 'color' => '#000']);
    $otherWs->addMember($stranger, 'owner');

    actingAs($stranger)
        ->post(route('workspaces.switch', $this->workspace->id))
        ->assertForbidden();
});

// Privilege Escalation
test('member cannot self-promote to admin via role update', function () {
    actingAs($this->member)
        ->patch(route('workspaces.members.role', $this->workspace->id), [
            'user_id' => $this->member->id,
            'role' => 'admin',
        ])
        ->assertForbidden();

    $role = $this->workspace->members()->where('user_id', $this->member->id)->first()->pivot->role;
    expect($role)->toBe('member');
});
