<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Folder;
use App\Models\Project;
use App\Models\Space;
use App\Models\Status;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectService
{
    /**
     * Get lists for a space, optionally scoped to a specific folder.
     */
    public function getListsForSpace(Space $space, ?Folder $folder = null): Collection
    {
        $query = $space->projects()
            ->with(['tasks' => fn ($q) => $q->with(['status', 'assignees'])])
            ->withCount('tasks');

        if ($folder) {
            $query->where('folder_id', $folder->id);
        }

        return $query->orderBy('position')->get();
    }

    /**
     * Get board payload grouped by status for tasks or subtasks.
     *
     * When taskId is provided, returns subtasks for the parent task grouped by status.
     * Otherwise returns task-level board items grouped by status.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getWithTasksByStatus(Project $project, $taskId = null): array
    {
        // Load space with statuses first
        $project->load('space.statuses');

        $parentTask = null;

        // If viewing subtasks, load from parent task
        if ($taskId) {
            $parentTask = Task::where('project_id', $project->id)->with([
                'subtasks.status',
                'subtasks.assignees',
                'subtasks.labels',
                'subtasks.sprint',
                'subtasks.dependencies',
                'subtasks.dependents',
                'subtasks.timeEntries.user',
                'subtasks.comments.user', // Include comments
                'subtasks.activities' => fn ($q) => $q->with('user')->latest()->limit(50),
                'subtasks.checklistItems', // For checklist UI & auto-progress
                'subtasks.children.status', // Direct children (depth+1 subtasks)
                'subtasks.children.assignees',
            ])->find($taskId);
        }

        if ($parentTask) {
            $items = $parentTask->subtasks->whereNull('parent_id');
        } else {
            // Load tasks for the list
            $items = $project->tasks()
                ->with([
                    'status',
                    'assignees',
                    'labels',
                    'subtasks.assignees', // For subtask count & assignee aggregation
                    'comments.user', // Include comments
                    'activities' => fn ($q) => $q->with('user')->latest()->limit(50),
                ])
                ->orderBy('position')
                ->get();

            // Task-level assignees are derived from subtask assignees.
            $items->each(function ($task) {
                $aggregatedAssignees = $task->subtasks
                    ->flatMap(fn ($subtask) => $subtask->assignees ?? collect())
                    ->unique('id')
                    ->values();

                $task->setRelation('assignees', $aggregatedAssignees);
            });
        }

        $statuses = $project->space->statuses;

        $grouped = [];
        foreach ($statuses as $status) {
            $statusItems = $items->where('status_id', $status->id);

            // Sort by due_date ascending (nearest first), nulls at the end
            $statusItems = $statusItems->sort(function ($a, $b) {
                $aDate = $a->due_date ?? null;
                $bDate = $b->due_date ?? null;

                if ($aDate === null && $bDate === null) {
                    return $a->position <=> $b->position;
                }
                if ($aDate === null) {
                    return 1;
                }
                if ($bDate === null) {
                    return -1;
                }

                return $aDate <=> $bDate;
            });

            $grouped[$status->id] = $statusItems->values()->toArray();
        }

        return $grouped;
    }

    /**
     * Create a new project inside a space.
     *
     * @param  array<string, mixed>  $data  Validated list payload.
     */
    public function create(array $data, Space $space, User $user, ?Folder $folder = null): Project
    {
        return DB::transaction(function () use ($data, $space, $user, $folder) {
            // Backward-compat: older spaces may miss one of the default task statuses.
            $this->ensureDefaultTaskStatuses($space);

            $project = Project::create([
                'space_id' => $space->id,
                'folder_id' => $folder?->id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? null,
                'icon' => $data['icon'] ?? null,
                'created_by' => $user->id,
            ]);

            $project->addMember($user, AccessService::PROJECT_OWNER);

            Activity::log($space->workspace, $user, $project, 'created', [
                'name' => $project->name,
                'space_name' => $space->name,
                'folder_name' => $folder?->name,
            ]);

            return $project;
        });
    }

    /**
     * Ensure canonical task statuses exist for the space.
     */
    private function ensureDefaultTaskStatuses(Space $space): void
    {
        $statusColors = config('business.default_status_colors', [
            'open' => '#6B7280',
            'in_progress' => '#3B82F6',
            'review' => '#F59E0B',
            'done' => '#10B981',
        ]);

        $defaults = [
            [
                'type' => 'open',
                'name' => 'Open',
                'slug' => 'open',
                'color' => $statusColors['backlog'] ?? '#6B7280',
                'position' => 0,
                'is_default' => true,
                'is_closed' => false,
            ],
            [
                'type' => 'in_progress',
                'name' => 'In Progress',
                'slug' => 'in-progress',
                'color' => $statusColors['todo'] ?? '#3B82F6',
                'position' => 1,
                'is_default' => false,
                'is_closed' => false,
            ],
            [
                'type' => 'review',
                'name' => 'Review',
                'slug' => 'review',
                'color' => $statusColors['in_progress'] ?? '#F59E0B',
                'position' => 2,
                'is_default' => false,
                'is_closed' => false,
            ],
            [
                'type' => 'closed',
                'name' => 'Completed',
                'slug' => 'completed',
                'color' => $statusColors['done'] ?? '#10B981',
                'position' => 3,
                'is_default' => false,
                'is_closed' => true,
            ],
        ];

        foreach ($defaults as $default) {
            $status = $space->statuses()
                ->where(fn ($q) => $q->where('type', $default['type'])->orWhere('slug', $default['slug']))
                ->first();

            if ($status) {
                $updates = [];

                if ($status->type !== $default['type']) {
                    $updates['type'] = $default['type'];
                }

                if (! in_array($status->applies_to, ['tasks', 'both'], true)) {
                    $updates['applies_to'] = 'both';
                }

                if ($default['is_closed'] && ! $status->is_closed) {
                    $updates['is_closed'] = true;
                }

                if ($default['is_default']) {
                    $hasDefaultTaskStatus = $space->statuses()->forTasks()->where('is_default', true)->exists();
                    if (! $hasDefaultTaskStatus && ! $status->is_default) {
                        $updates['is_default'] = true;
                    }
                }

                if (! empty($updates)) {
                    $status->update($updates);
                }

                continue;
            }

            // Keep insertion in a sensible order without touching existing custom statuses more than needed.
            $space->statuses()->where('position', '>=', $default['position'])->increment('position');

            Status::create([
                'space_id' => $space->id,
                'name' => $default['name'],
                'slug' => $default['slug'],
                'color' => $default['color'],
                'type' => $default['type'],
                'applies_to' => 'both',
                'position' => $default['position'],
                'is_default' => $default['is_default'],
                'is_closed' => $default['is_closed'],
            ]);
        }
    }

    /**
     * Add a member to a list with a role.
     */
    public function addMember(Project $project, User $user, string $role, User $addedBy): void
    {
        $project->addMember($user, $role);

        Activity::log($project->space->workspace, $addedBy, $project, 'member_added', [
            'name' => $project->name,
            'member_name' => $user->name,
            'role' => $role,
        ]);
    }

    /**
     * Update an existing list member role.
     */
    public function updateMemberRole(Project $project, User $user, string $role, User $updatedBy): void
    {
        $project->members()->updateExistingPivot($user->id, ['role' => $role]);

        Activity::log($project->space->workspace, $updatedBy, $project, 'member_role_updated', [
            'name' => $project->name,
            'member_name' => $user->name,
            'role' => $role,
        ]);
    }

    /**
     * Remove a member from a list.
     */
    public function removeMember(Project $project, User $user, User $removedBy): void
    {
        $project->members()->detach($user->id);

        Activity::log($project->space->workspace, $removedBy, $project, 'member_removed', [
            'name' => $project->name,
            'member_name' => $user->name,
        ]);
    }

    /**
     * Update list metadata.
     *
     * @param  array<string, mixed>  $data  Validated update payload.
     */
    public function update(Project $project, array $data, User $user): Project
    {
        $changes = [];
        $oldValues = $project->only(['name', 'description', 'color']);

        $project->update([
            'name' => $data['name'] ?? $project->name,
            'description' => $data['description'] ?? $project->description,
            'color' => $data['color'] ?? $project->color,
            'icon' => $data['icon'] ?? $project->icon,
        ]);

        foreach ($oldValues as $key => $oldValue) {
            if (isset($data[$key]) && $data[$key] !== $oldValue) {
                $changes[$key] = ['old' => $oldValue, 'new' => $data[$key]];
            }
        }

        if (! empty($changes)) {
            Activity::log($project->space->workspace, $user, $project, 'updated', [
                'name' => $project->name,
            ], $changes);
        }

        return $project->fresh();
    }

    /**
     * Soft-delete a list.
     */
    public function delete(Project $project, User $user): void
    {
        DB::transaction(function () use ($project, $user) {
            Activity::log($project->space->workspace, $user, $project, 'deleted', [
                'name' => $project->name,
            ]);

            $project->delete();
        });
    }

    /**
     * Move a list to another folder (or root when null).
     */
    public function moveToFolder(Project $project, ?Folder $folder, User $user): Project
    {
        $oldFolderName = $project->folder?->name ?? 'No Folder';
        $newFolderName = $folder?->name ?? 'No Folder';

        $project->update([
            'folder_id' => $folder?->id,
            'position' => Project::where('space_id', $project->space_id)
                ->where('folder_id', $folder?->id)
                ->max('position') + 1,
        ]);

        Activity::log($project->space->workspace, $user, $project, 'moved', [
            'name' => $project->name,
        ], [
            'folder' => ['old' => $oldFolderName, 'new' => $newFolderName],
        ]);

        return $project->fresh();
    }

    /**
     * Reorder list positions within a space.
     *
     * @param  array<int, int|string>  $order  Ordered list ids.
     */
    public function reorder(Space $space, array $order): void
    {
        DB::transaction(function () use ($space, $order) {
            foreach ($order as $position => $projectId) {
                Project::where('id', $projectId)
                    ->where('space_id', $space->id)
                    ->update(['position' => $position]);
            }
        });
    }

    /**
     * Duplicate a list and clone all tasks/subtasks and their assignments/labels.
     */
    public function duplicate(Project $project, User $user): Project
    {
        return DB::transaction(function () use ($project, $user) {
            $newProject = $project->replicate();
            $newProject->name = $project->name.' (Copy)';
            $newProject->slug = Str::slug($newProject->name);
            $newProject->position = Project::where('space_id', $project->space_id)
                ->where('folder_id', $project->folder_id)
                ->max('position') + 1;
            $newProject->save();

            // Only copy the creator as project_owner, not all members
            $newProject->addMember($user, AccessService::PROJECT_OWNER);

            foreach ($project->tasks()->with(['labels', 'subtasks.labels'])->get() as $task) {
                $newTask = $task->replicate(['task_id']);
                $newTask->project_id = $newProject->id;
                // Reset operational fields — this is a template copy, not a continuation
                $newTask->start_date = null;
                $newTask->due_date = null;
                $newTask->save();

                // Copy labels (structural), but NOT assignees (operational)
                $newTask->labels()->sync($task->labels->pluck('id'));

                foreach ($task->subtasks as $subtask) {
                    $newSubtask = $subtask->replicate([
                        'subtask_id',
                        'completed_at',
                        'completed_by',
                        'time_spent',
                        'sprint_id',
                    ]);
                    $newSubtask->task_id = $newTask->id;
                    // Reset operational fields
                    $newSubtask->start_date = null;
                    $newSubtask->due_date = null;
                    $newSubtask->baseline_start_date = null;
                    $newSubtask->baseline_due_date = null;
                    $newSubtask->progress = 0;
                    $newSubtask->save();

                    // Copy labels (structural), but NOT assignees (operational)
                    $newSubtask->labels()->sync($subtask->labels->pluck('id'));
                }
            }

            Activity::log($project->space->workspace, $user, $newProject, 'duplicated', [
                'name' => $newProject->name,
                'original_name' => $project->name,
            ]);

            return $newProject;
        });
    }
}
