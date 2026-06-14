<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Status extends Model
{
    use HasFactory;

    protected $fillable = [
        'space_id',
        'name',
        'slug',
        'color',
        'type',
        'applies_to',
        'position',
        'is_default',
        'is_closed',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_closed' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($status) {
            if (empty($status->slug)) {
                $status->slug = Str::slug($status->name);
            }
        });
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function subtasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Subtask::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('is_closed', false);
    }

    public function scopeClosed($query)
    {
        return $query->where('is_closed', true);
    }

    /**
     * Scope to get statuses applicable for tasks
     */
    public function scopeForTasks($query)
    {
        return $query->whereIn('applies_to', ['tasks', 'both']);
    }

    /**
     * Scope to get statuses applicable for subtasks
     */
    public function scopeForSubtasks($query)
    {
        return $query->whereIn('applies_to', ['subtasks', 'both']);
    }
}
