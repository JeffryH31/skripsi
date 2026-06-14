<?php

namespace App\Models;

use App\Enums\PriorityLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subtask extends Model
{
    use HasFactory, SoftDeletes;

    public const MAX_DEPTH = 6; // 7 levels total (0–6)

    protected $fillable = [
        'subtask_id',
        'task_id',
        'parent_id',
        'depth',
        'sprint_id',
        'status_id',
        'priority_level',
        'name',
        'description',
        'start_date',
        'due_date',
        'baseline_start_date',
        'baseline_due_date',
        'completed_at',
        'time_estimate',
        'optimistic_estimate',
        'most_likely_estimate',
        'pessimistic_estimate',
        'time_spent',
        'progress',
        'position',
        'created_by',
        'completed_by',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'due_date' => 'datetime',
        'baseline_start_date' => 'datetime',
        'baseline_due_date' => 'datetime',
        'completed_at' => 'datetime',
        'time_estimate' => 'integer',
        'optimistic_estimate' => 'integer',
        'most_likely_estimate' => 'integer',
        'pessimistic_estimate' => 'integer',
        'time_spent' => 'integer',
        'progress' => 'integer',
        'priority_level' => PriorityLevel::class,
    ];

    protected $with = ['status'];

    /**
     * Appended so these fields appear in toArray() (used by ProjectService).
     * Without $appends, they are only present in SubtaskResource responses.
     */
    protected $appends = ['can_add_children', 'has_kanban_view', 'checklist_total', 'checklist_checked', 'pert_expected_estimate', 'pert_variance'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($subtask) {
            // Resolve depth from parent
            if ($subtask->parent_id) {
                $parent = static::find($subtask->parent_id);
                $subtask->depth = $parent ? $parent->depth + 1 : 0;
            } else {
                $subtask->depth = 0;
            }

            // Generate human-readable subtask_id
            if (empty($subtask->subtask_id)) {
                $task = Task::find($subtask->task_id);
                $count = static::withTrashed()->where('task_id', $subtask->task_id)->count() + 1;
                $subtask->subtask_id = $task->task_id.'-'.$count;

                while (static::withTrashed()->where('subtask_id', $subtask->subtask_id)->exists()) {
                    $count++;
                    $subtask->subtask_id = $task->task_id.'-'.$count;
                }
            }

            // Position scoped to siblings (same parent + same status)
            if (is_null($subtask->position)) {
                $maxPosition = static::where('task_id', $subtask->task_id)
                    ->where('parent_id', $subtask->parent_id)
                    ->where('status_id', $subtask->status_id)
                    ->max('position');
                $subtask->position = $maxPosition !== null ? $maxPosition + 1 : 0;
            }
        });

        // Cascade soft-delete to children and time entries
        static::deleting(function ($subtask) {
            if ($subtask->isForceDeleting()) {
                return;
            }
            $subtask->children()->each(fn ($child) => $child->delete());
            $subtask->timeEntries()->each(fn ($entry) => $entry->delete());
        });

        // Cascade restore to children and time entries
        static::restoring(function ($subtask) {
            $subtask->children()->onlyTrashed()->each(fn ($child) => $child->restore());
            $subtask->timeEntries()->onlyTrashed()->each(fn ($entry) => $entry->restore());
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** Direct parent subtask (null for level-1 subtasks) */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Subtask::class, 'parent_id');
    }

    /** Direct children subtasks */
    public function children(): HasMany
    {
        return $this->hasMany(Subtask::class, 'parent_id')->orderBy('position');
    }

    /** All checklist items belonging to this subtask */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('position');
    }

    /** Top-level checklist items only (parent_id IS NULL) */
    public function rootChecklistItems(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->whereNull('parent_id')->orderBy('position');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    public function getPriorityAttribute(): ?array
    {
        return $this->priority_level?->toArray();
    }

    public function getCanAddChildrenAttribute(): bool
    {
        return $this->canAddChildren();
    }

    public function getHasKanbanViewAttribute(): bool
    {
        return $this->hasKanbanView();
    }

    public function getChecklistTotalAttribute(): int
    {
        return $this->relationLoaded('checklistItems')
            ? $this->checklistItems->count()
            : 0;
    }

    public function getChecklistCheckedAttribute(): int
    {
        return $this->relationLoaded('checklistItems')
            ? $this->checklistItems->where('is_checked', true)->count()
            : 0;
    }

    /** Whether this subtask can have children (depth < MAX_DEPTH) */
    public function canAddChildren(): bool
    {
        return $this->depth < self::MAX_DEPTH;
    }

    /** Whether this subtask should show a Kanban for its children (level 1 & 2 = depth 0 & 1) */
    public function hasKanbanView(): bool
    {
        return $this->depth < 2;
    }

    /**
     * Recalculate and persist progress from checklist items.
     * progress = (checked / total) * 100, or 0 if no checklist items.
     */
    public function recalculateProgress(): void
    {
        $total = ChecklistItem::where('subtask_id', $this->id)->count();
        $checked = ChecklistItem::where('subtask_id', $this->id)->where('is_checked', true)->count();

        $progress = $total > 0 ? (int) round(($checked / $total) * 100) : 0;

        $this->withoutEvents(fn () => $this->update(['progress' => $progress]));
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subtask_assignees')
            ->withPivot(['assigned_by']);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class, 'subtask_labels')
            ->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Subtask::class, 'subtask_dependencies', 'subtask_id', 'depends_on_subtask_id')
            ->withPivot('dependency_type')
            ->withTimestamps();
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Subtask::class, 'subtask_dependencies', 'depends_on_subtask_id', 'subtask_id')
            ->withPivot('dependency_type')
            ->withTimestamps();
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeIncomplete($query)
    {
        return $query->whereNull('completed_at');
    }

    public function scopeOverdue($query)
    {
        return $query->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    public function scopeDueToday($query)
    {
        return $query->whereNull('completed_at')
            ->whereDate('due_date', today());
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [now(), now()->addDays($days)]);
    }

    public function isOverdue(): bool
    {
        return ! $this->completed_at && $this->due_date && $this->due_date->isPast();
    }

    public function isCompleted(): bool
    {
        return ! is_null($this->completed_at);
    }

    public function getPertExpectedEstimateAttribute(): ?float
    {
        if (
            is_null($this->optimistic_estimate) ||
            is_null($this->most_likely_estimate) ||
            is_null($this->pessimistic_estimate)
        ) {
            return null;
        }

        return round((
            $this->optimistic_estimate +
            (4 * $this->most_likely_estimate) +
            $this->pessimistic_estimate
        ) / 6, 2);
    }

    public function getPertVarianceAttribute(): ?float
    {
        if (is_null($this->optimistic_estimate) || is_null($this->pessimistic_estimate)) {
            return null;
        }

        return round(pow(($this->pessimistic_estimate - $this->optimistic_estimate) / 6, 2), 2);
    }

    public function getPlannedEstimateAttribute(): ?float
    {
        return $this->pert_expected_estimate ?? $this->time_estimate;
    }

    public function hasUncompletedDependencies(): bool
    {
        return $this->dependencies()
            ->whereNull('completed_at')
            ->wherePivotIn('dependency_type', ['blocks'])
            ->exists();
    }

    public function getUncompletedDependencyNames(): array
    {
        return $this->dependencies()
            ->whereNull('completed_at')
            ->wherePivotIn('dependency_type', ['blocks'])
            ->pluck('name')
            ->toArray();
    }

    public function markAsCompleted(User $user, ?int $targetStatusId = null): void
    {
        $resolvedStatusId = $this->resolveCompletionStatusId($targetStatusId);

        // progress is driven by checklist, not forced to 100 here
        $payload = [
            'completed_at' => now(),
            'completed_by' => $user->id,
        ];

        if ($resolvedStatusId) {
            $payload['status_id'] = $resolvedStatusId;
        }

        $this->update($payload);
    }

    public function markAsIncomplete(): void
    {
        $defaultOpenStatusId = $this->resolveDefaultOpenStatusId();

        // progress is driven by checklist, reset to current checklist state
        $payload = [
            'completed_at' => null,
            'completed_by' => null,
        ];

        if ($defaultOpenStatusId) {
            $payload['status_id'] = $defaultOpenStatusId;
        }

        $this->update($payload);
    }

    protected function resolveCompletionStatusId(?int $targetStatusId): ?int
    {
        if (! $targetStatusId) {
            return null;
        }

        return $this->resolveCustomCompletionStatusId($targetStatusId);
    }

    protected function resolveCustomCompletionStatusId(int $targetStatusId): ?int
    {
        $spaceId = $this->task?->project?->space_id;
        if (! $spaceId) {
            return null;
        }

        return Status::query()
            ->whereKey($targetStatusId)
            ->where('space_id', $spaceId)
            ->whereIn('applies_to', ['subtasks', 'both'])
            ->value('id');
    }

    protected function resolveDefaultOpenStatusId(): ?int
    {
        $spaceId = $this->task?->project?->space_id;
        if (! $spaceId) {
            return null;
        }

        $baseQuery = Status::query()
            ->where('space_id', $spaceId)
            ->whereIn('applies_to', ['subtasks', 'both']);

        $defaultStatusId = (clone $baseQuery)
            ->where('is_default', true)
            ->orderBy('position')
            ->value('id');

        if ($defaultStatusId) {
            return $defaultStatusId;
        }

        return (clone $baseQuery)
            ->where(function ($query) {
                $query->where('is_closed', false)
                    ->where('type', '!=', 'closed');
            })
            ->orderBy('position')
            ->value('id');
    }
}
