<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Determine whether the user can view the workspace.
     */
    public function view(User $user, Workspace $workspace): bool
    {
        return $workspace->members()
            ->wherePivot('user_id', $user->id)
            ->exists();
    }

    /**
     * Determine whether the user can update the workspace.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $workspace->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivotIn('role', ['admin'])
            ->exists();
    }

    /**
     * Determine whether the user can delete the workspace.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $workspace->members()
            ->wherePivot('user_id', $user->id)
            ->wherePivotIn('role', ['admin'])
            ->exists();
    }
}
