<?php

namespace App\Http\Controllers;

use App\Http\Requests\CalendarFilterRequest;
use App\Models\Subtask;
use App\Models\Workspace;
use App\Services\AccessService;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __construct(
        protected AccessService $accessService
    ) {}

    /**
     * Display the calendar view.
     */
    public function index(CalendarFilterRequest $request, Workspace $workspace): Response
    {
        abort_unless($this->accessService->canViewWorkspace($request->user(), $workspace), 403);
        $validated = $request->validated();

        $startDate = $validated['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $validated['end_date'] ?? now()->endOfMonth()->toDateString();
        $viewMode = $validated['view'] ?? 'month';

        $workspace->load([
            'spaces.statuses',
            'labels',
            'members',
        ]);

        $user = $request->user();
        $workspaceRole = $this->accessService->getWorkspaceRole($user, $workspace);

        $subtasks = Subtask::query()
            ->whereHas('task.project', function ($query) use ($workspace, $user, $workspaceRole) {
                $query->whereHas('space', function ($sq) use ($workspace, $user, $workspaceRole) {
                    $sq->where('workspace_id', $workspace->id);

                    // Non-admin users can only see spaces they are a member of
                    if (! in_array($workspaceRole, [AccessService::WORKSPACE_OWNER, AccessService::WORKSPACE_ADMIN], true)) {
                        $sq->whereHas('members', fn ($m) => $m->where('user_id', $user->id));
                    }
                });

                // Workspace admin can see all; others only see projects they belong to
                if (! in_array($workspaceRole, [AccessService::WORKSPACE_OWNER, AccessService::WORKSPACE_ADMIN], true)) {
                    $query->whereHas('members', fn ($m) => $m->where('user_id', $user->id));
                }
            })
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('due_date', [$startDate, $endDate])
                    ->orWhereBetween('start_date', [$startDate, $endDate]);
            })
            ->with([
                'status',
                'assignees',
                'labels',
                'activities' => fn ($q) => $q->with('user')->latest()->limit(50),
                'task.project.space',
            ])
            ->orderBy('due_date')
            ->get();

        return Inertia::render('Calendar/Index', [
            'workspace' => $workspace,
            'subtasks' => $subtasks,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'viewMode' => $viewMode,
        ]);
    }
}
