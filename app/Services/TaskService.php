<?php

namespace App\Services;

use App\Enums\PriorityLevel;
use App\Models\Activity;
use App\Models\Label;
use App\Models\Project;
use App\Models\Space;
use App\Models\Status;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskService
{
    /**
     * Get tasks for a list with filtering and pagination
     */
    public function getTasksForList(Project $list,
        array $filters = [],
        ?string $sortBy = 'position',
        string $sortDirection = 'asc',
        ?int $perPage = null): Collection|LengthAwarePaginator
    {
        $query = $list->tasks()
            ->with([
                'status',
                'assignees',
                'labels',
                'subtasks' => fn ($q) => $q->with(['status', 'assignees']),
                'creator',
            ]);

        // Apply filters
        $query = $this->applyFilters($query, $filters);

        // Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        if ($perPage) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Get task with all relations
     */
    public function getTaskWithRelations(Task $task): Task
    {
        return $task->load([
            'project.space.workspace',
            'status',
            'assignees',
            'labels',
            'subtasks' => fn ($q) => $q->with(['status', 'assignees', 'dependencies', 'dependents', 'timeEntries.user'])->orderBy('position'),
            'dependencies',
            'dependents',
            'comments' => fn ($q) => $q->whereNull('parent_id')->with(['user', 'replies.user'])->latest(),
            'activities' => fn ($q) => $q->with('user')->latest()->limit(50),
            'creator',
        ]);
    }

    /**
     * Create a new task
     */
    public function create(array $data, Project $list, User $user): Task
    {
        return DB::transaction(function () use ($data, $list, $user) {
            $task = Task::create([
                'project_id' => $list->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status_id' => $data['status_id'] ?? null,
                'priority_level' => $data['priority_level'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'time_estimate' => $data['time_estimate'] ?? null,
                'created_by' => $user->id,
            ]);

            // Sync assignees if provided
            if (! empty($data['assignee_ids'])) {
                $pivotData = [];
                foreach ($data['assignee_ids'] as $assigneeId) {
                    $pivotData[$assigneeId] = ['assigned_by' => $user->id];
                }
                $task->assignees()->sync($pivotData);
            }

            // Sync labels if provided
            if (! empty($data['label_ids'])) {
                $task->labels()->sync($data['label_ids']);
            }

            Activity::log($list->space->workspace, $user, $task, 'created', [
                'name' => $task->name,
            ]);

            return $task->fresh(['status', 'assignees', 'labels']);
        });
    }

    /**
     * Update a task
     */
    public function update(Task $task, array $data, User $user): Task
    {
        return DB::transaction(function () use ($task, $data, $user) {
            $changes = [];
            // Capture before update – needed for readable activity descriptions
            $oldStatusId = $task->status_id;
            $oldStatusName = $task->status?->name;
            $oldPriorityEnum = $task->priority_level; // PriorityLevel enum|null

            $oldValues = $task->only(['name', 'description']);

            $task->update([
                'name' => $data['name'] ?? $task->name,
                'description' => $data['description'] ?? $task->description,
                'status_id' => $data['status_id'] ?? $task->status_id,
                'priority_level' => $data['priority_level'] ?? $task->priority_level,
                'start_date' => array_key_exists('start_date', $data) ? ($data['start_date'] ?: null) : $task->start_date,
                'due_date' => array_key_exists('due_date', $data) ? ($data['due_date'] ?: null) : $task->due_date,
                'time_estimate' => array_key_exists('time_estimate', $data) ? ($data['time_estimate'] ?: null) : $task->time_estimate,
            ]);

            foreach ($oldValues as $key => $oldValue) {
                $newValue = $data[$key] ?? $task->$key;
                if ($newValue != $oldValue) {
                    $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
                }
            }

            // Update assignees if provided
            if (isset($data['assignee_ids'])) {
                $oldAssigneeIds = $task->assignees->pluck('id')->toArray();
                $newAssigneeIds = $data['assignee_ids'];
                $task->assignees()->sync($newAssigneeIds);

                $addedIds = array_diff($newAssigneeIds, $oldAssigneeIds);
                $removedIds = array_diff($oldAssigneeIds, $newAssigneeIds);

                // Batch load all changed assignees to avoid N+1
                $changedIds = array_merge($addedIds, $removedIds);
                $assignees = ! empty($changedIds)
                    ? User::whereIn('id', $changedIds)->get()->keyBy('id')
                    : collect();

                foreach ($addedIds as $id) {
                    $assignee = $assignees->get($id);
                    if ($assignee) {
                        Activity::log($task->project->space->workspace, $user, $task, 'assigned', [
                            'name' => $task->name,
                            'assignee_name' => $assignee->name,
                            'assignee_id' => $assignee->id,
                        ]);
                    }
                }

                foreach ($removedIds as $id) {
                    $assignee = $assignees->get($id);
                    if ($assignee) {
                        Activity::log($task->project->space->workspace, $user, $task, 'unassigned', [
                            'name' => $task->name,
                            'assignee_name' => $assignee->name,
                            'assignee_id' => $assignee->id,
                        ]);
                    }
                }
                // Assignee changes are logged separately above – don't add to generic $changes
            }

            // Update labels if provided
            if (isset($data['label_ids'])) {
                $task->labels()->sync($data['label_ids']);
            }

            if (! empty($changes)) {
                Activity::log($task->project->space->workspace, $user, $task, 'updated', [
                    'name' => $task->name,
                ], $changes);
            }

            // Log status change with readable names
            if (isset($data['status_id']) && (int) $data['status_id'] !== (int) $oldStatusId) {
                $newStatus = Status::find($data['status_id']);

                // Record who closed the task (completed_at itself is set by the model hook).
                if ($newStatus && $newStatus->is_closed && $task->completed_at && ! $task->completed_by) {
                    $task->forceFill(['completed_by' => $user->id])->saveQuietly();
                }

                Activity::log($task->project->space->workspace, $user, $task, 'status_changed', [
                    'name' => $task->name,
                ], [
                    'status' => ['old' => $oldStatusName, 'new' => $newStatus?->name],
                ]);
            }

            // Log priority change with readable labels
            if (array_key_exists('priority_level', $data) && $data['priority_level'] !== $oldPriorityEnum?->value) {
                $oldLabel = $oldPriorityEnum?->label();
                $newLabel = $data['priority_level'] !== null ? PriorityLevel::from((int) $data['priority_level'])->label() : null;
                Activity::log($task->project->space->workspace, $user, $task, 'priority_changed', [
                    'name' => $task->name,
                ], [
                    'priority' => ['old' => $oldLabel, 'new' => $newLabel],
                ]);
            }

            return $task->fresh();
        });
    }

    /**
     * Delete a task
     */
    public function delete(Task $task, User $user): void
    {
        DB::transaction(function () use ($task, $user) {
            Activity::log($task->project->space->workspace, $user, $task, 'deleted', [
                'name' => $task->name,
            ]);

            $task->delete();
        });
    }

    /**
     * Change task status
     */
    public function changeStatus(Task $task, Status $status, User $user): Task
    {
        $oldStatus = $task->status;

        $task->status_id = $status->id;
        // completed_at is handled by the model's saving hook; capture who closed it.
        if ($status->is_closed) {
            $task->completed_by = $user->id;
        }
        $task->save();

        Activity::log($task->project->space->workspace, $user, $task, 'status_changed', [
            'name' => $task->name,
        ], [
            'status' => [
                'old' => $oldStatus?->name,
                'new' => $status->name,
            ],
        ]);

        return $task->fresh();
    }

    /**
     * Change task priority
     */
    public function changePriority(Task $task, ?int $priorityLevel, User $user): Task
    {
        $oldPriority = $task->priority_level;
        $task->update(['priority_level' => $priorityLevel]);

        Activity::log($task->project->space->workspace, $user, $task, 'priority_changed', [
            'name' => $task->name,
        ], [
            'priority' => [
                'old' => $oldPriority?->label(),
                'new' => $priorityLevel ? PriorityLevel::from($priorityLevel)->label() : null,
            ],
        ]);

        return $task->fresh();
    }

    /**
     * Assign user to task
     */
    public function assign(Task $task, User $assignee, User $assignedBy): Task
    {
        $task->assign($assignee, $assignedBy);

        Activity::log($task->project->space->workspace, $assignedBy, $task, 'assigned', [
            'name' => $task->name,
            'assignee_name' => $assignee->name,
            'assignee_id' => $assignee->id,
        ]);

        return $task->fresh();
    }

    /**
     * Unassign user from task
     */
    public function unassign(Task $task, User $assignee, User $unassignedBy): Task
    {
        $task->unassign($assignee);

        Activity::log($task->project->space->workspace, $unassignedBy, $task, 'unassigned', [
            'name' => $task->name,
            'assignee_name' => $assignee->name,
        ]);

        return $task->fresh();
    }

    /**
     * Move task to different list
     */
    public function move(Task $task, Project $newList, User $user, ?int $position = null): Task
    {
        $oldList = $task->project;
        $task->move($newList, $position);

        Activity::log($newList->space->workspace, $user, $task, 'moved', [
            'name' => $task->name,
        ], [
            'list' => [
                'old' => $oldList->name,
                'new' => $newList->name,
            ],
        ]);

        return $task->fresh();
    }

    /**
     * Reorder tasks within a list
     */
    public function reorder(Project $list, array $order): void
    {
        DB::transaction(function () use ($list, $order) {
            foreach ($order as $position => $taskId) {
                Task::where('id', $taskId)
                    ->where('project_id', $list->id)
                    ->update(['position' => $position]);
            }
        });
    }

    /**
     * Add label to task
     */
    public function addLabel(Task $task, Label $label, User $user): Task
    {
        $task->addLabel($label);

        Activity::log($task->project->space->workspace, $user, $task, 'label_added', [
            'name' => $task->name,
            'label_name' => $label->name,
        ]);

        return $task->fresh();
    }

    /**
     * Remove label from task
     */
    public function removeLabel(Task $task, Label $label, User $user): Task
    {
        $task->removeLabel($label);

        Activity::log($task->project->space->workspace, $user, $task, 'label_removed', [
            'name' => $task->name,
            'label_name' => $label->name,
        ]);

        return $task->fresh();
    }

    /**
     * Duplicate a task
     */
    public function duplicate(Task $task, User $user): Task
    {
        return DB::transaction(function () use ($task, $user) {
            $newTask = $task->replicate(['task_id']);
            $newTask->name = $task->name.' (Copy)';
            // Reset operational fields — assignees and dates are not part of the template
            $newTask->start_date = null;
            $newTask->due_date = null;
            $newTask->position = Task::where('project_id', $task->project_id)
                ->max('position') + 1;
            $newTask->save();

            // Copy labels (structural), but NOT assignees (operational)
            $newTask->labels()->sync($task->labels->pluck('id'));

            foreach ($task->subtasks()->with(['labels'])->get() as $subtask) {
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

            Activity::log($task->project->space->workspace, $user, $newTask, 'duplicated', [
                'name' => $newTask->name,
                'original_name' => $task->name,
            ]);

            return $newTask;
        });
    }

    /**
     * Apply filters to task query
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters($query, array $filters)
    {
        if (! empty($filters['status_ids'])) {
            $query->whereIn('status_id', $filters['status_ids']);
        }

        if (! empty($filters['priority_levels'])) {
            $query->whereIn('priority_level', $filters['priority_levels']);
        }

        if (! empty($filters['assignee_ids'])) {
            $query->whereHas('assignees', fn ($q) => $q->whereIn('user_id', $filters['assignee_ids']));
        }

        if (! empty($filters['label_ids'])) {
            $query->whereHas('labels', fn ($q) => $q->whereIn('label_id', $filters['label_ids']));
        }

        // Tasks don't have completed_at - only subtasks do
        // Filter by tasks with all subtasks completed or incomplete
        if (! empty($filters['is_completed'])) {
            $query->whereDoesntHave('subtasks', fn ($q) => $q->whereNull('completed_at'));
        } elseif (isset($filters['is_completed']) && $filters['is_completed'] === false) {
            $query->whereHas('subtasks', fn ($q) => $q->whereNull('completed_at'));
        }

        // Tasks don't have due dates - only subtasks do
        if (! empty($filters['is_overdue'])) {
            $query->whereHas(
                'subtasks',
                fn ($q) => $q->whereNull('completed_at')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now())
            );
        }

        if (! empty($filters['due_date_from'])) {
            $query->whereHas(
                'subtasks',
                fn ($q) => $q->where('due_date', '>=', $filters['due_date_from'])
            );
        }

        if (! empty($filters['due_date_to'])) {
            $query->whereHas(
                'subtasks',
                fn ($q) => $q->where('due_date', '<=', $filters['due_date_to'])
            );
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('task_id', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Get user's assigned tasks across all workspaces
     */
    public function getMyTasks(User $user, array $filters = []): Collection
    {
        $query = Task::query()
            ->whereHas('assignees', fn ($assignees) => $assignees->where('users.id', $user->id))
            ->with(['project.space.workspace', 'status', 'labels', 'assignees', 'subtasks.assignees'])
            ->orderBy('position');

        return $this->applyFilters($query, $filters)->distinct()->get();
    }

    /**
     * Get subtasks assigned to a user (dashboard "Assigned to me").
     *
     * Returns subtasks where the user is explicitly in the subtask_assignees pivot.
     * Eager-loads the parent task + list/space/workspace for breadcrumb display.
     */
    public function getMySubtasks(User $user, array $filters = []): Collection
    {
        $query = Subtask::query()
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $user->id))
            ->whereNull('completed_at')
            ->with([
                'task.project.space.workspace',
                'status',
                'labels',
                'assignees',
            ])
            ->orderByRaw('CASE WHEN due_date IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('due_date')
            ->orderBy('priority_level');

        if (! empty($filters['is_overdue'])) {
            $query->whereNotNull('due_date')
                ->where('due_date', '<', now());
        }

        return $query->get();
    }

    /**
     * Global search across tasks, projects, and spaces.
     *
     * @param  array<string, mixed>  $params  Validated search parameters.
     * @return array{tasks: Collection, projects: Collection, spaces: Collection, meta: array}
     */
    public function globalSearch(User $user, array $params): array
    {
        $query = $params['q'] ?? '';
        $type = $params['type'] ?? 'all';
        $limit = $params['limit'] ?? 20;
        $workspaceId = $params['workspace_id'] ?? null;

        if (strlen($query) < 2) {
            return ['tasks' => collect(), 'projects' => collect(), 'spaces' => collect(), 'meta' => [
                'query' => $query, 'type' => $type, 'workspace_id' => $workspaceId,
                'count' => ['tasks' => 0, 'projects' => 0, 'spaces' => 0],
            ]];
        }

        $query = str_replace(['%', '_'], ['\%', '\_'], $query);

        $tasksQuery = Task::whereHas('project.space.workspace', function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('members', fn ($q2) => $q2->where('users.id', $user->id));
        })
            ->when($workspaceId, fn ($q) => $q->whereHas('project.space', fn ($q2) => $q2->where('workspace_id', $workspaceId)))
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->when(! empty($params['status_id']), fn ($q) => $q->where('status_id', $params['status_id']))
            ->when(! empty($params['assignee_id']), fn ($q) => $q->whereHas('assignees', fn ($q2) => $q2->where('users.id', $params['assignee_id'])))
            ->with(['project.space', 'status', 'assignees'])
            ->limit($limit);

        $tasks = $type === 'all' || $type === 'tasks' ? $tasksQuery->get() : collect();

        $projectsQuery = Project::whereHas('space.workspace', function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('members', fn ($q2) => $q2->where('users.id', $user->id));
        })
            ->when($workspaceId, fn ($q) => $q->whereHas('space', fn ($q2) => $q2->where('workspace_id', $workspaceId)))
            ->where('name', 'like', "%{$query}%")
            ->with('space')
            ->limit(min($limit, 15));

        $projects = $type === 'all' || $type === 'projects' ? $projectsQuery->get() : collect();

        $spacesQuery = Space::whereHas('workspace', function ($q) use ($user) {
            $q->where('created_by', $user->id)
                ->orWhereHas('members', fn ($q2) => $q2->where('users.id', $user->id));
        })
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->where('name', 'like', "%{$query}%")
            ->with('workspace')
            ->limit(min($limit, 15));

        $spaces = $type === 'all' || $type === 'spaces' ? $spacesQuery->get() : collect();

        return [
            'tasks' => $tasks,
            'projects' => $projects,
            'spaces' => $spaces,
            'meta' => [
                'query' => $query,
                'type' => $type,
                'workspace_id' => $workspaceId,
                'count' => [
                    'tasks' => $tasks->count(),
                    'projects' => $projects->count(),
                    'spaces' => $spaces->count(),
                ],
            ],
        ];
    }
}
