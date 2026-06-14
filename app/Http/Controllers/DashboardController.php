<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\DashboardService;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspaceService,
        protected DashboardService $dashboardService,
        protected AccessService $accessService,
    ) {}

    /**
     * Display the main dashboard.
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        $workspaces = $this->workspaceService->getWorkspacesForUser($user);

        if ($workspaces->isEmpty()) {
            return redirect()->route('workspaces.index');
        }

        $activeWorkspaceId = session('active_workspace_id', $workspaces->first()?->id);
        $activeWorkspace = $workspaces->firstWhere('id', $activeWorkspaceId) ?? $workspaces->first();

        $dashboardData = $this->dashboardService->getDashboardData($user, $activeWorkspace);

        return Inertia::render('Dashboard', [
            'workspaces' => $workspaces,
            'activeWorkspace' => $activeWorkspace,
            'mySubtasks' => $dashboardData['mySubtasks'],
            'overdueSubtasks' => $dashboardData['overdueSubtasks'],
            'runningTimer' => $dashboardData['runningTimer'],
            'timeStats' => $dashboardData['timeStats'],
            'recentActivity' => $dashboardData['recentActivity'],
        ]);
    }

    /**
     * Switch active workspace.
     */
    public function switchWorkspace(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($this->accessService->canViewWorkspace($request->user(), $workspace), 403);

        session(['active_workspace_id' => $workspace->id]);

        return redirect()->route('dashboard');
    }

    public function markNotificationsRead(Request $request): RedirectResponse
    {
        $request->user()->markNotificationsRead();

        return back();
    }
}
