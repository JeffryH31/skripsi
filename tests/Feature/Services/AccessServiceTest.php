<?php

use App\Models\Comment;
use App\Models\Space;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Services\AccessService;

uses(Tests\Traits\CreatesWorkspaceHierarchy::class);

beforeEach(function () {
    $this->access = new AccessService;
    $this->owner = $this->createUser();
    $this->hierarchy = $this->createFullHierarchy($this->owner);
});

// Workspace-level
test('owner can view workspace', function () {
    expect($this->access->canViewWorkspace($this->owner, $this->hierarchy['workspace']))->toBeTrue();
});

test('member can view workspace', function () {
    $member = $this->createUser();
    $this->hierarchy['workspace']->addMember($member, 'member');

    expect($this->access->canViewWorkspace($member, $this->hierarchy['workspace']))->toBeTrue();
});

test('stranger cannot view workspace', function () {
    $stranger = $this->createUser();

    expect($this->access->canViewWorkspace($stranger, $this->hierarchy['workspace']))->toBeFalse();
});

test('owner can manage workspace', function () {
    expect($this->access->canManageWorkspace($this->owner, $this->hierarchy['workspace']))->toBeTrue();
});

test('admin can manage workspace', function () {
    $admin = $this->createUser();
    $this->hierarchy['workspace']->addMember($admin, 'admin');

    expect($this->access->canManageWorkspace($admin, $this->hierarchy['workspace']))->toBeTrue();
});

test('member cannot manage workspace', function () {
    $member = $this->createUser();
    $this->hierarchy['workspace']->addMember($member, 'member');

    expect($this->access->canManageWorkspace($member, $this->hierarchy['workspace']))->toBeFalse();
});

test('only owner can delete workspace', function () {
    $admin = $this->createUser();
    $this->hierarchy['workspace']->addMember($admin, 'admin');

    expect($this->access->canDeleteWorkspace($this->owner, $this->hierarchy['workspace']))->toBeTrue();
    expect($this->access->canDeleteWorkspace($admin, $this->hierarchy['workspace']))->toBeFalse();
});

// Space-level
test('workspace member without space membership cannot view space', function () {
    $member = $this->createUser();
    $this->hierarchy['workspace']->addMember($member, 'member');

    expect($this->access->canViewSpace($member, $this->hierarchy['space']))->toBeFalse();
});

test('workspace member with space membership can view space', function () {
    $member = $this->createUser();
    $this->hierarchy['workspace']->addMember($member, 'member');
    $this->hierarchy['space']->members()->attach($member->id, ['role' => 'member']);

    expect($this->access->canViewSpace($member, $this->hierarchy['space']))->toBeTrue();
});

test('only explicit members can view space', function () {
    $member = $this->createUser();
    $spaceMember = $this->createUser();

    // Add both as workspace members
    $this->hierarchy['workspace']->addMember($member, 'member');
    $this->hierarchy['workspace']->addMember($spaceMember, 'member');

    // Only spaceMember gets space-level access
    $this->hierarchy['space']->members()->attach($spaceMember->id, ['role' => 'member']);

    expect($this->access->canViewSpace($spaceMember, $this->hierarchy['space']))->toBeTrue();
    expect($this->access->canViewSpace($member, $this->hierarchy['space']))->toBeFalse();
});

test('workspace owner can always view any space', function () {

    expect($this->access->canViewSpace($this->owner, $this->hierarchy['space']))->toBeTrue();
});

test('workspace owner can manage space', function () {
    expect($this->access->canManageSpace($this->owner, $this->hierarchy['space']))->toBeTrue();
});

test('space admin can manage space', function () {
    $manager = $this->createUser();
    $this->hierarchy['workspace']->addMember($manager, 'member');
    $this->hierarchy['space']->members()->attach($manager->id, ['role' => 'admin']);

    expect($this->access->canManageSpace($manager, $this->hierarchy['space']))->toBeTrue();
});

