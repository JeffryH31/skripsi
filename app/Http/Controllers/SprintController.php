<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSprintRequest;
use App\Http\Requests\UpdateSprintRequest;
use App\Models\Project;
use App\Models\Space;
use App\Models\Sprint;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\SprintService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SprintController extends Controller
{
    public function __construct(
        protected SprintService $sprintService,
        protected AccessService $accessService,
    ) {}

    /**
     * Display a listing of sprints.
     */
    public function index(Request $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless($this->accessService->canViewSpace($request->user(), $space), 403);

        $projects = $space->projects()
            ->select('id', 'name', 'space_id')
            ->orderBy('position')
            ->get();

        $selectedList = null;
        if ($projects->isNotEmpty()) {
            $requestedListId = (int) $request->integer('list_id');
            $selectedList = $requestedListId
                ? $projects->firstWhere('id', $requestedListId)
                : $projects->first();
        }

        if (! $selectedList) {
            return redirect()
                ->route('spaces.show', [$workspace, $space])
                ->with('error', 'No project available for sprint view.');
        }

        return redirect()->route('projects.show', [
            'workspace' => $workspace,
            'space' => $space,
            'list' => $selectedList,
            'view' => 'sprint',
        ]);
    }

    /**
     * Display the sprint board.
     */
    public function show(Request $request, Workspace $workspace, Space $space, Sprint $sprint): Response
    {
        abort_unless($this->accessService->canViewSpace($request->user(), $space), 403);
        $this->ensureSprintBelongsToSpace($sprint, $space);

        $list = $sprint->project;
        if (! $list) {
            $list = Project::where('space_id', $space->id)->orderBy('position')->first();
        }

        $sprint->load([
            'subtasks.status',
            'subtasks.assignees',
            'subtasks.labels',
            'subtasks.task',
        ]);

        // Get backlog subtasks (subtasks without sprint in this project)
        $backlogSubtasks = $list ? $this->sprintService->getBacklogSubtasks($list) : collect();

        $statistics = $this->sprintService->getSprintStatistics($sprint);
        $burndown = $this->sprintService->getBurndownData($sprint);

        return Inertia::render('Sprints/Show', [
            'workspace' => $workspace,
            'space' => $space,
            'list' => $list,
            'sprint' => $sprint,
            'backlogSubtasks' => $backlogSubtasks,
            'statistics' => $statistics,
            'burndown' => $burndown,
            'statuses' => $space->statuses,
            'labels' => $workspace->labels,
            'members' => $workspace->members,
            'canManageTaskStructure' => $list ? $this->accessService->canManageTaskStructure($request->user(), $list) : false,
            'canOperateTasks' => $list ? $this->accessService->canOperateTasks($request->user(), $list) : false,
        ]);
    }

    /**
     * Store a newly created sprint.
     */
    public function store(StoreSprintRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        $validated = $request->validated();
        $list = Project::where('space_id', $space->id)->findOrFail((int) $validated['list_id']);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $list), 403);

        $this->sprintService->createSprint($list, $validated);

        return redirect()->back()->with('success', 'Sprint created successfully!');
    }

    /**
     * Update the specified sprint.
     */
    public function update(UpdateSprintRequest $request, Workspace $workspace, Space $space, Sprint $sprint): RedirectResponse
    {
        $this->ensureSprintBelongsToSpace($sprint, $space);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $sprint->project), 403);

        $validated = $request->validated();
        if (isset($validated['list_id'])) {
            Project::where('space_id', $space->id)->findOrFail((int) $validated['list_id']);
        }

        $this->sprintService->updateSprint($sprint, $validated);

        return redirect()->back()->with('success', 'Sprint updated successfully!');
    }

    /**
     * Start sprint.
     */
    public function start(Request $request, Workspace $workspace, Space $space, Sprint $sprint): RedirectResponse
    {
        $this->ensureSprintBelongsToSpace($sprint, $space);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $sprint->project), 403);

        $this->sprintService->startSprint($sprint);

        return redirect()->back()->with('success', 'Sprint started!');
    }

    /**
     * Complete sprint.
     */
    public function complete(Request $request, Workspace $workspace, Space $space, Sprint $sprint): RedirectResponse
    {
        $this->ensureSprintBelongsToSpace($sprint, $space);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $sprint->project), 403);

        $this->sprintService->completeSprint($sprint);

        return redirect()->back()->with('success', 'Sprint completed!');
    }

    /**
     * Add subtask to sprint.
     */
    public function addTask(Request $request, Workspace $workspace, Space $space, Sprint $sprint): RedirectResponse
    {
        $this->ensureSprintBelongsToSpace($sprint, $space);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $sprint->project), 403);

        $validated = $request->validate([
            'subtask_id' => ['required', 'exists:subtasks,id'],
        ]);

        $this->sprintService->addSubtaskToSprint($sprint, $validated['subtask_id']);

        return redirect()->back()->with('success', 'Subtask added to sprint!');
    }

    /**
     * Remove subtask from sprint.
     */
    public function removeTask(Request $request, Workspace $workspace, Space $space, Sprint $sprint): RedirectResponse
    {
        $this->ensureSprintBelongsToSpace($sprint, $space);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $sprint->project), 403);

        $validated = $request->validate([
            'subtask_id' => ['required', 'exists:subtasks,id'],
        ]);

        $this->sprintService->removeSubtaskFromSprint($sprint, $validated['subtask_id']);

        return redirect()->back()->with('success', 'Subtask removed from sprint!');
    }

    /**
     * Remove the specified sprint.
     */
    public function destroy(Request $request, Workspace $workspace, Space $space, Sprint $sprint): RedirectResponse
    {
        $this->ensureSprintBelongsToSpace($sprint, $space);
        abort_unless($this->accessService->canManageTaskStructure($request->user(), $sprint->project), 403);

        $this->sprintService->deleteSprint($sprint);

        return redirect()->back()->with('success', 'Sprint deleted successfully!');
    }

    private function ensureSprintBelongsToSpace(Sprint $sprint, Space $space): void
    {
        if ($sprint->project_id) {
            $belongs = Project::where('id', $sprint->project_id)
                ->where('space_id', $space->id)
                ->exists();
            abort_unless($belongs, 404);

            return;
        }

        abort_unless((int) $sprint->space_id === (int) $space->id, 404);
    }
}
