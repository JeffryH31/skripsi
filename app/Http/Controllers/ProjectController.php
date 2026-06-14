<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddMemberRequest;
use App\Http\Requests\RemoveMemberRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Folder;
use App\Models\Project;
use App\Models\Space;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function __construct(
        protected ProjectService $projectService,
        protected AccessService $accessService,
    ) {}

    /**
     * Store a newly created list.
     */
    public function store(StoreProjectRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);
        $folder = $request->folder_id ? Folder::find($request->folder_id) : null;

        if ($folder && (int) $folder->space_id !== (int) $space->id) {
            return back()->withErrors(['error' => 'Folder must belong to the same space.']);
        }

        $project = $this->projectService->create(
            $request->validated(),
            $space,
            $request->user(),
            $folder
        );

        return redirect()
            ->route('projects.show', [$workspace, $space, $project])
            ->with('success', 'List created successfully.');
    }

    /**
     * Display the specified list (Board view).
     */
    public function show(Request $request, Workspace $workspace, Space $space, Project $project): Response
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canViewProject($request->user(), $project), 403);
        $requestedTaskId = (int) $request->integer('task_id');
        $parentTask = null;

        if ($requestedTaskId > 0) {
            // Accept task_id only if the task belongs to the currently opened list.
            $parentTask = $project->tasks()->whereKey($requestedTaskId)->first();
        }

        $tasksByStatus = $this->projectService->getWithTasksByStatus($project, $parentTask?->id);

        $user = $request->user();
        $isWsAdmin = $this->accessService->canManageWorkspace($user, $workspace);
        $projectFilter = function ($q) use ($user, $isWsAdmin) {
            return $isWsAdmin ? $q : $q->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id));
        };

        $workspace->load([
            'spaces' => function ($q) use ($user, $isWsAdmin, $projectFilter) {
                if (! $isWsAdmin) {
                    $q->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id));
                }
                $q->with([
                    'folders.projects' => $projectFilter,
                    'projectsWithoutFolder' => $projectFilter,
                ])->orderBy('position');
            },
            'members',
            'labels',
        ]);

        // Filter statuses based on whether viewing subtasks or tasks
        $statusesQuery = $space->statuses()->orderBy('position');
        if ($parentTask) {
            $statusesQuery->forSubtasks(); // Only subtask-applicable statuses
        } else {
            $statusesQuery->forTasks(); // Only task-applicable statuses
        }

        return Inertia::render('Projects/Show', [
            'workspace' => $workspace,
            'space' => $space,
            'list' => $project,
            'tasksByStatus' => $tasksByStatus,
            'statuses' => $statusesQuery->get(),
            'sprints' => $project->sprints()->withCount('subtasks')->orderBy('position')->get(),
            'parentTask' => $parentTask,
            'projectMembers' => $project->members()->get(['users.id', 'users.name', 'users.email']),
            'canManageProject' => $this->accessService->canManageProject($request->user(), $project),
            'canDeleteProject' => $this->accessService->canDeleteProject($request->user(), $project),
            'canManageTaskStructure' => $this->accessService->canManageTaskStructure($request->user(), $project),
            'canOperateTasks' => $this->accessService->canOperateTasks($request->user(), $project),
            'canManageSpace' => $this->accessService->canManageSpace($request->user(), $space),
        ]);
    }

    /**
     * Display project access settings.
     */
    public function settings(Request $request, Workspace $workspace, Space $space, Project $project): Response
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canViewProject($request->user(), $project), 403);

        $workspace->load('members');
        $project->load('members');

        $projectMemberIds = $project->members->pluck('id');

        $mapUser = fn ($member, ?string $role = null) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'initials' => $member->initials,
            'avatar_color' => $member->avatar_color,
            'profile_photo_url' => $member->profile_photo_url,
            'role' => $role,
        ];

        return Inertia::render('Projects/Settings', [
            'workspace' => $workspace,
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
            ],
            'list' => [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'is_archived' => (bool) $project->is_archived,
            ],
            'members' => $project->members
                ->map(fn ($member) => $mapUser($member, $member->pivot?->role))
                ->values(),
            'availableUsers' => $workspace->members
                ->filter(fn ($member) => ! $projectMemberIds->contains($member->id))
                ->map(fn ($member) => $mapUser($member))
                ->values(),
            'canManageMembers' => $this->accessService->canManageProjectMembers($request->user(), $project),
        ]);
    }

    /**
     * Update the specified list.
     */
    public function update(UpdateProjectRequest $request, Workspace $workspace, Space $space, Project $project): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProject($request->user(), $project), 403);
        $this->projectService->update($project, $request->validated(), $request->user());

        return back()->with('success', 'List updated successfully.');
    }

    /**
     * Remove the specified list.
     */
    public function destroy(Request $request, Workspace $workspace, Space $space, Project $project): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canDeleteProject($request->user(), $project), 403);
        $this->projectService->delete($project, $request->user());

        return redirect()
            ->route('spaces.show', [$workspace, $space])
            ->with('success', 'List deleted successfully.');
    }

    /**
     * Move list to folder.
     */
    public function moveToFolder(Request $request, Workspace $workspace, Space $space, Project $project): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProject($request->user(), $project), 403);
        $request->validate([
            'folder_id' => [
                'nullable',
                Rule::exists('folders', 'id')->where(fn ($query) => $query->where('space_id', $space->id)),
            ],
        ]);

        $folder = $request->folder_id ? Folder::where('space_id', $space->id)->find($request->folder_id) : null;

        $this->projectService->moveToFolder($project, $folder, $request->user());

        return back()->with('success', 'List moved successfully.');
    }

    /**
     * Reorder lists.
     */
    public function reorder(ReorderRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);
        $this->projectService->reorder($space, $request->order);

        return back();
    }

    /**
     * Duplicate the list.
     */
    public function duplicate(Request $request, Workspace $workspace, Space $space, Project $project): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProject($request->user(), $project), 403);
        $newProject = $this->projectService->duplicate($project, $request->user());

        return redirect()
            ->route('projects.show', [$workspace, $space, $newProject])
            ->with('success', 'List duplicated successfully.');
    }

    /**
     * Change project status (for kanban board drag-and-drop).
     */
    public function changeStatus(Request $request, Workspace $workspace, Space $space, Project $project): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProject($request->user(), $project), 403);
        $request->validate([
            'status_id' => [
                'required',
                Rule::exists('statuses', 'id')->where(fn ($query) => $query->where('space_id', $space->id)),
            ],
        ]);

        $project->update(['status_id' => $request->status_id]);

        return back()->with('success', 'Project status updated.');
    }

    public function addMember(AddMemberRequest $request, Workspace $workspace, Space $space, Project $project): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProjectMembers($request->user(), $project), 403);

        $validated = $request->validated();

        $user = User::findOrFail($validated['user_id']);
        $this->projectService->addMember($project, $user, $validated['role'], $request->user());

        return back()->with('success', 'Project member added successfully.');
    }

    public function updateMemberRole(UpdateMemberRoleRequest $request, Workspace $workspace, Space $space, Project $project): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProjectMembers($request->user(), $project), 403);

        $validated = $request->validated();

        $user = User::findOrFail($validated['user_id']);
        $this->projectService->updateMemberRole($project, $user, $validated['role'], $request->user());

        return back()->with('success', 'Project member role updated successfully.');
    }

    public function removeMember(RemoveMemberRequest $request, Workspace $workspace, Space $space, Project $project): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $project->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageProjectMembers($request->user(), $project), 403);

        $validated = $request->validated();

        $user = User::findOrFail($validated['user_id']);
        $this->projectService->removeMember($project, $user, $request->user());

        return back()->with('success', 'Project member removed successfully.');
    }
}
