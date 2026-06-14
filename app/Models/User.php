<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model
 *
 * Represents a user in the project management system.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $role
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'hourly_rate',
        'is_super_admin',
        'last_notifications_read_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'profile_photo_url',
        'initials',
        'avatar_color',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'hourly_rate' => 'decimal:2',
            'is_super_admin' => 'boolean',
            'last_notifications_read_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function spaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class, 'space_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function starredSpaces(): BelongsToMany
    {
        return $this->belongsToMany(Space::class, 'starred_spaces', 'user_id', 'space_id')
            ->withTimestamps();
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignees')
            ->withPivot('assigned_by');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);
        $initials = '';

        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }

        return $initials ?: strtoupper(substr($this->name, 0, 2));
    }

    public function getAvatarColorAttribute(): string
    {
        $colors = [
            '#6366F1', '#8B5CF6', '#EC4899', '#EF4444',
            '#F59E0B', '#10B981', '#0EA5E9', '#06B6D4',
        ];

        return $colors[$this->id % count($colors)];
    }

    public function getActiveWorkspace(): ?Workspace
    {
        return $this->workspaces()->first();
    }

    public function getMyTasks()
    {
        return $this->assignedTasks()
            ->with(['project.space', 'status'])
            ->orderBy('position')
            ->get();
    }

    public function getOverdueTasks()
    {
        // Tasks don't have due dates - only subtasks do
        // Return tasks that have overdue subtasks
        return $this->assignedTasks()
            ->whereHas('subtasks', function ($q) {
                $q->whereNull('completed_at')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now());
            })
            ->get();
    }

    public function getTodayTimeSpent(): int
    {
        return $this->timeEntries()
            ->whereDate('started_at', today())
            ->sum('duration');
    }

    public function getWeekTimeSpent(): int
    {
        return $this->timeEntries()
            ->whereBetween('started_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->sum('duration');
    }

    public function markNotificationsRead(): void
    {
        $this->forceFill([
            'last_notifications_read_at' => now(),
        ])->save();
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->is_super_admin;
    }
}
