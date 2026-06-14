<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\WorkspaceAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceAnalyticsController extends Controller
{
    public function __construct(
        protected WorkspaceAnalyticsService $analyticsService,
        protected AccessService $accessService,
    ) {}

    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($this->accessService->canViewAnalytics($request->user(), $workspace), 403);
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $canManage = $this->accessService->canManageWorkspace($request->user(), $workspace);

        $membersPayload = $canManage
            ? $this->analyticsService->getMembersPayload($workspace)
            : ['members' => collect(), 'spaces' => collect()];

        return Inertia::render('Workspaces/Analytics', [
            'workspace' => $workspace,
            'analytics' => $this->analyticsService->getOverview(
                $workspace,
                $validated['start_date'] ?? null,
                $validated['end_date'] ?? null,
            ),
            'filters' => $validated,
            'members' => $membersPayload['members'],
            'spaces' => $membersPayload['spaces'],
            'canManage' => $canManage,
        ]);
    }

    public function export(Request $request, Workspace $workspace): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($this->accessService->canViewAnalytics($request->user(), $workspace), 403);
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $rows = $this->analyticsService->getCsvRows(
            $workspace,
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null,
        );

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['metric', 'value']);
            foreach ($rows as $row) {
                fputcsv($output, [$row['metric'], $row['value']]);
            }
            fclose($output);
        }, 'workspace-analytics.csv');
    }
}
