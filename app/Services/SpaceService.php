<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Space;
use App\Models\Status;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpaceService
{
    /**
     * Get all spaces for a workspace
     */
    public function getSpacesForWorkspace(Workspace $workspace): Collection
    {
        return $workspace->spaces()
            ->with([
                'folders.projects',
                'projectsWithoutFolder',
                'statuses',
            ])
            ->withCount(['allFolders', 'projects'])
            ->orderBy('position')
            ->get();
    }

    /**
     * Get a space with full hierarchy, filtering products by user access.
     */
    public function getWithHierarchy(Space $space, ?User $user = null): Space
    {
        $listFilter = $this->buildListAccessFilter($user, $space);

        return $space->load([
            'workspace',
            'folders' => fn ($q) => $q->with([
                'children',
                'projects' => fn ($lq) => $listFilter($lq)->with('status')->withCount('tasks')->orderBy('position'),
            ])->orderBy('position'),
            'projectsWithoutFolder' => fn ($q) => $listFilter($q)->with('status')->withCount('tasks')->orderBy('position'),
            'statuses' => fn ($q) => $q->orderBy('position'),
            'labels',
        ]);
    }

    /**
     * Get projects (Projects) grouped by status for kanban board, filtered by user access.
     */
    public function getProjectsByStatus(Space $space, ?User $user = null): array
    {
        $query = $space->projects();

        if ($user) {
            $query->accessibleBy($user);
        }

        $lists = $query
            ->with(['status', 'folder'])
            ->withCount('tasks')
            ->orderBy('position')
            ->get();

        $statuses = $space->statuses()->orderBy('position')->get();

        $grouped = [];
        foreach ($statuses as $status) {
            $grouped[$status->id] = $lists
                ->where('status_id', $status->id)
                ->values()
                ->toArray();
        }

        return $grouped;
    }

    /**
     * Build a closure that filters a Project query by user access.
     */
    private function buildListAccessFilter(?User $user, Space $space): \Closure
    {
        return function ($query) use ($user, $space) {
            if (! $user) {
                return $query;
            }

            $wsRole = $space->workspace
                ? $space->workspace->members()->where('user_id', $user->id)->first()?->pivot?->role
                : null;

            if (in_array($wsRole, [AccessService::WORKSPACE_ADMIN, AccessService::WORKSPACE_OWNER], true)) {
                return $query;
            }

            return $query->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id));
        };
    }

    /**
     * Create a new space
     */
    public function create(array $data, Workspace $workspace, User $user): Space
    {
        return DB::transaction(function () use ($data, $workspace, $user) {
            $space = Space::create([
                'workspace_id' => $workspace->id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'color' => $data['color'] ?? '#6366F1',
                'created_by' => $user->id,
            ]);

            Activity::log($workspace, $user, $space, 'created', [
                'name' => $space->name,
            ]);

            return $space;
        });
    }

    /**
     * Update a space
     */
    public function update(Space $space, array $data, User $user): Space
    {
        $changes = [];
        $oldValues = $space->only(['name', 'color']);

        $space->update([
            'name' => $data['name'] ?? $space->name,
            'color' => $data['color'] ?? $space->color,
        ]);

        foreach ($oldValues as $key => $oldValue) {
            if (isset($data[$key]) && $data[$key] !== $oldValue) {
                $changes[$key] = ['old' => $oldValue, 'new' => $data[$key]];
            }
        }

        if (! empty($changes)) {
            Activity::log($space->workspace, $user, $space, 'updated', [
                'name' => $space->name,
            ], $changes);
        }

        return $space->fresh();
    }

    /**
     * Delete a space
     */
    public function delete(Space $space, User $user): void
    {
        DB::transaction(function () use ($space, $user) {
            Activity::log($space->workspace, $user, $space, 'deleted', [
                'name' => $space->name,
            ]);

            $space->delete();
        });
    }

    /**
     * Toggle starred status for a specific user
     */
    public function toggleStar(Space $space, User $user): bool
    {
        $isStarred = $space->starredBy()->where('user_id', $user->id)->exists();

        if ($isStarred) {
            $space->starredBy()->detach($user->id);

            return false;
        } else {
            $space->starredBy()->attach($user->id, ['workspace_id' => $space->workspace_id]);

            return true;
        }
    }

    /**
     * Reorder spaces
     */
    public function reorder(Workspace $workspace, array $order): void
    {
        DB::transaction(function () use ($workspace, $order) {
            foreach ($order as $position => $spaceId) {
                Space::where('id', $spaceId)
                    ->where('workspace_id', $workspace->id)
                    ->update(['position' => $position]);
            }
        });
    }

    /**
     * Add a member to the space with a role.
     */
    public function addMember(Space $space, User $user, string $role, User $addedBy): void
    {
        $space->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);

        Activity::log($space->workspace, $addedBy, $space, 'member_added', [
            'name' => $space->name,
            'member_name' => $user->name,
            'role' => $role,
        ]);
    }

    /**
     * Update role for an existing space member.
     */
    public function updateMemberRole(Space $space, User $user, string $role, User $updatedBy): void
    {
        $space->members()->updateExistingPivot($user->id, ['role' => $role]);

        Activity::log($space->workspace, $updatedBy, $space, 'member_role_updated', [
            'name' => $space->name,
            'member_name' => $user->name,
            'role' => $role,
        ]);
    }

    /**
     * Remove a member from the space.
     */
    public function removeMember(Space $space, User $user, User $removedBy): void
    {
        $space->members()->detach($user->id);

        Activity::log($space->workspace, $removedBy, $space, 'member_removed', [
            'name' => $space->name,
            'member_name' => $user->name,
        ]);
    }

    /**
     * Get space statistics
     */
    public function getStatistics(Space $space): array
    {
        $subtaskCounts = DB::table('subtasks')
            ->join('tasks', 'subtasks.task_id', '=', 'tasks.id')
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->join('statuses', 'subtasks.status_id', '=', 'statuses.id')
            ->where('projects.space_id', $space->id)
            ->whereNull('subtasks.deleted_at')
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN subtasks.completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN subtasks.completed_at IS NULL AND statuses.type IN ("in_progress", "review") THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN subtasks.completed_at IS NULL AND subtasks.due_date IS NOT NULL AND subtasks.due_date < CURRENT_TIMESTAMP THEN 1 ELSE 0 END) as overdue
            ')
            ->first();

        return [
            'total_tasks' => $subtaskCounts->total ?? 0,
            'completed_tasks' => $subtaskCounts->completed ?? 0,
            'in_progress_tasks' => $subtaskCounts->in_progress ?? 0,
            'overdue_tasks' => $subtaskCounts->overdue ?? 0,
            'folders_count' => $space->allFolders()->count(),
            'projects_count' => $space->projects()->count(),
            'progress' => $subtaskCounts->total > 0
                ? round(($subtaskCounts->completed / $subtaskCounts->total) * 100, 1)
                : 0,
        ];
    }

    /**
     * Add custom status to space
     */
    public function addStatus(Space $space, array $data): Status
    {
        $maxPosition = $space->statuses()->max('position') ?? -1;

        return $space->statuses()->create([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'color' => $data['color'] ?? '#6B7280',
            'type' => 'custom',
            'position' => $maxPosition + 1,
            'is_closed' => $data['is_closed'] ?? false,
            'applies_to' => $data['applies_to'] ?? 'both',
        ]);
    }

    /**
     * Update status
     */
    public function updateStatus(Status $status, array $data): Status
    {
        $status->update([
            'name' => $data['name'] ?? $status->name,
            'slug' => isset($data['name']) ? Str::slug($data['name']) : $status->slug,
            'color' => $data['color'] ?? $status->color,
            'is_closed' => $data['is_closed'] ?? $status->is_closed,
            'applies_to' => $data['applies_to'] ?? $status->applies_to,
        ]);

        return $status->fresh();
    }

    /**
     * Delete status
     */
    public function deleteStatus(Status $status, ?int $moveToStatusId = null): void
    {
        DB::transaction(function () use ($status, $moveToStatusId) {
            if ($moveToStatusId) {
                $status->tasks()->update(['status_id' => $moveToStatusId]);
                $status->subtasks()->update(['status_id' => $moveToStatusId]);
            }

            $status->delete();
        });
    }

    /**
     * Reorder statuses
     */
    public function reorderStatuses(Space $space, array $order): void
    {
        DB::transaction(function () use ($space, $order) {
            foreach ($order as $position => $statusId) {
                Status::where('id', $statusId)
                    ->where('space_id', $space->id)
                    ->update(['position' => $position]);
            }
        });
    }
}
