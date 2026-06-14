<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkspaceService
{
    /**
     * Get all workspaces the user is a member of, with spaces eager-loaded.
     */
    public function getWorkspacesForUser(User $user): Collection
    {
        return $user->workspaces()
            ->with(['spaces' => fn ($q) => $q->orderBy('position')])
            ->withCount('members')
            ->orderBy('name')
            ->get();
    }

    /**
     * Create a new workspace and assign the creator as owner.
     *
     * @param  array{name: string, color?: string, is_personal?: bool}  $data
     */
    public function create(array $data, User $owner): Workspace
    {
        return DB::transaction(function () use ($data, $owner) {
            $workspace = Workspace::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'color' => $data['color'] ?? '#7C3AED',
                'is_personal' => $data['is_personal'] ?? false,
            ]);

            // Creator is always the first admin
            $workspace->addMember($owner, AccessService::WORKSPACE_OWNER);

            Activity::log($workspace, $owner, $workspace, 'created', [
                'name' => $workspace->name,
            ]);

            return $workspace;
        });
    }

    /**
     * Update workspace name and/or color. Logs activity if name changed.
     *
     * @param  array{name?: string, color?: string}  $data
     */
    public function update(Workspace $workspace, array $data, User $user): Workspace
    {
        $oldName = $workspace->name;

        $workspace->update([
            'name' => $data['name'] ?? $workspace->name,
            'color' => $data['color'] ?? $workspace->color,
        ]);

        if ($oldName !== $workspace->name) {
            Activity::log($workspace, $user, $workspace, 'updated', [
                'name' => $workspace->name,
            ], [
                'name' => ['old' => $oldName, 'new' => $workspace->name],
            ]);
        }

        return $workspace->fresh();
    }

    /**
     * Permanently delete a workspace and all its nested data.
     */
    public function delete(Workspace $workspace, User $user): void
    {
        DB::transaction(function () use ($workspace, $user) {
            // Log before deletion
            Activity::log($workspace, $user, $workspace, 'deleted', [
                'name' => $workspace->name,
            ]);

            $workspace->delete();
        });
    }

    /**
     * Add a user as a member of the workspace with the given role.
     */
    public function addMember(Workspace $workspace, User $user, string $role = AccessService::WORKSPACE_MEMBER, ?User $addedBy = null): void
    {
        $workspace->addMember($user, $role);

        if ($addedBy) {
            Activity::log($workspace, $addedBy, $workspace, 'member_added', [
                'name' => $workspace->name,
                'member_name' => $user->name,
                'member_id' => $user->id,
                'role' => $role,
            ]);
        }
    }

    /**
     * Remove a user from the workspace membership.
     */
    public function removeMember(Workspace $workspace, User $user, ?User $removedBy = null): void
    {
        $workspace->removeMember($user);

        if ($removedBy) {
            Activity::log($workspace, $removedBy, $workspace, 'member_removed', [
                'name' => $workspace->name,
                'member_name' => $user->name,
                'member_id' => $user->id,
            ]);
        }
    }

    /**
     * Change a workspace member's role.
     */
    public function updateMemberRole(Workspace $workspace, User $user, string $role, ?User $updatedBy = null): void
    {
        $workspace->members()->updateExistingPivot($user->id, ['role' => $role]);

        if ($updatedBy) {
            Activity::log($workspace, $updatedBy, $workspace, 'member_role_updated', [
                'name' => $workspace->name,
                'member_name' => $user->name,
                'role' => $role,
            ]);
        }
    }

    /**
     * Get aggregate statistics for a workspace (spaces, tasks, completion, overdue).
     *
     * @return array{spaces_count: int, projects_count: int, tasks_count: int, completed_subtasks_count: int, overdue_subtasks_count: int, members_count: int}
     */
    public function getStatistics(Workspace $workspace): array
    {
        $spaces = $workspace->spaces()->withCount([
            'projects',
        ])->get();

        $totalLists = $spaces->sum('projects_count');

        // Get task counts through relationships
        $taskCounts = DB::table('tasks')
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->join('spaces', 'projects.space_id', '=', 'spaces.id')
            ->where('spaces.workspace_id', $workspace->id)
            ->whereNull('tasks.deleted_at')
            ->selectRaw('COUNT(*) as total')
            ->first();

        // Get subtask counts for completion and overdue metrics
        $subtaskCounts = DB::table('subtasks')
            ->join('tasks', 'subtasks.task_id', '=', 'tasks.id')
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->join('spaces', 'projects.space_id', '=', 'spaces.id')
            ->where('spaces.workspace_id', $workspace->id)
            ->whereNull('subtasks.deleted_at')
            ->selectRaw('
                SUM(CASE WHEN subtasks.completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN subtasks.completed_at IS NULL AND subtasks.due_date < CURRENT_TIMESTAMP THEN 1 ELSE 0 END) as overdue
            ')
            ->first();

        return [
            'spaces_count' => $spaces->count(),
            'projects_count' => $totalLists,
            'tasks_count' => $taskCounts->total ?? 0,
            'completed_subtasks_count' => $subtaskCounts->completed ?? 0,
            'overdue_subtasks_count' => $subtaskCounts->overdue ?? 0,
            'members_count' => $workspace->members()->count(),
        ];
    }
}
