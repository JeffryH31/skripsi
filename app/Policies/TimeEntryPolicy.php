<?php

namespace App\Policies;

use App\Models\TimeEntry;
use App\Models\User;

class TimeEntryPolicy
{
    /**
     * Determine whether the user can update the time entry.
     */
    public function update(User $user, TimeEntry $timeEntry): bool
    {
        return $user->id === $timeEntry->user_id;
    }

    /**
     * Determine whether the user can delete the time entry.
     */
    public function delete(User $user, TimeEntry $timeEntry): bool
    {
        return $user->id === $timeEntry->user_id;
    }
}
