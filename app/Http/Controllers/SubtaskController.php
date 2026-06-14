<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubtaskRequest;
use App\Http\Requests\UpdateSubtaskRequest;
use App\Http\Resources\SubtaskResource;
use App\Models\Label;
use App\Models\Project;
use App\Models\Space;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\SubtaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubtaskController extends Controller
{
    public function __construct(
        protected SubtaskService $subtaskService,
        protected AccessService $accessService,
    ) {}

    /**
     * Store a newly created subtask.
     */
    public function store(StoreSubtaskRequest $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $project), 403);
        try {
            $subtask = $this->subtaskService->create(
                $request->validated(),
                $task,
                $request->user()
            );

            return redirect()->back()->with([
                'success' => 'Subtask created successfully.',
                'subtask' => new SubtaskResource($subtask->load(['status', 'assignees', 'labels'])),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create subtask: '.$e->getMessage()]);
        }
    }

    /**
     * Update the specified subtask.
     */
    public function update(UpdateSubtaskRequest $request, Workspace $workspace, Space $space, Project $project, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless((int) $subtask->task_id === (int) $task->id, 404);
        abort_unless($this->accessService->canEditTasks($request->user(), $project), 403);
        try {
            $this->subtaskService->update($subtask, $request->validated(), $request->user());

            return redirect()->back()->with('success', 'Subtask updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified subtask.
     */
    public function destroy(Request $request, Workspace $workspace, Space $space, Project $project, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless((int) $subtask->task_id === (int) $task->id, 404);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $project), 403);
        try {
            $this->subtaskService->delete($subtask, $request->user());

            return redirect()->back()->with('success', 'Subtask deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Duplicate the specified subtask.
     */
    public function duplicate(Request $request, Workspace $workspace, Space $space, Project $project, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless((int) $subtask->task_id === (int) $task->id, 404);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $project), 403);
        try {
            $this->subtaskService->duplicate($subtask, $request->user());

            return redirect()->back()->with('success', 'Subtask duplicated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to duplicate subtask: '.$e->getMessage()]);
        }
    }

    /**
     * Mark subtask as completed.
     */
    public function complete(Request $request, Workspace $workspace, Space $space, Project $project, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless((int) $subtask->task_id === (int) $task->id, 404);
        abort_unless($this->accessService->canEditTasks($request->user(), $project), 403);
        $validated = $request->validate([
            'target_status_id' => [
                'nullable',
                Rule::exists('statuses', 'id')->where(function ($query) use ($space) {
                    $query->where('space_id', $space->id)
                        ->whereIn('applies_to', ['subtasks', 'both']);
                }),
            ],
        ]);

        try {
            $this->subtaskService->complete($subtask, $request->user(), $validated['target_status_id'] ?? null);

            return redirect()->back()->with('success', 'Subtask completed.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }
    }

    /**
     * Reopen a completed subtask.
     */
    public function reopen(Request $request, Workspace $workspace, Space $space, Project $project, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless((int) $subtask->task_id === (int) $task->id, 404);
        abort_unless($this->accessService->canEditTasks($request->user(), $project), 403);

        $this->subtaskService->reopen($subtask, $request->user());

        return redirect()->back()->with('success', 'Subtask reopened.');
    }

    /**
     * Reorder subtasks.
     */
    public function reorder(Request $request, Workspace $workspace, Space $space, Project $project, Task $task): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $project), 403);
        $request->validate([
            'subtask_ids' => ['required', 'array'],
            'subtask_ids.*' => [
                'integer',
                Rule::exists('subtasks', 'id')->where(fn ($query) => $query->where('task_id', $task->id)),
            ],
        ]);

        try {
            $this->subtaskService->reorder($task, $request->subtask_ids);

            return redirect()->back()->with('success', 'Subtasks reordered successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reorder subtasks: '.$e->getMessage()]);
        }
    }

    /**
     * Add label to subtask.
     */
    public function addLabel(Request $request, Workspace $workspace, Space $space, Project $project, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless((int) $subtask->task_id === (int) $task->id, 404);
        abort_unless($this->accessService->canManageLabels($request->user(), $project), 403);
        $validated = $request->validate([
            'label_id' => [
                'required',
                Rule::exists('labels', 'id')->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
        ]);

        try {
            $label = Label::where('workspace_id', $workspace->id)->findOrFail($validated['label_id']);
            $this->subtaskService->addLabel($subtask, $label, $request->user());

            return redirect()->back()->with('success', 'Label added successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to add label: '.$e->getMessage()]);
        }
    }

    /**
     * Remove label from subtask.
     */
    public function removeLabel(Request $request, Workspace $workspace, Space $space, Project $project, Task $task, Subtask $subtask): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless((int) $task->project_id === (int) $project->id, 404);
        abort_unless((int) $subtask->task_id === (int) $task->id, 404);
        abort_unless($this->accessService->canManageLabels($request->user(), $project), 403);
        $validated = $request->validate([
            'label_id' => [
                'required',
                Rule::exists('labels', 'id')->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
        ]);

        try {
            $label = Label::where('workspace_id', $workspace->id)->findOrFail($validated['label_id']);
            $this->subtaskService->removeLabel($subtask, $label, $request->user());

            return redirect()->back()->with('success', 'Label removed successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to remove label: '.$e->getMessage()]);
        }
    }
}
