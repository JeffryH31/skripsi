<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Activity;
use App\Models\Space;
use App\Models\Task;
use App\Models\TaskList;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ActivityService
 *
 * Handles all business logic related to activity logging and retrieval.
 * Updated for Hierarchy: Workspace -> Space -> Folder -> List -> Task -> Subtask
 */
class ActivityService
{
    /**
     * Log an activity.
     */
    public function log(User $user, Model $subject, string $action, array $metadata = []): Activity
    {
        return Activity::create([
            'user_id' => $user->id,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->getKey(),
            'type' => $action,
            'properties' => $metadata,
        ]);
    }

    // ==========================================
    // Task Activity Logs
    // ==========================================
    /**
     * Log task created activity.
     */
    public function logTaskCreated(User $user, Task $task): Activity
    {
        return $this->log($user, $task, 'task.created', [
            'task_title' => $task->title,
            'list_name' => $task->list?->name,
            'space_name' => $task->list?->space?->name,
        ]);
    }

    /**
     * Log task updated activity.
     */
    public function logTaskUpdated(User $user, Task $task, array $changes): Activity
    {
        return $this->log($user, $task, 'task.updated', [
            'task_title' => $task->title,
            'changes' => $changes,
        ]);
    }

    /**
     * Log task completed activity.
     */
    public function logTaskCompleted(User $user, Task $task): Activity
    {
        return $this->log($user, $task, 'task.completed', [
            'task_title' => $task->title,
            'actual_hours' => $task->actual_hours,
            'estimated_hours' => $task->estimated_hours,
        ]);
    }

    /**
     * Log task moved activity.
     */
    public function logTaskMoved(User $user, Task $task, string $fromList, string $toList): Activity
    {
        return $this->log($user, $task, 'task.moved', [
            'task_title' => $task->title,
            'from_list' => $fromList,
            'to_list' => $toList,
        ]);
    }

    /**
     * Log task status changed activity.
     */
    public function logTaskStatusChanged(User $user, Task $task, string $fromStatus, string $toStatus): Activity
    {
        return $this->log($user, $task, 'task.status_changed', [
            'task_title' => $task->title,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
        ]);
    }

    /**
     * Log task assigned activity.
     */
    public function logTaskAssigned(User $user, Task $task, User $assignee): Activity
    {
        return $this->log($user, $task, 'task.assigned', [
            'task_title' => $task->title,
            'assignee_name' => $assignee->name,
            'assignee_id' => $assignee->id,
        ]);
    }

    /**
     * Log estimation updated activity.
     */
    public function logEstimationUpdated(User $user, Task $task, float $oldEstimate, float $newEstimate): Activity
    {
        return $this->log($user, $task, 'task.estimation_updated', [
            'task_title' => $task->title,
            'old_estimate' => $oldEstimate,
            'new_estimate' => $newEstimate,
        ]);
    }

    /**
     * Log subtask created activity.
     */
    public function logSubtaskCreated(User $user, Task $subtask): Activity
    {
        return $this->log($user, $subtask, 'subtask.created', [
            'subtask_title' => $subtask->title,
            'parent_task' => $subtask->parent?->title,
        ]);
    }

    // ==========================================
    // Timer Activity Logs
    // ==========================================
    /**
     * Log timer started activity.
     */
    public function logTimerStarted(User $user, Task $task): Activity
    {
        return $this->log($user, $task, 'timer.started', [
            'task_title' => $task->title,
        ]);
    }

    /**
     * Log timer stopped activity.
     */
    public function logTimerStopped(User $user, Task $task, int $durationMinutes): Activity
    {
        return $this->log($user, $task, 'timer.stopped', [
            'task_title' => $task->title,
            'duration_minutes' => $durationMinutes,
            'duration_formatted' => $this->formatDuration($durationMinutes),
        ]);
    }

    /**
     * Log manual time logged activity.
     */
    public function logManualTimeLogged(User $user, Task $task, int $durationMinutes): Activity
    {
        return $this->log($user, $task, 'time.manual_logged', [
            'task_title' => $task->title,
            'duration_minutes' => $durationMinutes,
            'duration_formatted' => $this->formatDuration($durationMinutes),
        ]);
    }

    // ==========================================
    // Space Activity Logs
    // ==========================================
    /**
     * Log space created activity.
     */
    public function logSpaceCreated(User $user, Space $space): Activity
    {
        return $this->log($user, $space, 'space.created', [
            'space_name' => $space->name,
            'workspace' => $space->workspace?->name,
        ]);
    }

