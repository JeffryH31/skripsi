<?php

namespace App\Services;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Space;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;

class AccessService
{
    public const WORKSPACE_OWNER = 'owner';

    public const WORKSPACE_ADMIN = 'admin';

    public const WORKSPACE_MEMBER = 'member';

    public const SPACE_ADMIN = 'admin';

    public const SPACE_MEMBER = 'member';

    public const SPACE_GUEST = 'guest';

    public const PROJECT_OWNER = 'project_owner';

    public const PROJECT_MANAGER = 'project_manager';

    public const PROJECT_DEVELOPER = 'development_team';

    public const PROJECT_GUEST = 'guest';

    /** @var array<string, ?string> Per-request role cache to avoid repeated DB queries.
     */
    private array $roleCache = [];

    private function cacheKey(string $scope, int $userId, int $entityId): string
    {
        return "{$scope}:{$userId}:{$entityId}";
    }

    // Role getters
    /**
     * Determine whether the user can access the application.
     */
    public function canAccessWebsite(User $user): bool
    {
        return ! is_null($user->id) && $user->workspaces()->exists();
    }

    /**
     * Get workspace membership role for a user.
     */
    public function getWorkspaceRole(User $user, Workspace $workspace): ?string
    {
        $key = $this->cacheKey('ws', $user->id, $workspace->id);

        if (! array_key_exists($key, $this->roleCache)) {
            $this->roleCache[$key] = $workspace->members()
                ->where('user_id', $user->id)
                ->first()?->pivot?->role;
        }

        return $this->roleCache[$key];
    }

    /**
     * Get space membership role for a user.
     */
    public function getSpaceRole(User $user, Space $space): ?string
    {
        $key = $this->cacheKey('sp', $user->id, $space->id);

        if (! array_key_exists($key, $this->roleCache)) {
            $this->roleCache[$key] = $space->members()
                ->where('user_id', $user->id)
                ->first()?->pivot?->role;
        }

        return $this->roleCache[$key];
    }

    /**
     * Get project membership role for a user.
     */
    public function getProjectRole(User $user, Project $list): ?string
    {
        $key = $this->cacheKey('pj', $user->id, $list->id);

        if (! array_key_exists($key, $this->roleCache)) {
            $this->roleCache[$key] = $list->members()
                ->where('user_id', $user->id)
                ->first()?->pivot?->role;
        }

        return $this->roleCache[$key];
    }



    // Workspace-level
    /**
     * Determine whether a user can view a workspace.
     */
    public function canViewWorkspace(User $user, Workspace $workspace): bool
    {
        return ! is_null($this->getWorkspaceRole($user, $workspace));
    }

