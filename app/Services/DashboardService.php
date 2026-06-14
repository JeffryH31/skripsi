<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;

class DashboardService
{
    public function __construct(
        protected TaskService $taskService,
        protected AccessService $accessService,
    ) {}

    /**
     * Get the dashboard payload for the given user and workspace.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(User $user, Workspace $workspace): array
    {
        $this->loadWorkspaceHierarchy($user, $workspace);

        $mySubtasks = $this->taskService->getMySubtasks($user);
        $overdueSubtasks = $this->taskService->getMySubtasks($user, ['is_overdue' => true]);

        return [
            'mySubtasks' => $mySubtasks,
            'overdueSubtasks' => $overdueSubtasks,
            'runningTimer' => $this->getRunningTimer($user),
            'timeStats' => $this->getTimeStats($user, $mySubtasks->count()),
            'recentActivity' => $this->getRecentActivity($workspace),
        ];
    }

    /**
     * Load workspace hierarchy with access-filtered spaces and projects.
     */
    public function loadWorkspaceHierarchy(User $user, Workspace $workspace): void
    {
        $isWsAdmin = $this->accessService->canManageWorkspace($user, $workspace);
        $listFilter = function ($q) use ($user, $isWsAdmin) {
            return $isWsAdmin ? $q : $q->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id));
        };

        $workspace->load([
            'spaces' => function ($q) use ($user, $isWsAdmin, $listFilter) {
                if (! $isWsAdmin) {
                    $q->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id));
                }
                $q->with([
                    'folders' => fn ($fq) => $fq->with(['projects' => $listFilter])->orderBy('position'),
                    'projectsWithoutFolder' => fn ($lq) => $listFilter($lq)->orderBy('position'),
                    'statuses' => fn ($sq) => $sq->orderBy('position'),
                ])->orderBy('position');
            },
            'members',
            'labels',
        ]);
    }

    /**
     * Get the running timer for a user.
     */
    protected function getRunningTimer(User $user): ?TimeEntry
    {
        return TimeEntry::where('user_id', $user->id)
            ->where('is_running', true)
            ->with('subtask.task.project.space')
            ->first();
    }

    /**
     * Calculate time statistics for the dashboard.
     *
     * @return array<string, int>
     */
    protected function getTimeStats(User $user, int $todoCount): array
    {
        $todayTimeSpent = $user->getTodayTimeSpent();
        $weekTimeSpent = $user->getWeekTimeSpent();

        $workdayStart = now()->copy()->setTime(config('business.workday_start_hour'), 0);
        $workdayEnd = now()->copy()->setTime(config('business.workday_end_hour'), 0);
        $breakStart = now()->copy()->setTime(config('business.break_start_hour'), 0);
        $breakEnd = now()->copy()->setTime(config('business.break_end_hour'), 0);

        if (now()->lessThanOrEqualTo($workdayStart)) {
            $todayCapacity = 0;
        } else {
            $raw = $workdayStart->diffInMinutes(now()->min($workdayEnd));
            $breakElapsed = now()->greaterThan($breakStart)
                ? $breakStart->diffInMinutes(now()->min($breakEnd))
                : 0;
            $todayCapacity = max(0, $raw - $breakElapsed);
        }

        $weekCapacity = now()->startOfWeek()->diffInWeekdays(now()->endOfDay()->min(now()))
            * config('business.working_hours_per_day', 8) * 60;

        return [
            'today' => $todayTimeSpent,
            'week' => $weekTimeSpent,
            'idle_today' => max(0, $todayCapacity - $todayTimeSpent),
            'idle_week' => max(0, $weekCapacity - $weekTimeSpent),
            'todo_count' => $todoCount,
        ];
    }

    /**
     * Get recent activity for a workspace.
     *
     * @return array<int, array<string, mixed>>|\Illuminate\Support\Collection
     */
    protected function getRecentActivity(Workspace $workspace): array|\Illuminate\Support\Collection
    {
        return Activity::where('workspace_id', $workspace->id)
            ->with('user')
            ->latest()
            ->limit(config('business.limits.recent_activity'))
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'action' => $a->action,
                'description' => $a->description,
                'properties' => $a->properties,
                'created_at' => $a->created_at,
                'user' => $a->user ? [
                    'id' => $a->user->id,
                    'name' => $a->user->name,
                    'initials' => $a->user->initials,
                    'avatar_color' => $a->user->avatar_color,
                    'profile_photo_url' => $a->user->profile_photo_url,
                ] : null,
            ]);
    }
}
