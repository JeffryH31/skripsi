<?php

namespace App\Services;

use App\Enums\PriorityLevel;
use App\Models\Activity;
use App\Models\Label;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubtaskService
{
    /**
     * Create a new subtask under the given task.
     *
     * Handles nesting depth validation, assignee/label sync, and activity logging
     * within a single database transaction.
     *
     * @param  array<string, mixed>  $data  Validated subtask attributes (name, description, status_id, etc.)
     *
     * @throws \Illuminate\Validation\ValidationException If nesting depth exceeds MAX_DEPTH.
     */
    public function create(array $data, Task $task, User $user): Subtask
    {
        return DB::transaction(function () use ($data, $task, $user) {
            // Validate nesting depth before creating
            if (! empty($data['parent_id'])) {
                $parent = Subtask::where('task_id', $task->id)->findOrFail((int) $data['parent_id']);

                if ($parent->depth >= Subtask::MAX_DEPTH) {
                    throw ValidationException::withMessages([
                        'parent_id' => ['Maximum nesting depth ('.(Subtask::MAX_DEPTH + 1).' levels) reached. Cannot create deeper subtasks.'],
                    ]);
                }
            }

            $subtask = Subtask::create([
                'task_id' => $task->id,
                'parent_id' => $data['parent_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status_id' => $data['status_id'] ?? null,
                'priority_level' => $data['priority_level'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'baseline_start_date' => $data['start_date'] ?? null,
                'baseline_due_date' => $data['due_date'] ?? null,
                'time_estimate' => $data['time_estimate'] ?? null,
                'created_by' => $user->id,
            ]);

            // Sync assignees if provided
            if (! empty($data['assignee_ids'])) {
                $pivotData = [];
                foreach ($data['assignee_ids'] as $assigneeId) {
                    $pivotData[$assigneeId] = ['assigned_by' => $user->id];
                }
                $subtask->assignees()->sync($pivotData);
            }

            // Sync labels if provided
            if (! empty($data['label_ids'])) {
                $subtask->labels()->sync($data['label_ids']);
            }

            Activity::log(
                $task->project->space->workspace,
                $user,
                $subtask,
                'created',
                [
                    'name' => $subtask->name,
                    'task_name' => $task->name,
                ]
            );

            return $subtask->fresh(['assignees', 'labels', 'status']);
        });
    }

    /**
     * Update an existing subtask's attributes.
     *
     * Compares old vs new values, syncs assignees/labels if provided,
     * and logs granular activity changes within a transaction.
     *
     * @param  array<string, mixed>  $data  Fields to update (only provided keys are applied).
     */
    public function update(Subtask $subtask, array $data, User $user): Subtask
    {
        return DB::transaction(function () use ($subtask, $data, $user) {
            $updateData = [];
            $fields = [
                'name',
                'description',
                'status_id',
                'priority_level',
                'sprint_id',
                'start_date',
                'due_date',
                'baseline_start_date',
                'baseline_due_date',
                'time_estimate',
                'optimistic_estimate',
                'most_likely_estimate',
                'pessimistic_estimate',
                // 'progress' is now auto-calculated from checklist items
            ];

            // Capture status/priority before update for readable activity descriptions
            $oldStatusId = $subtask->status_id;
            $oldStatusName = $subtask->status?->name;
            $oldPriorityEnum = $subtask->priority_level; // PriorityLevel enum|null

            // Capture old values for change tracking (status/priority logged separately below)
            $oldValues = $subtask->only(array_diff($fields, ['status_id', 'priority_level']));

            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    if ($field === 'sprint_id' && $data[$field]) {
                        $sprint = Sprint::find((int) $data[$field]);
                        if (! $sprint) {
                            throw ValidationException::withMessages([
                                'sprint_id' => ['Selected sprint does not exist.'],
                            ]);
                        }

                        $subtaskListId = (int) $subtask->task->project_id;
                        $sprintListId = (int) ($sprint->project_id ?? 0);

                        if ($sprintListId > 0 && $sprintListId !== $subtaskListId) {
                            throw ValidationException::withMessages([
                                'sprint_id' => ['Selected sprint is not in this project.'],
                            ]);
                        }
                    }

                    $updateData[$field] = $data[$field];
                }
            }

            if (! empty($updateData)) {
                $subtask->update($updateData);
            }

            // Track changes for activity
            $changes = [];
            $dateFields = ['start_date', 'due_date', 'baseline_start_date', 'baseline_due_date'];
            foreach ($oldValues as $key => $oldValue) {
                $newValue = array_key_exists($key, $data) ? $data[$key] : $subtask->$key;

                if (in_array($key, $dateFields, true)) {
                    $oldComparable = $this->normalizeDateForComparison($oldValue);
                    $newComparable = $this->normalizeDateForComparison($newValue);

                    if ($oldComparable === $newComparable) {
                        continue;
                    }

                    $changes[$key] = [
                        'old' => $oldComparable,
                        'new' => $newComparable,
                    ];

                    continue;
                }

                if ($newValue != $oldValue) {
                    $changes[$key] = ['old' => $oldValue, 'new' => $newValue];
                }
            }

            // Update assignees if provided
            if (isset($data['assignee_ids'])) {
                $oldAssigneeIds = $subtask->assignees->pluck('id')->toArray();
                $newAssigneeIds = $data['assignee_ids'];

                $pivotData = [];
                foreach ($newAssigneeIds as $assigneeId) {
                    $pivotData[$assigneeId] = ['assigned_by' => $user->id];
                }
                $subtask->assignees()->sync($pivotData);

                $addedIds = array_diff($newAssigneeIds, $oldAssigneeIds);
                $removedIds = array_diff($oldAssigneeIds, $newAssigneeIds);

                // Batch load all changed assignees to avoid N+1
                $changedIds = array_merge($addedIds, $removedIds);
                $assignees = ! empty($changedIds)
                    ? User::whereIn('id', $changedIds)->get()->keyBy('id')
                    : collect();

                $workspace = $subtask->task->project->space->workspace;
                foreach ($addedIds as $id) {
                    $assignee = $assignees->get($id);
                    if ($assignee) {
                        Activity::log($workspace, $user, $subtask, 'assigned', [
                            'name' => $subtask->name,
                            'assignee_name' => $assignee->name,
                            'assignee_id' => $assignee->id,
                        ]);
                    }
                }

                foreach ($removedIds as $id) {
                    $assignee = $assignees->get($id);
                    if ($assignee) {
                        Activity::log($workspace, $user, $subtask, 'unassigned', [
                            'name' => $subtask->name,
                            'assignee_name' => $assignee->name,
                            'assignee_id' => $assignee->id,
                        ]);
                    }
                }
                // Assignee changes are logged separately above – don't add to generic $changes
            }

            // Update labels if provided
            if (isset($data['label_ids'])) {
                $subtask->labels()->sync($data['label_ids']);
            }

            // Log activity with changes
            if (! empty($changes)) {
                Activity::log(
                    $subtask->task->project->space->workspace,
                    $user,
                    $subtask,
                    'updated',
                    ['name' => $subtask->name],
                    $changes
                );
            }

            $workspace = $subtask->task->project->space->workspace;

            // Log status change with readable names
            if (isset($data['status_id']) && (int) $data['status_id'] !== (int) $oldStatusId) {
                $newStatus = Status::find($data['status_id']);
                Activity::log($workspace, $user, $subtask, 'status_changed', [
                    'name' => $subtask->name,
                ], [
                    'status' => ['old' => $oldStatusName, 'new' => $newStatus?->name],
                ]);
            }

            // Log priority change with readable labels
            if (array_key_exists('priority_level', $data) && $data['priority_level'] !== $oldPriorityEnum?->value) {
                $oldLabel = $oldPriorityEnum?->label();
                $newLabel = $data['priority_level'] !== null ? PriorityLevel::from((int) $data['priority_level'])->label() : null;
                Activity::log($workspace, $user, $subtask, 'priority_changed', [
                    'name' => $subtask->name,
                ], [
                    'priority' => ['old' => $oldLabel, 'new' => $newLabel],
                ]);
            }

            return $subtask->fresh();
        });
    }

    /**
     * Normalize date-like values into a comparable calendar date string.
     */
    private function normalizeDateForComparison(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        try {
            return Carbon::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return is_scalar($value) ? (string) $value : null;
        }
    }

    /**
     * Soft-delete a subtask and log the deletion activity.
     */
    public function delete(Subtask $subtask, User $user): void
    {
        DB::transaction(function () use ($subtask, $user) {
            $subtaskName = $subtask->name;
            $task = $subtask->task;
            $workspace = $task->project->space->workspace;

            $subtask->delete();

            Activity::log(
                $workspace,
                $user,
                $task,
                'deleted_subtask',
                ['name' => $subtaskName]
            );
        });
    }

    /**
     * Reorder subtasks within the same parent by updating their position column.
     *
     * @param  array<int>  $subtaskIds  Ordered list of subtask IDs (index = new position).
     */
    public function reorder(Task $task, array $subtaskIds, ?int $parentId = null): void
    {
        DB::transaction(function () use ($task, $subtaskIds, $parentId) {
            foreach ($subtaskIds as $position => $subtaskId) {
                Subtask::where('id', $subtaskId)
                    ->where('task_id', $task->id)
                    ->where('parent_id', $parentId)
                    ->update(['position' => $position]);
            }
        });
    }

    /**
     * Mark a subtask as completed.
     *
     * Checks for uncompleted dependencies before completing.
     *
     * @throws \Illuminate\Validation\ValidationException If subtask has uncompleted dependencies.
     */
    public function complete(Subtask $subtask, User $user, ?int $targetStatusId = null): Subtask
    {
        if ($subtask->hasUncompletedDependencies()) {
            $names = $subtask->getUncompletedDependencyNames();
            $nameList = implode(', ', $names);
            throw ValidationException::withMessages([
                'dependency' => "Cannot complete this subtask. It depends on uncompleted subtasks: {$nameList}",
            ]);
        }

        $subtask->markAsCompleted($user, $targetStatusId);

        Activity::log(
            $subtask->task->project->space->workspace,
            $user,
            $subtask,
            'completed',
            ['name' => $subtask->name]
        );

        return $subtask->fresh();
    }

    /**
     * Reopen a completed subtask.
     */
    public function reopen(Subtask $subtask, User $user): Subtask
    {
        $subtask->markAsIncomplete();

        Activity::log(
            $subtask->task->project->space->workspace,
            $user,
            $subtask,
            'reopened',
            ['name' => $subtask->name]
        );

        return $subtask->fresh();
    }

    /**
     * Add a label to a subtask.
     */
    public function addLabel(Subtask $subtask, Label $label, User $user): void
    {
        $alreadyAttached = $subtask->labels()->whereKey($label->id)->exists();
        if (! $alreadyAttached) {
            $subtask->labels()->syncWithoutDetaching([$label->id]);

            Activity::log(
                $subtask->task->project->space->workspace,
                $user,
                $subtask,
                'label_added',
                [
                    'name' => $subtask->name,
                    'label_name' => $label->name,
                ]
            );
        }
    }

    /**
     * Remove a label from a subtask.
     */
    public function removeLabel(Subtask $subtask, Label $label, User $user): void
    {
        $detached = $subtask->labels()->detach($label->id);
        if ($detached > 0) {
            Activity::log(
                $subtask->task->project->space->workspace,
                $user,
                $subtask,
                'label_removed',
                [
                    'name' => $subtask->name,
                    'label_name' => $label->name,
                ]
            );
        }
    }

    /**
     * Duplicate a subtask with its labels and assignees.
     *
     * Creates a copy with " (Copy)" appended to the name, resets completion
     * state, and logs the duplication activity.
     */
    public function duplicate(Subtask $subtask, User $user): Subtask
    {
        return DB::transaction(function () use ($subtask, $user) {
            $newSubtask = $subtask->replicate([
                'subtask_id',
                'completed_at',
                'completed_by',
                'time_spent',
                'sprint_id',
                'position',
            ]);
            $newSubtask->name = $subtask->name.' (Copy)';
            // Reset operational fields — dates and assignees are not part of the template
            $newSubtask->start_date = null;
            $newSubtask->due_date = null;
            $newSubtask->baseline_start_date = null;
            $newSubtask->baseline_due_date = null;
            $newSubtask->progress = 0;
            $newSubtask->save();

            // Copy labels (structural), but NOT assignees (operational)
            $newSubtask->labels()->sync($subtask->labels->pluck('id'));

            $task = $subtask->task;
            Activity::log(
                $task->project->space->workspace,
                $user,
                $task,
                'duplicated',
                ['name' => $newSubtask->name, 'original_name' => $subtask->name]
            );

            return $newSubtask;
        });
    }
}