    /**
     * Determine whether a user is a super admin (application-level).
     * Super admins can create workspaces and access all workspaces.
     */
    public function isSuperAdmin(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether a user can create a new workspace.
     * Only super admins are allowed; regular users are restricted to
     * the workspaces they are invited to.
     */
    public function canCreateWorkspace(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether a user can manage workspace settings and members.
     */
    public function canManageWorkspace(User $user, Workspace $workspace): bool
    {
        // Super admins can manage any workspace.
        if ($user->isSuperAdmin()) {
            return true;
        }

        return in_array($this->getWorkspaceRole($user, $workspace), [
            self::WORKSPACE_OWNER,
            self::WORKSPACE_ADMIN,
        ], true);
    }

    /**
     * Determine whether a user can delete a workspace (admin only).
     */
    public function canDeleteWorkspace(User $user, Workspace $workspace): bool
    {
        return $this->getWorkspaceRole($user, $workspace) === self::WORKSPACE_OWNER;
    }

    /**
     * Determine whether a user can view workspace analytics.
     */
    public function canViewAnalytics(User $user, Workspace $workspace): bool
    {
        return in_array($this->getWorkspaceRole($user, $workspace), [
            self::WORKSPACE_OWNER,
            self::WORKSPACE_ADMIN,
            self::WORKSPACE_MEMBER,
        ], true);
    }

    // Space-level
    /**
     * Determine whether a user can view a space.
     *
     * Workspace admin can always view every space.
     * Otherwise requires explicit space membership.
     */
    public function canViewSpace(User $user, Space $space): bool
    {
        if (! $this->canViewWorkspace($user, $space->workspace)) {
            return false;
        }

        if (in_array($this->getWorkspaceRole($user, $space->workspace), [
            self::WORKSPACE_OWNER,
            self::WORKSPACE_ADMIN,
        ], true)) {
            return true;
        }

        return in_array($this->getSpaceRole($user, $space), [
            self::SPACE_ADMIN,
            self::SPACE_MEMBER,
            self::SPACE_GUEST,
        ], true);
    }

    /**
     * Determine whether a user can manage a space (settings, statuses, folders).
     *
     * Workspace admin can always manage spaces.
     */
    public function canManageSpace(User $user, Space $space): bool
    {
        if (in_array($this->getWorkspaceRole($user, $space->workspace), [
            self::WORKSPACE_OWNER,
            self::WORKSPACE_ADMIN,
        ], true)) {
            return true;
        }

        if (! $this->canViewSpace($user, $space)) {
            return false;
        }

        return in_array($this->getSpaceRole($user, $space), [
            self::SPACE_ADMIN,
        ], true);
    }

    // Project-level: viewing & management
    /**
     * Determine whether a user can view a project.
     *
     */
    public function canViewProject(User $user, Project $list): bool
    {
        $workspaceRole = $this->getWorkspaceRole($user, $list->space->workspace);

        if (is_null($workspaceRole)) {
            return false;
        }

        if (in_array($workspaceRole, [self::WORKSPACE_OWNER, self::WORKSPACE_ADMIN], true)) {
            return true;
        }

        if (! is_null($this->getProjectRole($user, $list))) {
            return true;
        }

        // No project members configured → open by default for workspace members
        return ! $list->members()->exists();
    }

    /**
     * Determine whether a user can manage a project (settings, etc.).
     *
     * Workspace admin can always manage projects.
     */
    public function canManageProject(User $user, Project $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [
            self::WORKSPACE_OWNER,
            self::WORKSPACE_ADMIN,
        ], true)) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [self::PROJECT_OWNER, self::PROJECT_MANAGER], true);
    }

    /**
     * Determine whether a user can delete a project.
     *
     * Workspace admin can always delete.
     */
    public function canDeleteProject(User $user, Project $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [
            self::WORKSPACE_OWNER,
            self::WORKSPACE_ADMIN,
        ], true)) {
            return true;
        }

        return $this->getProjectRole($user, $list) === self::PROJECT_OWNER;
    }

    /**
     * Determine whether a user can manage project members.
     *
     * Workspace admin can always manage project membership
     * (to assign people to projects).
     */
    public function canManageProjectMembers(User $user, Project $list): bool
    {
        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [
            self::WORKSPACE_OWNER,
            self::WORKSPACE_ADMIN,
        ], true)) {
            return true;
        }

        return $this->getProjectRole($user, $list) === self::PROJECT_OWNER;
    }

    // Project-level: task operations
    //
    // These methods check PRODUCT-LEVEL roles.
    // Only the workspace OWNER gets automatic bypass.
    // Workspace admins must have an explicit project role.
    /**
     * Determine whether a user can edit tasks in a project.
     */
    public function canEditTasks(User $user, Project $list): bool
    {
        return $this->canOperateTasks($user, $list);
    }

    /**
     * Determine whether a user can perform task operations
     * (create/edit subtasks, change status & priority).
     *
     * Workspace owner → always allowed.
     * Others → must have project_owner, project_manager, or development_team role.
     */
    public function canOperateTasks(User $user, Project $list): bool
    {
        if ($this->getWorkspaceRole($user, $list->space->workspace) === self::WORKSPACE_OWNER) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [
            self::PROJECT_OWNER,
            self::PROJECT_MANAGER,
            self::PROJECT_DEVELOPER,
        ], true);
    }

    /**
     * Determine whether a user can manage task structure
     * (create/delete tasks, manage sprints).
     */
    public function canManageTaskStructure(User $user, Project $list): bool
    {
        if ($this->getWorkspaceRole($user, $list->space->workspace) === self::WORKSPACE_OWNER) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [self::PROJECT_OWNER, self::PROJECT_MANAGER], true);
    }

    /**
     * Determine whether a user can manage labels on tasks.
     */
    public function canManageLabels(User $user, Project $list): bool
    {
        return $this->canManageTaskStructure($user, $list);
    }

    /**
     * Determine whether a user can manage dependencies.
     */
    public function canManageDependencies(User $user, Project $list): bool
    {
        return $this->canManageTaskStructure($user, $list);
    }

    /**
     * Determine whether a user can assign tasks to users.
     *
     */
    public function canAssignTasks(User $user, Project $list): bool
    {
        if ($this->getWorkspaceRole($user, $list->space->workspace) === self::WORKSPACE_OWNER) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [self::PROJECT_OWNER, self::PROJECT_MANAGER], true);
    }

    /**
     * Determine whether a user can track time.
     *
     * Workspace owner → always allowed.
     * Others → must have project_owner, project_manager, or development_team role.
     */
    public function canTrackTime(User $user, Project $list): bool
    {
        if ($this->getWorkspaceRole($user, $list->space->workspace) === self::WORKSPACE_OWNER) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [
            self::PROJECT_OWNER,
            self::PROJECT_MANAGER,
            self::PROJECT_DEVELOPER,
        ], true);
    }

    // Comment & time-entry ownership
    /**
     * Determine whether a user can comment on a project.
     *
     * Anyone who can view the project can comment.
     */
    public function canComment(User $user, Project $list): bool
    {
        return $this->canViewProject($user, $list);
    }

    /**
     * Determine whether a user can manage (edit/delete) a specific comment.
     *
     * Only the comment author, or a workspace owner can manage it.
     */
    public function canManageComment(User $user, Comment $comment): bool
    {
        if ((int) $comment->user_id === (int) $user->id) {
            return true;
        }

        // Workspace owner/admin can moderate any comment
        $workspace = $comment->task->project->space->workspace;

        return in_array($this->getWorkspaceRole($user, $workspace), [
            self::WORKSPACE_OWNER,
            self::WORKSPACE_ADMIN,
        ], true);
    }

    /**
     * Determine whether a user can resolve/unresolve a comment.
     *
     * Comment author, or project_owner/project_manager on the project.
     */
    public function canResolveComment(User $user, Comment $comment): bool
    {
        if ((int) $comment->user_id === (int) $user->id) {
            return true;
        }

        $list = $comment->task->project;

        if (in_array($this->getWorkspaceRole($user, $list->space->workspace), [
            self::WORKSPACE_OWNER,
            self::WORKSPACE_ADMIN,
        ], true)) {
            return true;
        }

        return in_array($this->getProjectRole($user, $list), [self::PROJECT_OWNER, self::PROJECT_MANAGER], true);
    }

    /**
     * Determine whether a user can manage a specific time entry.
     *
     * Own entry → always allowed.
     * Otherwise requires project_owner/project_manager role (can oversee team time).
     */
    public function canManageTimeEntry(User $user, TimeEntry $entry): bool
    {
        if ((int) $entry->user_id === (int) $user->id) {
            return true;
        }

        return $this->canAssignTasks($user, $entry->subtask->task->project);
    }

    // Activity & analytics
    /**
     * Determine whether a user can view project activity logs.
     */
    public function canViewActivity(User $user, Project $list): bool
    {
        return $this->canViewProject($user, $list);
    }
}
