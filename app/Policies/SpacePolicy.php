<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Space;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * SpacePolicy
 *
 * Authorization policy for Space model .
 * Determines what actions a user can perform on a space.
 */
class SpacePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any spaces.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the space.
     */
    public function view(User $user, Space $space): bool
    {
        // Space creator can always view
        if ($space->created_by === $user->id) {
            return true;
        }

        // Workspace owner can view
        if ($space->workspace->owner_id === $user->id) {
            return true;
        }

        // If space is not private, workspace member can view
        if (! $space->is_private && $space->workspace->hasMember($user)) {
            return true;
        }

        // Space member can view
        return $space->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create spaces.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the space.
     */
    public function update(User $user, Space $space): bool
    {
        // Space creator can always update
        if ($space->created_by === $user->id) {
            return true;
        }

        // Workspace owner can update
        if ($space->workspace->owner_id === $user->id) {
            return true;
        }

        // Workspace admin can update
        if ($space->workspace->isAdmin($user)) {
            return true;
        }

        // Space admin can update
        $membership = $space->members()->where('user_id', $user->id)->first();

        return $membership && $membership->pivot->role === 'admin';
    }

    /**
     * Determine whether the user can delete the space.
     */
    public function delete(User $user, Space $space): bool
    {
        // Space creator can delete
        if ($space->created_by === $user->id) {
            return true;
        }

        // Workspace owner can delete
        return $space->workspace->owner_id === $user->id;
    }

    /**
     * Determine whether the user can restore the space.
     */
    public function restore(User $user, Space $space): bool
    {
        return $this->delete($user, $space);
    }

    /**
     * Determine whether the user can permanently delete the space.
     */
    public function forceDelete(User $user, Space $space): bool
    {
        return $space->workspace->owner_id === $user->id;
    }

    /**
     * Determine whether the user can manage space members.
     */
    public function manageMembers(User $user, Space $space): bool
    {
        return $this->update($user, $space);
    }
}
