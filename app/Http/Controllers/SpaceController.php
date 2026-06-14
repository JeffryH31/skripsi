<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddMemberRequest;
use App\Http\Requests\RemoveMemberRequest;
use App\Http\Requests\ReorderRequest;
use App\Http\Requests\StoreSpaceRequest;
use App\Http\Requests\StoreStatusRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Http\Requests\UpdateSpaceRequest;
use App\Http\Requests\UpdateStatusRequest;
use App\Models\Space;
use App\Models\Status;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\SpaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SpaceController extends Controller
{
    public function __construct(
        protected SpaceService $spaceService,
        protected AccessService $accessService,
    ) {}

    /**
     * Store a newly created space.
     */
    public function store(StoreSpaceRequest $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);
        try {
            $space = $this->spaceService->create(
                $request->validated(),
                $workspace,
                $request->user()
            );

            return redirect()->back()->with([
                'success' => 'Space created successfully.',
                'space' => $space,
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create space: '.$e->getMessage()]);
        }
    }

    /**
     * Display the specified space.
     */
    public function show(Request $request, Workspace $workspace, Space $space): Response
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canViewSpace($request->user(), $space), 403);
        $space = $this->spaceService->getWithHierarchy($space, $request->user());
        $statistics = $this->spaceService->getStatistics($space);

        // Annotate is_starred for the current user.
        $space->is_starred = $space->starredBy()->where('user_id', $request->user()->id)->exists();

        // Products (Projects) grouped by status for kanban
        $projectsByStatus = $this->spaceService->getProjectsByStatus($space, $request->user());

        $user = $request->user();
        $isWsAdmin = $this->accessService->canManageWorkspace($user, $workspace);
        $listFilter = function ($q) use ($user, $isWsAdmin) {
            return $isWsAdmin ? $q : $q->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id));
        };

        $workspace->load([
            'spaces' => function ($q) use ($user, $isWsAdmin, $listFilter) {
                if (! $isWsAdmin) {
                    $q->whereHas('members', fn ($mq) => $mq->where('user_id', $user->id));
                }
                $q->with([
                    'folders.projects' => $listFilter,
                    'projectsWithoutFolder' => $listFilter,
                ])->orderBy('position');
            },
            'members',
            'labels',
        ]);

        return Inertia::render('Spaces/Show', [
            'workspace' => $workspace,
            'space' => $space,
            'statistics' => $statistics,
            'productsByStatus' => $projectsByStatus,
            'canManageSpace' => $this->accessService->canManageSpace($request->user(), $space),
            'canManageWorkspace' => $this->accessService->canManageWorkspace($request->user(), $workspace),
        ]);
    }

    /**
     * Display space access settings.
     */
    public function settings(Request $request, Workspace $workspace, Space $space): Response
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canViewSpace($request->user(), $space), 403);

        $workspace->load('members');
        $space->load(['members', 'projects']);

        $spaceMemberIds = $space->members->pluck('id');

        $mapUser = fn ($member, ?string $role = null) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'initials' => $member->initials,
            'avatar_color' => $member->avatar_color,
            'profile_photo_url' => $member->profile_photo_url,
            'role' => $role,
        ];

        return Inertia::render('Spaces/Settings', [
            'workspace' => $workspace,
            'space' => [
                'id' => $space->id,
                'name' => $space->name,
            ],
            'projects' => $space->projects
                ->map(fn ($list) => [
                    'id' => $list->id,
                    'name' => $list->name,
                    'is_archived' => (bool) $list->is_archived,
                ])
                ->values(),
            'members' => $space->members
                ->map(fn ($member) => $mapUser($member, $member->pivot?->role))
                ->values(),
            'availableUsers' => $workspace->members
                ->filter(fn ($member) => ! $spaceMemberIds->contains($member->id))
                ->map(fn ($member) => $mapUser($member))
                ->values(),
            'canManageMembers' => $this->accessService->canManageSpace($request->user(), $space),
        ]);
    }

    /**
     * Update the specified space.
     */
    public function update(UpdateSpaceRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);
        try {
            $updatedSpace = $this->spaceService->update($space, $request->validated(), $request->user());

            return redirect()->back()->with([
                'success' => 'Space updated successfully.',
                'space' => $updatedSpace,
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update space: '.$e->getMessage()]);
        }
    }

    /**
     * Remove the specified space.
     */
    public function destroy(Request $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);
        try {
            $this->spaceService->delete($space, $request->user());

            return redirect()
                ->route('workspaces.show', $workspace)
                ->with('success', 'Space deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete space: '.$e->getMessage()]);
        }
    }

    /**
     * Toggle starred status.
     */
    public function toggleStar(Request $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canViewSpace($request->user(), $space), 403);

        try {
            $this->spaceService->toggleStar($space, $request->user());

            return redirect()->back()->with('success', 'Space starred status updated.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update space: '.$e->getMessage()]);
        }
    }

    /**
     * Reorder spaces.
     */
    public function reorder(ReorderRequest $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);

        try {
            $this->spaceService->reorder($workspace, $request->order);

            return redirect()->back()->with('success', 'Spaces reordered successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reorder spaces: '.$e->getMessage()]);
        }
    }

    /**
     * Add status to space.
     */
    public function addStatus(StoreStatusRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canManageSpace($request->user(), $space), 403);

        try {
            $status = $this->spaceService->addStatus($space, $request->validated());

            return redirect()->back()->with([
                'success' => 'Status added successfully.',
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to add status: '.$e->getMessage()]);
        }
    }

    /**
     * Update status.
     */
    public function updateStatus(UpdateStatusRequest $request, Workspace $workspace, Space $space, Status $status): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $status->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageSpace($request->user(), $space), 403);

        try {
            $this->spaceService->updateStatus($status, $request->validated());

            return redirect()->back()->with('success', 'Status updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update status: '.$e->getMessage()]);
        }
    }

    /**
     * Delete status.
     */
    public function deleteStatus(Request $request, Workspace $workspace, Space $space, Status $status): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless((int) $status->space_id === (int) $space->id, 404);
        abort_unless($this->accessService->canManageSpace($request->user(), $space), 403);

        $validated = $request->validate([
            'move_to_status_id' => [
                'nullable',
                Rule::exists('statuses', 'id')->where(fn ($query) => $query->where('space_id', $space->id)),
            ],
        ]);

        try {
            $this->spaceService->deleteStatus($status, $validated['move_to_status_id'] ?? null);

            return redirect()->back()->with('success', 'Status deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete status: '.$e->getMessage()]);
        }
    }

    /**
     * Reorder statuses.
     */
    public function reorderStatuses(ReorderRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canManageSpace($request->user(), $space), 403);

        try {
            $this->spaceService->reorderStatuses($space, $request->order);

            return redirect()->back()->with('success', 'Statuses reordered successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to reorder statuses: '.$e->getMessage()]);
        }
    }

    public function addMember(AddMemberRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canManageSpace($request->user(), $space), 403);

        $validated = $request->validated();

        $user = User::findOrFail($validated['user_id']);
        if (! $workspace->isMember($user)) {
            return redirect()->back()->withErrors(['error' => 'User must be a workspace member first.']);
        }

        $this->spaceService->addMember($space, $user, $validated['role'], $request->user());

        return redirect()->back()->with('success', 'Space member added successfully.');
    }

    public function updateMemberRole(UpdateMemberRoleRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canManageSpace($request->user(), $space), 403);

        $validated = $request->validated();

        $user = User::findOrFail($validated['user_id']);
        $this->spaceService->updateMemberRole($space, $user, $validated['role'], $request->user());

        return redirect()->back()->with('success', 'Space member role updated successfully.');
    }

    public function removeMember(RemoveMemberRequest $request, Workspace $workspace, Space $space): RedirectResponse
    {
        abort_unless((int) $space->workspace_id === (int) $workspace->id, 404);
        abort_unless($this->accessService->canManageSpace($request->user(), $space), 403);

        $validated = $request->validated();

        $user = User::findOrFail($validated['user_id']);
        $isSpaceOwner = $space->members()->where('user_id', $user->id)->wherePivot('role', AccessService::WORKSPACE_OWNER)->exists();
        if ($isSpaceOwner) {
            return redirect()->back()->withErrors(['error' => 'Space owner cannot be removed.']);
        }

        $this->spaceService->removeMember($space, $user, $request->user());

        return redirect()->back()->with('success', 'Space member removed successfully.');
    }
}
