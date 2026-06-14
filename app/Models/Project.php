<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'space_id',
        'folder_id',
        'status_id',
        'name',
        'slug',
        'description',
        'color',
        'icon',
        'is_archived',
        'position',
        'settings',
        'created_by',
    ];

    protected $casts = [
        'is_archived' => 'boolean',
        'settings' => 'array',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($list) {
            if (empty($list->slug)) {
                $list->slug = Str::slug($list->name);
            }

            if (empty($list->position)) {
                $query = static::where('space_id', $list->space_id);
                if ($list->folder_id) {
                    $query->where('folder_id', $list->folder_id);
                } else {
                    $query->whereNull('folder_id');
                }
                $list->position = $query->max('position') + 1;
            }

            if (empty($list->status_id)) {
                $space = Space::find($list->space_id);
                $list->status_id = $space?->getDefaultStatus()?->id;
            }
        });
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }

    public function allTasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }

    public function views(): HasMany
    {
        return $this->hasMany(View::class);
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(Sprint::class)->orderBy('position');
    }

    public function addMember(User $user, string $role = 'development_team'): void
    {
        $this->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    public function getTaskCountAttribute(): int
    {
        return $this->allTasks()->count();
    }

    public function getCompletedTaskCountAttribute(): int
    {
        return $this->allTasks()
            ->whereHas('subtasks', fn ($q) => $q->whereNotNull('completed_at'))
            ->whereDoesntHave('subtasks', fn ($q) => $q->whereNull('completed_at'))
            ->count();
    }

    public function getProgressAttribute(): float
    {
        $total = $this->task_count;
        if ($total === 0) {
            return 0;
        }

        return round(($this->completed_task_count / $total) * 100, 1);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope: only projects the user can access (member of, or workspace admin).
     */
    public function scopeAccessibleBy($query, User $user)
    {
        $wsAdminIds = \Illuminate\Support\Facades\DB::table('workspace_members')
            ->where('user_id', $user->id)
            ->where('role', 'admin')
            ->pluck('workspace_id');

        return $query->where(function ($q) use ($user, $wsAdminIds) {
            // Workspace admins see everything in their workspaces
            $q->whereHas('space', fn ($sq) => $sq->whereIn('workspace_id', $wsAdminIds))
              // Or user is an explicit project member
                ->orWhereHas('members', fn ($mq) => $mq->where('user_id', $user->id));
        });
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [];

        if ($this->folder) {
            $breadcrumbs = $this->folder->getBreadcrumbs();
        }

        $breadcrumbs[] = $this;

        return $breadcrumbs;
    }
}
