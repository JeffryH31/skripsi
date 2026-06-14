<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChecklistItemRequest;
use App\Http\Requests\UpdateChecklistItemRequest;
use App\Http\Resources\ChecklistItemResource;
use App\Models\ChecklistItem;
use App\Models\Project;
use App\Models\Space;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\ChecklistItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ChecklistItemController extends Controller
{
    public function __construct(
        protected ChecklistItemService $checklistService,
        protected AccessService $accessService,
    ) {}

    /**
     * Store a new checklist item under a subtask.
     */
    public function store(StoreChecklistItemRequest $request,
        Workspace $workspace,
        Space $space,
        Project $project,
        Task $task,
        Subtask $subtask, ): RedirectResponse
    {
        $this->authorizeScope($workspace, $space, $project, $task, $subtask);
        abort_unless($this->accessService->canEditTasks($request->user(), $project), 403);

        try {
            $item = $this->checklistService->create(
                $request->validated(),
                $subtask,
                $request->user(),
            );

            return redirect()->back()->with([
                'success' => 'Checklist item added.',
                'checklist_item' => new ChecklistItemResource($item),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Update checklist item name.
     */
    public function update(UpdateChecklistItemRequest $request,
        Workspace $workspace,
        Space $space,
        Project $project,
        Task $task,
        Subtask $subtask,
        ChecklistItem $checklistItem, ): RedirectResponse
    {
        $this->authorizeScope($workspace, $space, $project, $task, $subtask);
        abort_unless((int) $checklistItem->subtask_id === (int) $subtask->id, 404);
        abort_unless($this->accessService->canEditTasks($request->user(), $project), 403);

        try {
            $this->checklistService->update($checklistItem, $request->validated());

            return redirect()->back()->with('success', 'Checklist item updated.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Delete a checklist item (children cascade via FK).
     */
    public function destroy(Request $request,
        Workspace $workspace,
        Space $space,
        Project $project,
        Task $task,
        Subtask $subtask,
        ChecklistItem $checklistItem, ): RedirectResponse
    {
        $this->authorizeScope($workspace, $space, $project, $task, $subtask);
        abort_unless((int) $checklistItem->subtask_id === (int) $subtask->id, 404);
        abort_unless($this->accessService->canEditTasks($request->user(), $project), 403);

        try {
            $this->checklistService->delete($checklistItem);

            return redirect()->back()->with('success', 'Checklist item deleted.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Toggle the checked state of a checklist item.
     */
    public function toggle(Request $request,
        Workspace $workspace,
        Space $space,
        Project $project,
        Task $task,
        Subtask $subtask,
        ChecklistItem $checklistItem, ): RedirectResponse
    {
        $this->authorizeScope($workspace, $space, $project, $task, $subtask);
        abort_unless((int) $checklistItem->subtask_id === (int) $subtask->id, 404);
        abort_unless($this->accessService->canEditTasks($request->user(), $project), 403);

        $cascade = (bool) $request->input('cascade', false);

        try {
            $this->checklistService->toggle($checklistItem, $cascade);

            return redirect()->back()->with('success', 'Checklist item toggled.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Reorder checklist items within the same parent.
     */
    public function reorder(Request $request,
        Workspace $workspace,
        Space $space,
        Project $project,
        Task $task,
        Subtask $subtask, ): RedirectResponse
    {
        $this->authorizeScope($workspace, $space, $project, $task, $subtask);
        abort_unless($this->accessService->canEditTasks($request->user(), $project), 403);

        $request->validate([
            'item_ids' => ['required', 'array'],
            'item_ids.*' => ['integer', 'exists:checklist_items,id'],
            'parent_id' => ['nullable', 'exists:checklist_items,id'],
        ]);

        try {
            $this->checklistService->reorder(
                $subtask,
                $request->item_ids,
                $request->input('parent_id'),
            );

            return redirect()->back()->with('success', 'Checklist reordered.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    //  Private
    private function authorizeScope(
        Workspace $workspace,
        Space $space,
        Project $project,
        Task $task,
        Subtask $subtask,
    ): void {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless((int) $subtask->task_id === (int) $task->id, 404);
    }
}
