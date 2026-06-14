<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddMemberRequest;
use App\Http\Requests\RemoveMemberRequest;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateMemberRoleRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AccessService;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __construct(
        protected WorkspaceService $workspaceService,
        protected AccessService $accessService,
    ) {}

    /**
     * Display a listing of workspaces.
     */
    public function index(Request $request): Response
    {
        $workspaces = $this->workspaceService->getWorkspacesForUser($request->user());

        return Inertia::render('Workspaces/Index', [
            'workspaces' => $workspaces,
        ]);
    }

    /**
     * Store a newly created workspace.
     */
    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        abort_unless($this->accessService->canCreateWorkspace($request->user()), 403);

        $workspace = $this->workspaceService->create(
            $request->validated(),
            $request->user()
        );

        return back()->with('success', 'Workspace created successfully.');
    }

    /**
     * Display workspace settings.
     */
    public function settings(Request $request, Workspace $workspace): Response
    {
        abort_unless($this->accessService->canViewWorkspace($request->user(), $workspace), 403);

        $workspace->load([
            'members',
            'labels',
            'spaces' => fn ($query) => $query
                ->orderBy('position')
                ->with([
                    'members',
                    'projects' => fn ($listQuery) => $listQuery
                        ->orderBy('position')
                        ->with(['members']),
                ]),
        ]);

        $members = $workspace->members;

        // Return ALL users so the autocomplete can show everyone (members included —
        // selecting an existing member will just update their role, not duplicate them).
        $availableUsers = User::orderBy('name')->limit(200)->get();

        $projects = $workspace->spaces
            ->flatMap(function ($space) {
                return $space->projects->map(function ($list) use ($space) {
                    return [
                        'id' => $list->id,
                        'name' => $list->name,
                        'space' => [
                            'id' => $space->id,
                            'name' => $space->name,
                        ],
                        'is_archived' => (bool) $list->is_archived,
                        'members' => $list->members->map(function ($member) {
                            return [
                                'id' => $member->id,
                                'name' => $member->name,
                                'email' => $member->email,
                                'initials' => $member->initials,
                                'avatar_color' => $member->avatar_color,
                                'profile_photo_url' => $member->profile_photo_url,
                                'role' => $member->pivot?->role,
                            ];
                        })->values(),
                    ];
                });
            })
            ->values();

        $spaces = $workspace->spaces
            ->map(function ($space) {
                return [
                    'id' => $space->id,
                    'name' => $space->name,
                    'members' => $space->members->map(function ($member) {
                        return [
                            'id' => $member->id,
                            'name' => $member->name,
                            'email' => $member->email,
                            'initials' => $member->initials,
                            'avatar_color' => $member->avatar_color,
                            'profile_photo_url' => $member->profile_photo_url,
                            'role' => $member->pivot?->role,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return Inertia::render('Workspaces/Settings', [
            'workspace' => $workspace,
            'members' => $members,
            'availableUsers' => $availableUsers,
            'projects' => $projects,
            'spaces' => $spaces,
        ]);
    }

    /**
     * Display the specified workspace.
     */
    public function show(Request $request, Workspace $workspace): Response
    {
        abort_unless($this->accessService->canViewWorkspace($request->user(), $workspace), 403);

        $workspace->load([
            'spaces' => fn ($q) => $q->withCount(['projects', 'tasks'])
                ->orderBy('position'),
        ]);

        return Inertia::render('Workspaces/Show', [
            'workspace' => $workspace,
            'canManageWorkspace' => $this->accessService->canManageWorkspace($request->user(), $workspace),
        ]);
    }

    /**
     * Update the specified workspace.
     */
    public function update(UpdateWorkspaceRequest $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);

        try {
            $this->workspaceService->update(
                $workspace,
                $request->validated(),
                $request->user()
            );

            return redirect()->back()->with('success', 'Workspace updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update workspace: '.$e->getMessage()]);
        }
    }

    /**
     * Remove the specified workspace.
     */
    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        abort_unless($this->accessService->canDeleteWorkspace($request->user(), $workspace), 403);

        try {
            $this->workspaceService->delete($workspace, $request->user());

            return redirect()
                ->route('dashboard')
                ->with('success', 'Workspace deleted successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to delete workspace: '.$e->getMessage()]);
        }
    }

    /**
     * Add member to workspace.
     * Accepts either user_id (existing user) or email (find-or-create flow).
     */
    public function addMember(AddMemberRequest $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validated();

        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);

        try {
            // Resolve user: by ID if provided, otherwise by email
            if (! empty($validated['user_id'])) {
                $user = User::findOrFail($validated['user_id']);
            } else {
                $user = User::where('email', $validated['email'])->first();
                if (! $user) {
                    return redirect()->back()->withErrors([
                        'email' => 'No account found with that email. Use "Create User" to create a new account.',
                    ]);
                }
            }

            // Already a member? Just update role if one is supplied.
            $existingRole = $workspace->members()->where('user_id', $user->id)->value('role');
            if ($existingRole !== null) {
                $newRole = $validated['role'] ?? $existingRole;
                if ($newRole !== $existingRole) {
                    $this->workspaceService->updateMemberRole($workspace, $user, $newRole, $request->user());
                    return redirect()->back()->with('success', "{$user->name}'s role updated to {$newRole}.");
                }
                return redirect()->back()->with('info', "{$user->name} is already a member of this workspace.");
            }

            $this->workspaceService->addMember(
                $workspace,
                $user,
                $validated['role'] ?? AccessService::WORKSPACE_MEMBER,
                $request->user()
            );

            return redirect()->back()->with('success', 'Member added successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to add member: '.$e->getMessage()]);
        }
    }

    /**
     * Remove member from workspace.
     */
    public function removeMember(RemoveMemberRequest $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validated();

        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);

        try {
            $user = User::findOrFail($validated['user_id']);

            if ((int) $request->user()->id === (int) $user->id) {
                return redirect()->back()->withErrors(['error' => 'You cannot remove yourself from workspace.']);
            }

            $this->workspaceService->removeMember($workspace, $user, $request->user());

            return redirect()->back()->with('success', 'Member removed successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to remove member: '.$e->getMessage()]);
        }
    }

    /**
     * Update member role.
     */
    public function updateMemberRole(UpdateMemberRoleRequest $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validated();

        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);

        try {

            $user = User::findOrFail($validated['user_id']);

            $this->workspaceService->updateMemberRole(
                $workspace,
                $user,
                $validated['role'],
                $request->user()
            );

            return redirect()->back()->with('success', 'Member role updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update member role: '.$e->getMessage()]);
        }
    }

    /**
     * Create a new user account and add it to workspace members.
     */
    public function createMemberUser(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'role' => ['nullable', 'in:admin,member'],
        ]);

        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);

        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'hourly_rate' => $validated['hourly_rate'] ?? config('business.default_hourly_rate', 150000),
            ]);

            $this->workspaceService->addMember(
                $workspace,
                $user,
                $validated['role'] ?? AccessService::WORKSPACE_MEMBER,
                $request->user()
            );

            return redirect()->back()->with('success', 'User created and added to workspace successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to create user: '.$e->getMessage()]);
        }
    }

    /**
     * Update workspace member user profile fields.
     */
    public function updateMemberUser(Request $request, Workspace $workspace): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($request->input('user_id')),
            ],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        abort_unless($this->accessService->canManageWorkspace($request->user(), $workspace), 403);

        try {

            $user = User::findOrFail($validated['user_id']);
            if (! $workspace->isMember($user)) {
                return redirect()->back()->withErrors(['error' => 'User is not a member of this workspace.']);
            }

            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'hourly_rate' => $validated['hourly_rate'] ?? $user->hourly_rate,
            ]);

            return redirect()->back()->with('success', 'User updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to update user: '.$e->getMessage()]);
        }
    }
}