    /**
     * Log space updated activity.
     */
    public function logSpaceUpdated(User $user, Space $space, array $changes): Activity
    {
        return $this->log($user, $space, 'space.updated', [
            'space_name' => $space->name,
            'changes' => $changes,
        ]);
    }

    // ==========================================
    // List Activity Logs
    // ==========================================
    /**
     * Log list created activity.
     */
    public function logListCreated(User $user, TaskList $list): Activity
    {
        return $this->log($user, $list, 'list.created', [
            'list_name' => $list->name,
            'space_name' => $list->space?->name,
            'folder_name' => $list->folder?->name,
        ]);
    }

    /**
     * Log list updated activity.
     */
    public function logListUpdated(User $user, TaskList $list, array $changes): Activity
    {
        return $this->log($user, $list, 'list.updated', [
            'list_name' => $list->name,
            'changes' => $changes,
        ]);
    }

    // ==========================================
    // Workspace Activity Logs
    // ==========================================
    /**
     * Log member added to workspace activity.
     */
    public function logMemberAdded(User $user, Workspace $workspace, User $member): Activity
    {
        return $this->log($user, $workspace, 'workspace.member_added', [
            'workspace_name' => $workspace->name,
            'member_name' => $member->name,
            'member_id' => $member->id,
        ]);
    }

    // ==========================================
    // Retrieval Methods
    // ==========================================
    /**
     * Get activities for a specific space.
     */
    public function getActivitiesForSpace(Space $space, int $perPage = 20): LengthAwarePaginator
    {
        $listIds = $space->projects()->pluck('id')->toArray();
        $taskIds = Task::whereIn('project_id', $listIds)->pluck('id')->toArray();

        return Activity::with('user')
            ->where(function ($query) use ($space, $listIds, $taskIds) {
                $query->where(function ($q) use ($space) {
                    $q->where('subject_type', Space::class)
                        ->where('subject_id', $space->id);
                })->orWhere(function ($q) use ($listIds) {
                    $q->where('subject_type', TaskList::class)
                        ->whereIn('subject_id', $listIds);
                })->orWhere(function ($q) use ($taskIds) {
                    $q->where('subject_type', Task::class)
                        ->whereIn('subject_id', $taskIds);
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get activities for a specific user.
     */
    public function getActivitiesForUser(User $user, int $perPage = 20): LengthAwarePaginator
    {
        return Activity::with(['user', 'subject'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get recent activities for a list.
     */
    public function getActivitiesForList(TaskList $list, int $limit = 10)
    {
        $taskIds = $list->tasks()->pluck('id')->toArray();

        return Activity::with('user')
            ->where(function ($query) use ($list, $taskIds) {
                $query->where(function ($q) use ($list) {
                    $q->where('subject_type', TaskList::class)
                        ->where('subject_id', $list->id);
                })->orWhere(function ($q) use ($taskIds) {
                    $q->where('subject_type', Task::class)
                        ->whereIn('subject_id', $taskIds);
                });
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent activities for a task.
     */
    public function getActivitiesForTask(Task $task, int $limit = 10)
    {
        $subtaskIds = $task->subtasks()->pluck('id')->toArray();
        $allIds = array_merge([$task->id], $subtaskIds);

        return Activity::with('user')
            ->where('subject_type', Task::class)
            ->whereIn('subject_id', $allIds)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent activities across all accessible workspaces.
     */
    public function getRecentActivities(User $user, int $limit = 50)
    {
        // Get all space IDs user has access to
        $spaceIds = Space::whereHas('workspace', function ($query) use ($user) {
            $query->accessibleBy($user->id);
        })->pluck('id')->toArray();

        // Get list and task IDs
        $listIds = TaskList::whereIn('space_id', $spaceIds)->pluck('id')->toArray();
        $taskIds = Task::whereIn('list_id', $listIds)->pluck('id')->toArray();

        return Activity::with(['user', 'subject'])
            ->where(function ($query) use ($spaceIds, $listIds, $taskIds) {
                $query->where(function ($q) use ($spaceIds) {
                    $q->where('subject_type', Space::class)
                        ->whereIn('subject_id', $spaceIds);
                })->orWhere(function ($q) use ($listIds) {
                    $q->where('subject_type', TaskList::class)
                        ->whereIn('subject_id', $listIds);
                })->orWhere(function ($q) use ($taskIds) {
                    $q->where('subject_type', Task::class)
                        ->whereIn('subject_id', $taskIds);
                });
            })
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    // ==========================================
    // Helper Methods
    // ==========================================
    /**
     * Format duration in minutes to human readable string.
     */
    private function formatDuration(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes}m";
        }

        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return "{$hours}h";
        }

        return "{$hours}h {$remainingMinutes}m";
    }
}
