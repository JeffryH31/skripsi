<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLabelRequest;
use App\Http\Requests\UpdateLabelRequest;
use App\Models\Label;
use App\Models\Workspace;
use App\Services\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function __construct(
        protected AccessService $accessService,
    ) {}

    public function store(StoreLabelRequest $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);

        $validated = $request->validated();

        $workspace->labels()->create([
            'name' => trim($validated['name']),
            'color' => strtoupper($validated['color']),
        ]);

        return redirect()->back()->with('success', 'Label created successfully.');
    }

    public function update(UpdateLabelRequest $request, Workspace $workspace, Label $label): RedirectResponse
    {
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);
        abort_unless((int) $label->workspace_id === (int) $workspace->id, 404);

        $validated = $request->validated();

        $label->update([
            'name' => trim($validated['name']),
            'color' => strtoupper($validated['color']),
        ]);

        return redirect()->back()->with('success', 'Label updated successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, Label $label): RedirectResponse
    {
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);
        abort_unless((int) $label->workspace_id === (int) $workspace->id, 404);

        $label->delete();

        return redirect()->back()->with('success', 'Label deleted successfully.');
    }
}