test('space member cannot manage space', function () {
    $member = $this->createUser();
    $this->hierarchy['workspace']->addMember($member, 'member');
    $this->hierarchy['space']->members()->attach($member->id, ['role' => 'member']);

    expect($this->access->canManageSpace($member, $this->hierarchy['space']))->toBeFalse();
});

// Project-level
test('project member can view project', function () {
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    expect($this->access->canViewProject($dev, $this->hierarchy['list']))->toBeTrue();
});

test('inherits view access from space when no project members configured', function () {
    $member = $this->createUser();
    $this->hierarchy['workspace']->addMember($member, 'member');

    // No list members configured — should inherit from space
    expect($this->access->canViewProject($member, $this->hierarchy['list']))->toBeTrue();
});

test('project_owner can manage project', function () {
    $pm = $this->createUser();
    $this->hierarchy['workspace']->addMember($pm, 'member');
    $this->hierarchy['list']->addMember($pm, 'project_owner');

    expect($this->access->canManageProject($pm, $this->hierarchy['list']))->toBeTrue();
});

test('developer cannot manage project', function () {
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    expect($this->access->canManageProject($dev, $this->hierarchy['list']))->toBeFalse();
});

// Task operations
test('developer can edit tasks', function () {
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    expect($this->access->canEditTasks($dev, $this->hierarchy['list']))->toBeTrue();
});

test('guest cannot edit tasks', function () {
    $guest = $this->createUser();
    $this->hierarchy['workspace']->addMember($guest, 'member');
    $this->hierarchy['list']->addMember($guest, 'guest');

    expect($this->access->canEditTasks($guest, $this->hierarchy['list']))->toBeFalse();
});

// Analytics
test('owner can view analytics', function () {
    expect($this->access->canViewAnalytics($this->owner, $this->hierarchy['workspace']))->toBeTrue();
});

test('member can view analytics', function () {
    $member = $this->createUser();
    $this->hierarchy['workspace']->addMember($member, 'member');

    expect($this->access->canViewAnalytics($member, $this->hierarchy['workspace']))->toBeTrue();
});

test('guest cannot view analytics', function () {
    $nonMember = $this->createUser();

    expect($this->access->canViewAnalytics($nonMember, $this->hierarchy['workspace']))->toBeFalse();
});

// Comment ownership
test('comment author can manage own comment', function () {
    $other = $this->createUser();

    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $this->owner->id,
        'content' => 'Test comment',
    ]);

    expect($this->access->canManageComment($this->owner, $comment))->toBeTrue();
    expect($this->access->canManageComment($other, $comment))->toBeFalse();
});

// canAccessWebsite
test('user with workspace can access website', function () {
    expect($this->access->canAccessWebsite($this->owner))->toBeTrue();
});

test('user without workspace cannot access website', function () {
    $user = $this->createUser();

    expect($this->access->canAccessWebsite($user))->toBeFalse();
});

// Role getters
test('getWorkspaceRole returns correct role', function () {
    $member = $this->createUser();
    $stranger = $this->createUser();
    $this->hierarchy['workspace']->addMember($member, 'member');

    expect($this->access->getWorkspaceRole($this->owner, $this->hierarchy['workspace']))->toBe('owner');
    expect($this->access->getWorkspaceRole($member, $this->hierarchy['workspace']))->toBe('member');
    expect($this->access->getWorkspaceRole($stranger, $this->hierarchy['workspace']))->toBeNull();
});

test('getSpaceRole returns correct role', function () {
    $spaceMember = $this->createUser();
    $this->hierarchy['workspace']->addMember($spaceMember, 'member');
    $this->hierarchy['space']->members()->attach($spaceMember->id, ['role' => 'admin']);

    expect($this->access->getSpaceRole($spaceMember, $this->hierarchy['space']))->toBe('admin');
    expect($this->access->getSpaceRole($this->owner, $this->hierarchy['space']))->toBeNull();
});

