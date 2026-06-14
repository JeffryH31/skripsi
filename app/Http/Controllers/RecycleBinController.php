<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\RecycleBinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecycleBinController extends Controller
{
    public function __construct(
        protected AccessService $accessService,
        protected RecycleBinService $recycleBinService,
    ) {}

    public function index(Request $request, Workspace $workspace): Response
    {
        abort_unless($this->accessService->canViewWorkspace($request->user(), $workspace), 403);

        return Inertia::render('Workspaces/RecycleBin', [
            'workspace' => $workspace,
            'trash' => $this->recycleBinService->getTrashedItems($workspace),
            'canRestore' => $this->accessService->canManageWorkspace($request->user(), $workspace),
        ]);
    }

    public function restore(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);

        $validated = $request->validate([
            'type' => ['required', 'in:list,task,subtask,time_entry'],
            'id' => ['required', 'integer'],
        ]);

        try {
            $this->recycleBinService->restoreItem($workspace, $validated['type'], (int) $validated['id']);

            return back()->with('success', 'Item restored successfully.');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'Failed to restore item.']);
        }
    }
}
