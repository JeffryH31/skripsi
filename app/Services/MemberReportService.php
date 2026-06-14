<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Subtask;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;

class MemberReportService
{
    public function getReport(Workspace $workspace, User $member): array
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $weekStart = $now->copy()->startOfWeek();
        $monthStart = $now->copy()->startOfMonth();

        // Base query factory scoped to this workspace member
        $base = fn () => TimeEntry::where('user_id', $member->id)
            ->whereHas('subtask.task.project.space', fn ($q) => $q->where('workspace_id', $workspace->id));

        // --- Summary stats ---
        $stats = [
            'today_minutes' => (clone $base())->where('started_at', '>=', $todayStart)->sum('duration'),
            'week_minutes' => (clone $base())->where('started_at', '>=', $weekStart)->sum('duration'),
            'month_minutes' => (clone $base())->where('started_at', '>=', $monthStart)->sum('duration'),
            'billable_minutes' => (clone $base())->where('is_billable', true)->where('started_at', '>=', $monthStart)->sum('duration'),
            'all_time_minutes' => (clone $base())->sum('duration'),
        ];

        // --- Running timer ---
        $runningEntry = TimeEntry::where('user_id', $member->id)
            ->where('is_running', true)
            ->with(['subtask.task.project.space'])
            ->first();

        $runningTimer = null;
        if ($runningEntry) {
            $runningTimer = [
                'id' => $runningEntry->id,
                'started_at' => $runningEntry->started_at->toDateTimeString(),
                'elapsed_minutes' => (int) $runningEntry->started_at->diffInMinutes(now()),
                'subtask' => $runningEntry->subtask->name,
                'task' => $runningEntry->subtask->task->name,
                'list' => $runningEntry->subtask->task->project->name,
                'space' => $runningEntry->subtask->task->project->space->name,
            ];
        }

        // --- Daily breakdown: last 14 days ---
        $dailyData = [];
        for ($i = 13; $i >= 0; $i--) {
            $day = $now->copy()->subDays($i);
            $minutes = (clone $base())
                ->whereDate('started_at', $day->toDateString())
                ->sum('duration');
            $dailyData[] = [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('d/m'),
                'minutes' => (int) $minutes,
                'hours' => round($minutes / 60, 1),
            ];
        }

        $maxDailyMinutes = max(1, max(array_column($dailyData, 'minutes')));

        foreach ($dailyData as &$d) {
            $d['pct'] = (int) round(($d['minutes'] / $maxDailyMinutes) * 100);
        }
        unset($d);

        // --- Weekly breakdown: Mon–Sun current week ---
        $weeklyData = [];
        for ($i = 0; $i <= 6; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $minutes = (clone $base())
                ->whereDate('started_at', $day->toDateString())
                ->sum('duration');
            $weeklyData[] = [
                'day' => $day->format('D'),
                'date' => $day->format('Y-m-d'),
                'minutes' => (int) $minutes,
                'hours' => round($minutes / 60, 1),
            ];
        }

        // --- Active subtasks (assigned, not yet completed) ---
        $activeSubtasks = Subtask::whereNull('completed_at')
            ->whereNull('deleted_at')
            ->whereHas('task.project.space', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $member->id))
            ->with(['status', 'task.project.space', 'sprint'])
            ->orderByDesc('updated_at')
            ->limit(30)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'subtask_id' => $s->subtask_id,
                'name' => $s->name,
                'status' => ['name' => $s->status?->name, 'color' => $s->status?->color],
                'priority' => $s->priority_level?->value,
                'due_date' => $s->due_date?->toDateString(),
                'is_overdue' => $s->due_date && $s->due_date->isPast() && ! $s->completed_at,
                'progress' => $s->progress ?? 0,
                'time_estimate' => $s->time_estimate,
                'time_spent' => $s->time_spent,
                'task' => ['name' => $s->task->name, 'id' => $s->task->id],
                'list' => ['name' => $s->task->project->name, 'id' => $s->task->project->id],
                'space' => ['name' => $s->task->project->space->name, 'id' => $s->task->project->space->id],
                'sprint' => $s->sprint ? ['name' => $s->sprint->name] : null,
            ])->values()->toArray();

        // --- Recently completed subtasks (last 30 days) ---
        $recentlyCompleted = Subtask::whereNotNull('completed_at')
            ->whereNull('deleted_at')
            ->where('completed_at', '>=', $now->copy()->subDays(30))
            ->whereHas('task.project.space', fn ($q) => $q->where('workspace_id', $workspace->id))
            ->whereHas('assignees', fn ($q) => $q->where('users.id', $member->id))
            ->with(['task.project.space'])
            ->orderByDesc('completed_at')
            ->limit(15)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'subtask_id' => $s->subtask_id,
                'name' => $s->name,
                'completed_at' => $s->completed_at->toDateTimeString(),
                'task' => $s->task->name,
                'list' => $s->task->project->name,
                'space' => $s->task->project->space->name,
            ])->values()->toArray();

        // --- Recent time entries (last 30 entries) ---
        $recentEntries = (clone $base())
            ->with(['subtask.task.project.space'])
            ->orderByDesc('started_at')
            ->limit(30)
            ->get()
            ->map(fn ($e) => [
                'id' => $e->id,
                'duration' => $e->duration,
                'is_billable' => $e->is_billable,
                'is_running' => $e->is_running,
                'started_at' => $e->started_at->toDateTimeString(),
                'ended_at' => $e->ended_at?->toDateTimeString(),
                'subtask' => [
                    'id' => $e->subtask->id,
                    'name' => $e->subtask->name,
                    'subtask_id' => $e->subtask->subtask_id,
                ],
                'task' => $e->subtask->task->name,
                'list' => $e->subtask->task->project->name,
                'space' => $e->subtask->task->project->space->name,
            ])->values()->toArray();

        // --- Recent activity log (last 30 entries) ---
        $recentActivity = Activity::where('workspace_id', $workspace->id)
            ->where('user_id', $member->id)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'description' => $a->description,
                'created_at' => $a->created_at->toDateTimeString(),
                'subject_type' => class_basename($a->subject_type),
                'properties' => $a->properties,
            ])->values()->toArray();

        return compact(
            'stats',
            'runningTimer',
            'dailyData',
            'weeklyData',
            'activeSubtasks',
            'recentlyCompleted',
            'recentEntries',
            'recentActivity',
        );
    }
}