test('getProjectRole returns correct role', function () {
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    expect($this->access->getProjectRole($dev, $this->hierarchy['list']))->toBe('development_team');
    expect($this->access->getProjectRole($dev, $this->hierarchy['list']))->toBe('development_team');
});

// canDeleteProject
test('project_owner can delete project', function () {
    $pm = $this->createUser();
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($pm, 'member');
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($pm, 'project_owner');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    expect($this->access->canDeleteProject($pm, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canDeleteProject($dev, $this->hierarchy['list']))->toBeFalse();
});

// canManageProjectMembers
test('project_owner can manage project members', function () {
    $pm = $this->createUser();
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($pm, 'member');
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($pm, 'project_owner');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    expect($this->access->canManageProjectMembers($pm, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canManageProjectMembers($dev, $this->hierarchy['list']))->toBeFalse();
});

// canOperateTasks
test('developer can operate tasks', function () {
    $dev = $this->createUser();
    $guest = $this->createUser();
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['workspace']->addMember($guest, 'member');
    $this->hierarchy['list']->addMember($dev, 'development_team');
    $this->hierarchy['list']->addMember($guest, 'guest');

    expect($this->access->canOperateTasks($dev, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canOperateTasks($guest, $this->hierarchy['list']))->toBeFalse();
});

// canManageTaskStructure / canManageLabels / canManageDependencies
test('project_manager can manage task structure', function () {
    $pm = $this->createUser();
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($pm, 'member');
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($pm, 'project_manager');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    expect($this->access->canManageTaskStructure($pm, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canManageTaskStructure($dev, $this->hierarchy['list']))->toBeFalse();
    expect($this->access->canManageLabels($pm, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canManageDependencies($pm, $this->hierarchy['list']))->toBeTrue();
});

// canAssignTasks
test('project_manager can assign tasks', function () {
    $pm = $this->createUser();
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($pm, 'member');
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($pm, 'project_manager');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    expect($this->access->canAssignTasks($pm, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canAssignTasks($dev, $this->hierarchy['list']))->toBeFalse();
});

// canTrackTime
test('developer can track time', function () {
    $dev = $this->createUser();
    $guest = $this->createUser();
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['workspace']->addMember($guest, 'member');
    $this->hierarchy['list']->addMember($dev, 'development_team');
    $this->hierarchy['list']->addMember($guest, 'guest');

    expect($this->access->canTrackTime($dev, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canTrackTime($guest, $this->hierarchy['list']))->toBeFalse();
});

// canComment
test('anyone with view access can comment', function () {
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    expect($this->access->canComment($dev, $this->hierarchy['list']))->toBeTrue();
});

// canManageTimeEntry
test('user can manage own time entry', function () {
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    $subtask = $this->createSubtask($this->hierarchy['task']);
    $entry = TimeEntry::create([
        'subtask_id' => $subtask->id,
        'user_id' => $dev->id,
        'duration' => 30,
        'started_at' => now(),
    ]);

    expect($this->access->canManageTimeEntry($dev, $entry))->toBeTrue();
});

// canViewActivity
test('project member can view activity', function () {
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    expect($this->access->canViewActivity($dev, $this->hierarchy['list']))->toBeTrue();
});

// CRITICAL: Workspace admin vs workspace owner on task operations
//
test('workspace admin CANNOT edit tasks on projects without a project role', function () {
    $admin = $this->createUser();
    $this->hierarchy['workspace']->addMember($admin, 'admin');
    // Admin has NO project-level role on this list
    expect($this->access->canOperateTasks($admin, $this->hierarchy['list']))->toBeFalse();
    expect($this->access->canManageTaskStructure($admin, $this->hierarchy['list']))->toBeFalse();
    expect($this->access->canAssignTasks($admin, $this->hierarchy['list']))->toBeFalse();
    expect($this->access->canTrackTime($admin, $this->hierarchy['list']))->toBeFalse();
    expect($this->access->canManageLabels($admin, $this->hierarchy['list']))->toBeFalse();
    expect($this->access->canManageDependencies($admin, $this->hierarchy['list']))->toBeFalse();
});

test('workspace admin CAN edit tasks when given a project role', function () {
    $admin = $this->createUser();
    $this->hierarchy['workspace']->addMember($admin, 'admin');
    $this->hierarchy['list']->addMember($admin, 'development_team');

    expect($this->access->canOperateTasks($admin, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canTrackTime($admin, $this->hierarchy['list']))->toBeTrue();
    // development_team cannot manage structure or assign
    expect($this->access->canManageTaskStructure($admin, $this->hierarchy['list']))->toBeFalse();
    expect($this->access->canAssignTasks($admin, $this->hierarchy['list']))->toBeFalse();
});

test('workspace admin with project_manager role CAN manage task structure', function () {
    $admin = $this->createUser();
    $this->hierarchy['workspace']->addMember($admin, 'admin');
    $this->hierarchy['list']->addMember($admin, 'project_manager');

    expect($this->access->canManageTaskStructure($admin, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canAssignTasks($admin, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canOperateTasks($admin, $this->hierarchy['list']))->toBeTrue();
});

test('workspace owner CAN do everything on any project without a project role', function () {
    // Owner has NO explicit project role
    expect($this->access->canOperateTasks($this->owner, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canManageTaskStructure($this->owner, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canAssignTasks($this->owner, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canTrackTime($this->owner, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canManageLabels($this->owner, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canManageDependencies($this->owner, $this->hierarchy['list']))->toBeTrue();
});

test('workspace admin CAN still manage projects and members (oversight)', function () {
    $admin = $this->createUser();
    $this->hierarchy['workspace']->addMember($admin, 'admin');
    // Admin has NO project role — but should still manage project settings & members
    expect($this->access->canViewProject($admin, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canManageProject($admin, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canDeleteProject($admin, $this->hierarchy['list']))->toBeTrue();
    expect($this->access->canManageProjectMembers($admin, $this->hierarchy['list']))->toBeTrue();
});

// canResolveComment
test('comment author can resolve own comment', function () {
    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $this->owner->id,
        'content' => 'Test comment',
    ]);

    expect($this->access->canResolveComment($this->owner, $comment))->toBeTrue();
});

test('project manager can resolve any comment in their project', function () {
    $author = $this->createUser();
    $pm = $this->createUser();
    $this->hierarchy['workspace']->addMember($author, 'member');
    $this->hierarchy['workspace']->addMember($pm, 'member');
    $this->hierarchy['list']->addMember($author, 'development_team');
    $this->hierarchy['list']->addMember($pm, 'project_manager');

    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $author->id,
        'content' => 'Test comment',
    ]);

    expect($this->access->canResolveComment($pm, $comment))->toBeTrue();
});

test('developer cannot resolve others comments', function () {
    $author = $this->createUser();
    $dev = $this->createUser();
    $this->hierarchy['workspace']->addMember($author, 'member');
    $this->hierarchy['workspace']->addMember($dev, 'member');
    $this->hierarchy['list']->addMember($author, 'development_team');
    $this->hierarchy['list']->addMember($dev, 'development_team');

    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $author->id,
        'content' => 'Test comment',
    ]);

    expect($this->access->canResolveComment($dev, $comment))->toBeFalse();
});

// canManageComment (workspace owner moderation)
test('workspace owner can moderate any comment', function () {
    $author = $this->createUser();
    $this->hierarchy['workspace']->addMember($author, 'member');
    $this->hierarchy['list']->addMember($author, 'development_team');

    $comment = Comment::create([
        'task_id' => $this->hierarchy['task']->id,
        'user_id' => $author->id,
        'content' => 'Test comment',
    ]);

    expect($this->access->canManageComment($this->owner, $comment))->toBeTrue();
});
