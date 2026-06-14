<?php

namespace App\Http\Middleware;

use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Folder;
use App\Models\Project;
use App\Models\Space;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $activeWorkspaceId = session('active_workspace_id');
        $activeWorkspace = null;
        $workspaces = collect([]);

        if ($request->user()) {
            $notificationWorkspaceIds = $request->user()->workspaces()->pluck('workspaces.id');
            $workspaces = $request->user()
                ->workspaces()
                ->orderBy('created_at', 'desc')
                ->get();

            // Keep sidebar workspace in sync with routes scoped by {workspace}.
            $routeWorkspace = $request->route('workspace');
            $routeWorkspaceId = null;

            if ($routeWorkspace instanceof Workspace) {
                $routeWorkspaceId = $routeWorkspace->id;
            } elseif (is_numeric($routeWorkspace)) {
                $routeWorkspaceId = (int) $routeWorkspace;
            }

            if ($routeWorkspaceId && $workspaces->contains('id', $routeWorkspaceId)) {
                $activeWorkspaceId = $routeWorkspaceId;
                session(['active_workspace_id' => $routeWorkspaceId]);
            }

            if ($activeWorkspaceId) {
                $activeWorkspace = $workspaces->firstWhere('id', $activeWorkspaceId);
            }

            if (! $activeWorkspace && $workspaces->isNotEmpty()) {
                $activeWorkspace = $workspaces->first();
                session(['active_workspace_id' => $activeWorkspace->id]);
            }

            if ($activeWorkspace) {
                $user = $request->user();
                $wsRole = $activeWorkspace->members()
                    ->where('user_id', $user->id)
                    ->first()?->pivot?->role;
                $isWsAdmin = in_array($wsRole, ['admin', 'owner'], true);

                $listAccessFilter = function ($query) use ($user, $isWsAdmin) {
                    if ($isWsAdmin) {
                        return $query;
                    }

                    return $query->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id));
                };

                $activeWorkspace->load([
                    'spaces' => function ($query) use ($user, $isWsAdmin, $listAccessFilter) {
                        // Non-admin users only see spaces they are a member of
                        if (! $isWsAdmin) {
                            $query->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id));
                        }
                        $query->with([
                            'folders.projects' => $listAccessFilter,
                            'projectsWithoutFolder' => $listAccessFilter,
                        ])->orderBy('position');
                    },
                    'labels' => fn ($q) => $q->orderBy('name'),
                ]);

                // Compute per-user starred spaces and annotate each space with is_starred.
                $starredSpaceIds = DB::table('starred_spaces')
                    ->where('user_id', $request->user()->id)
                    ->where('workspace_id', $activeWorkspace->id)
                    ->pluck('space_id')
                    ->toArray();

                $starredSpaces = collect();
                foreach ($activeWorkspace->spaces as $space) {
                    $space->is_starred = in_array($space->id, $starredSpaceIds);
                    if ($space->is_starred) {
                        $starredSpaces->push($space);
                    }
                }
                $activeWorkspace->setRelation('starred_spaces', $starredSpaces);
            }
        }

        // Actions too noisy / low-value for notifications
        $notificationIgnoredActions = ['timer_started', 'timer_stopped', 'time_updated', 'time_deleted'];

        $notifications = $request->user()
            ? Activity::query()
                ->whereIn('workspace_id', $notificationWorkspaceIds ?? [])
                ->where('user_id', '!=', $request->user()->id)
                ->whereNotIn('action', $notificationIgnoredActions)
                ->with(['user', 'subject' => function (MorphTo $morphTo) {
                    $morphTo->morphWith([
                        Task::class => ['project.space'],
                        Subtask::class => ['task.project.space'],
                        Project::class => ['space'],
                        Folder::class => ['space'],
                        Space::class => [],
                        Workspace::class => [],
                    ]);
                }])
                ->latest()
                ->limit(10)
                ->get()
            : collect();

        $unreadNotificationsCount = $request->user()
            ? Activity::query()
                ->whereIn('workspace_id', $notificationWorkspaceIds ?? [])
                ->where('user_id', '!=', $request->user()->id)
                ->whereNotIn('action', $notificationIgnoredActions)
                ->when(
                    $request->user()->last_notifications_read_at,
                    fn ($q) => $q->where('created_at', '>', $request->user()->last_notifications_read_at)
                )
                ->count()
            : 0;

        return [
            ...parent::share($request),
            'activeWorkspace' => $activeWorkspace,
            'workspaces' => $workspaces,
            'isSuperAdmin' => (bool) $request->user()?->is_super_admin,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'validationErrors' => fn () => collect(($request->session()->get('errors') instanceof ViewErrorBag)
                ? $request->session()->get('errors')->getBag('default')->getMessages()
                : [])->map(fn ($messages) => $messages[0] ?? null)->filter()->values(),
            'notifications' => ActivityResource::collection($notifications),
            'unreadNotificationsCount' => min($unreadNotificationsCount, 10),
            'notificationsLastReadAt' => $request->user()?->last_notifications_read_at?->toISOString(),
            'runningTimer' => fn () => $request->user() ? TimeEntry::where('user_id', $request->user()->id)
                ->where('is_running', true)
                ->with('subtask.task.project.space')
                ->first() : null,
        ];
    }
}
