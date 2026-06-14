<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistItem extends Model
{
    public const MAX_DEPTH = 6; // 7 levels total (0–6)

    protected $fillable = [
        'subtask_id',
        'parent_id',
        'name',
        'is_checked',
        'position',
        'depth',
        'created_by',
    ];

    protected $casts = [
        'is_checked' => 'boolean',
        'position' => 'integer',
        'depth' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($item) {
            // Resolve depth from parent
            if ($item->parent_id) {
                $parent = static::find($item->parent_id);
                $item->depth = $parent ? $parent->depth + 1 : 0;
            } else {
                $item->depth = 0;
            }

            // Auto-position within siblings
            if (is_null($item->position)) {
                $max = static::where('subtask_id', $item->subtask_id)
                    ->where('parent_id', $item->parent_id)
                    ->max('position');
                $item->position = $max !== null ? $max + 1 : 0;
            }
        });

        // Recalculate parent subtask progress after save/delete
        static::saved(function ($item) {
            $item->subtask?->recalculateProgress();
        });

        static::deleted(function ($item) {
            $item->subtask?->recalculateProgress();
        });
    }

    //  Relations
    public function subtask(): BelongsTo
    {
        return $this->belongsTo(Subtask::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'parent_id')->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    //  Helpers
    public function canAddChildren(): bool
    {
        return $this->depth < self::MAX_DEPTH;
    }
}
