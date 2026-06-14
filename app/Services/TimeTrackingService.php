<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TimeTrackingService
{
    /**
     * Log time entry for a subtask
     */
    public function logTime(Subtask $subtask, User $user, array $data): TimeEntry
    {
        return DB::transaction(function () use ($subtask, $user, $data) {
            $startedAt = isset($data['started_at']) ? \Carbon\Carbon::parse($data['started_at']) : now();
            $endedAt = isset($data['ended_at']) ? \Carbon\Carbon::parse($data['ended_at']) : null;

            $duration = $data['duration'] ?? null;
            if (! is_null($endedAt)) {
                $duration = max(1, (int) $startedAt->diffInMinutes($endedAt));
            }
            if (is_null($duration)) {
                $duration = 1;
            }

            $entry = TimeEntry::create([
                'subtask_id' => $subtask->id,
                'user_id' => $user->id,
                'duration' => $duration, // in minutes
                'started_at' => $startedAt,
                'ended_at' => $endedAt ?? $startedAt->copy()->addMinutes($duration),
                'is_billable' => $data['is_billable'] ?? false,
            ]);

            $workspace = $subtask->task->project->space->workspace;

            Activity::log($workspace, $user, $subtask->task, 'time_logged', [
                'name' => $subtask->name,
                'duration' => $entry->duration,
                'duration_formatted' => $entry->duration_formatted,
            ]);

            return $entry;
        });
    }

    /**
     * Start timer for a subtask
     */
    public function startTimer(Subtask $subtask, User $user): TimeEntry
    {
        $entry = TimeEntry::startTimer($subtask, $user);

        $workspace = $subtask->task->project->space->workspace;

        Activity::log($workspace, $user, $subtask->task, 'timer_started', [
            'name' => $subtask->name,
        ]);

        return $entry;
    }

    /**
     * Stop running timer
     */
    public function stopTimer(TimeEntry $entry, User $user): TimeEntry
    {
        $entry->stop();

        $workspace = $entry->subtask->task->project->space->workspace;

        Activity::log($workspace, $user, $entry->subtask->task, 'timer_stopped', [
            'name' => $entry->subtask->name,
            'duration' => $entry->duration,
            'duration_formatted' => $entry->duration_formatted,
        ]);

        return $entry->fresh();
    }

    /**
     * Get running timer for user
     */
    public function getRunningTimer(User $user): ?TimeEntry
    {
        return TimeEntry::where('user_id', $user->id)
            ->where('is_running', true)
            ->with('subtask.task.project.space')
            ->first();
    }

    /**
     * Update a time entry
     */
    public function updateEntry(TimeEntry $entry, array $data, User $user): TimeEntry
    {
        $oldDuration = $entry->duration;

        $updateData = [
            'started_at' => $data['started_at'] ?? $entry->started_at,
            'ended_at' => $data['ended_at'] ?? $entry->ended_at,
            'is_billable' => $data['is_billable'] ?? $entry->is_billable,
        ];

        if (isset($data['started_at']) && isset($data['ended_at'])) {
            $updateData['duration'] = max(1, (int) \Carbon\Carbon::parse($data['started_at'])->diffInMinutes(\Carbon\Carbon::parse($data['ended_at'])));
        } elseif (isset($data['duration'])) {
            $updateData['duration'] = $data['duration'];
        }

        $entry->update($updateData);

        if ($oldDuration !== $entry->duration) {
            $workspace = $entry->subtask->task->project->space->workspace;

            Activity::log($workspace, $user, $entry->subtask->task, 'time_updated', [
                'name' => $entry->subtask->name,
            ], [
                'duration' => ['old' => $oldDuration, 'new' => $entry->duration],
            ]);
        }

        return $entry->fresh();
    }

    /**
     * Delete a time entry
     */
    public function deleteEntry(TimeEntry $entry, User $user): void
    {
        $workspace = $entry->subtask->task->project->space->workspace;

        Activity::log($workspace, $user, $entry->subtask->task, 'time_deleted', [
            'name' => $entry->subtask->name,
            'duration' => $entry->duration,
        ]);

        $entry->delete();
    }

    /**
     * Get time entries for a task
     */
    public function getEntriesForTask(Task $task): Collection
    {
        return $task->timeEntries()
            ->with('user')
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * Get time entries for a user
     */
    public function getEntriesForUser(User $user, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $query = $user->timeEntries()
            ->with(['subtask.task.project.space'])
            ->orderBy('started_at', 'desc');

        if ($startDate) {
            $query->whereDate('started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('started_at', '<=', $endDate);
        }

        return $query->get();
    }

    /**
     * Get available subtasks for the "Log Time" dialog in a workspace.
     *
     * Returns active (not completed, not deleted) subtasks accessible by the user.
     */
    public function getAvailableSubtasksForTimeLog(User $user, Workspace $workspace): Collection
    {
        return Subtask::query()
            ->whereNull('completed_at')
            ->whereNull('deleted_at')
            ->whereHas('task.project', fn ($q) => $q
                ->whereHas('space', fn ($sq) => $sq->where('workspace_id', $workspace->id))
                ->accessibleBy($user)
            )
            ->with(['task.project.space'])
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'task_id' => $s->task->id,
                'task_name' => $s->task->name,
                'project_id' => $s->task->project->id,
                'project_name' => $s->task->project->name,
                'space_id' => $s->task->project->space->id,
                'space_name' => $s->task->project->space->name,
            ]);
    }

    /**
     * Get time summary for a task
     */
    public function getTaskTimeSummary(Task $task): array
    {
        $entries = $task->timeEntries;
        $totalMinutes = $entries->sum('duration');
        $estimatedMinutes = $task->subtasks()->sum('time_estimate');

        return [
            'total_minutes' => $totalMinutes,
            'total_formatted' => $this->formatMinutes($totalMinutes),
            'estimated_minutes' => $estimatedMinutes,
            'estimated_formatted' => $this->formatMinutes($estimatedMinutes),
            'remaining_minutes' => max(0, $estimatedMinutes - $totalMinutes),
            'progress' => $estimatedMinutes > 0
                ? min(100, round(($totalMinutes / $estimatedMinutes) * 100, 1))
                : 0,
            'entries_count' => $entries->count(),
            'billable_minutes' => $entries->where('is_billable', true)->sum('duration'),
        ];
    }

    /**
     * Get user time summary
     */
    public function getUserTimeSummary(User $user, string $period = 'week'): array
    {
        $startDate = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            default => now()->startOfWeek(),
        };

        $entries = $user->timeEntries()
            ->where('started_at', '>=', $startDate)
            ->with('subtask.task')
            ->get();

        $byTask = $entries->groupBy('subtask_id')->map(fn ($taskEntries) => [
            'subtask' => $taskEntries->first()->subtask,
            'total_minutes' => $taskEntries->sum('duration'),
        ])->sortByDesc('total_minutes')->values();

        $byDay = $entries->groupBy(fn ($e) => $e->started_at->format('Y-m-d'))
            ->map(fn ($dayEntries) => [
                'date' => $dayEntries->first()->started_at->format('Y-m-d'),
                'total_minutes' => $dayEntries->sum('duration'),
            ])->values();

        return [
            'total_minutes' => $entries->sum('duration'),
            'total_formatted' => $this->formatMinutes($entries->sum('duration')),
            'billable_minutes' => $entries->where('is_billable', true)->sum('duration'),
            'entries_count' => $entries->count(),
            'by_task' => $byTask->take(10),
            'by_day' => $byDay,
        ];
    }

    /**
     * Get workspace time report
     */
    public function getWorkspaceTimeReport(Workspace $workspace,
        ?string $startDate = null,
        ?string $endDate = null): array
    {
        $query = TimeEntry::query()
            ->join('subtasks', 'time_entries.subtask_id', '=', 'subtasks.id')
            ->join('tasks', 'subtasks.task_id', '=', 'tasks.id')
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->join('spaces', 'projects.space_id', '=', 'spaces.id')
            ->where('spaces.workspace_id', $workspace->id);

        if ($startDate) {
            $query->where('time_entries.started_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('time_entries.started_at', '<=', $endDate);
        }

        $byUser = DB::table('time_entries')
            ->join('subtasks', 'time_entries.subtask_id', '=', 'subtasks.id')
            ->join('tasks', 'subtasks.task_id', '=', 'tasks.id')
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->join('spaces', 'projects.space_id', '=', 'spaces.id')
            ->join('users', 'time_entries.user_id', '=', 'users.id')
            ->where('spaces.workspace_id', $workspace->id)
            ->when($startDate, fn ($q) => $q->where('time_entries.started_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('time_entries.started_at', '<=', $endDate))
            ->select('users.id', 'users.name', DB::raw('SUM(time_entries.duration) as total_minutes'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_minutes')
            ->get();

        $bySpace = DB::table('time_entries')
            ->join('subtasks', 'time_entries.subtask_id', '=', 'subtasks.id')
            ->join('tasks', 'subtasks.task_id', '=', 'tasks.id')
            ->join('projects', 'tasks.project_id', '=', 'projects.id')
            ->join('spaces', 'projects.space_id', '=', 'spaces.id')
            ->where('spaces.workspace_id', $workspace->id)
            ->when($startDate, fn ($q) => $q->where('time_entries.started_at', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->where('time_entries.started_at', '<=', $endDate))
            ->select('spaces.id', 'spaces.name', DB::raw('SUM(time_entries.duration) as total_minutes'))
            ->groupBy('spaces.id', 'spaces.name')
            ->orderByDesc('total_minutes')
            ->get();

        $totalMinutes = $query->sum('time_entries.duration');

        return [
            'total_minutes' => $totalMinutes,
            'total_formatted' => $this->formatMinutes($totalMinutes),
            'by_user' => $byUser,
            'by_space' => $bySpace,
        ];
    }

    /**
     * Format minutes to human readable string
     */
    protected function formatMinutes(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0) {
            return $hours.'h '.$mins.'m';
        }

        return $mins.'m';
    }
}
