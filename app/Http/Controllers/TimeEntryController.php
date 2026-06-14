<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTimeEntryRequest;
use App\Http\Requests\UpdateTimeEntryRequest;
use App\Models\Project;
use App\Models\Space;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\TimeTrackingService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimeEntryController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected TimeTrackingService $timeTrackingService,
        protected AccessService $accessService,
    ) {}

    /**
     * Display time entries for user.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $user = $request->user();

        $entries = $this->timeTrackingService->getEntriesForUser(
            $user,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        $runningTimer = $this->timeTrackingService->getRunningTimer($user);

        $workspaces = $user->workspaces()->with([
            'spaces' => function ($q) use ($user) {
                $q->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id));
                $q->with([
                    'folders.projects' => fn ($lq) => $lq->accessibleBy($user),
                    'projectsWithoutFolder' => fn ($lq) => $lq->accessibleBy($user),
                ])->orderBy('position');
            },
        ])->get();

        $activeWorkspaceId = session('active_workspace_id', $workspaces->first()?->id);
        $activeWorkspace = $workspaces->firstWhere('id', $activeWorkspaceId) ?? $workspaces->first();

        $subtasks = $activeWorkspace
            ? $this->timeTrackingService->getAvailableSubtasksForTimeLog($user, $activeWorkspace)
            : collect();

        return Inertia::render('TimeTracking/Index', [
            'activeWorkspace' => $activeWorkspace,
            'entries' => $entries,
            'runningTimer' => $runningTimer,
            'subtasks' => $subtasks,
        ]);
    }

    /**
     * Log time entry.
     */
    public function store(StoreTimeEntryRequest $request, Workspace $workspace, Space $space, Project $project, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless($this->accessService->canTrackTime($request->user(), $project), 403);
        try {
            $entry = $this->timeTrackingService->logTime(
                $subtask,
                $request->user(),
                $request->validated()
            );

            return redirect()->back()->with([
                'success' => 'Time logged successfully.',
                'timeEntry' => new \App\Http\Resources\TimeEntryResource($entry->load('user')),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to log time: '.$e->getMessage()]);
        }
    }

    /**
     * Start timer.
     */
    public function startTimer(Request $request, Workspace $workspace, Space $space, Project $project, Task $task): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        abort_unless($this->accessService->canTrackTime($request->user(), $project), 403);
        $validated = $request->validate([
            'subtask_id' => ['nullable', 'integer', 'exists:subtasks,id'],
        ]);

        try {
            $subtask = ! empty($validated['subtask_id'])
                ? Subtask::where('id', $validated['subtask_id'])->where('task_id', $task->id)->firstOrFail()
                : Subtask::where('task_id', $task->id)->firstOrFail();

            $entry = $this->timeTrackingService->startTimer(
                $subtask,
                $request->user()
            );

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Timer started successfully.',
                    'timeEntry' => new \App\Http\Resources\TimeEntryResource($entry->load('user')),
                ]);
            }

            return redirect()->back()->with([
                'success' => 'Timer started successfully.',
                'timeEntry' => new \App\Http\Resources\TimeEntryResource($entry->load('user')),
            ]);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['error' => 'Failed to start timer: '.$e->getMessage()]);
        }
    }

    /**
     * Stop timer.
     */
    public function stopTimer(Request $request, Workspace $workspace, Space $space, Project $project, Task $task, TimeEntry $entry): \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        abort_unless($this->accessService->canManageTimeEntry($request->user(), $entry), 403);
        try {
            $stoppedEntry = $this->timeTrackingService->stopTimer($entry, $request->user());

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Timer stopped successfully.',
                    'timeEntry' => new \App\Http\Resources\TimeEntryResource($stoppedEntry->load('user')),
                ]);
            }

            return redirect()->back()->with([
                'success' => 'Timer stopped successfully.',
                'timeEntry' => new \App\Http\Resources\TimeEntryResource($stoppedEntry->load('user')),
            ]);
        } catch (\Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['error' => 'Failed to stop timer: '.$e->getMessage()]);
        }
    }

    /**
     * Get running timer.
     */
    public function runningTimer(Request $request): \Illuminate\Http\JsonResponse|\Inertia\Response
    {
        $timer = $this->timeTrackingService->getRunningTimer($request->user());

        if ($request->wantsJson()) {
            return response()->json([
                'timer' => $timer ? new \App\Http\Resources\TimeEntryResource($timer->load('user', 'subtask')) : null,
            ]);
        }

        return Inertia::render('TimeTracking/RunningTimer', [
            'timer' => $timer ? new \App\Http\Resources\TimeEntryResource($timer->load('user', 'subtask')) : null,
        ]);
    }

    /**
     * Update time entry.
     */
    public function update(UpdateTimeEntryRequest $request, TimeEntry $entry): RedirectResponse
    {
        if (! $this->accessService->canManageTimeEntry($request->user(), $entry)) {
            return redirect()->back()->withErrors(['error' => 'You are not authorized to update this time entry.']);
        }

        try {
            $updatedEntry = $this->timeTrackingService->updateEntry($entry, $request->validated(), $request->user());

            return redirect()->back()->with([
                'success' => 'Time entry updated successfully.',
                'timeEntry' => new \App\Http\Resources\TimeEntryResource($updatedEntry->load('user')),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update time entry: '.$e->getMessage()]);
        }
    }

    /**
     * Get workspace time report.
     */
    public function workspaceReport(Request $request, Workspace $workspace): Response
    {
        abort_unless($this->accessService->canViewAnalytics($request->user(), $workspace), 403);
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $report = $this->timeTrackingService->getWorkspaceTimeReport(
            $workspace,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return Inertia::render('TimeTracking/WorkspaceReport', [
            'workspace' => $workspace,
            'report' => $report,
        ]);
    }
}
