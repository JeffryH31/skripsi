<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sprint extends Model
{
    use HasFactory;

    protected $fillable = [
        'space_id',
        'project_id',
        'name',
        'goal',
        'start_date',
        'end_date',
        'is_active',
        'position',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the space that owns the sprint.
     */
    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Get the project that owns the sprint.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the subtasks for the sprint.
     */
    public function subtasks()
    {
        return $this->hasMany(Subtask::class);
    }

    /**
     * Scope for active sprints.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for upcoming sprints.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_date', '>', now());
    }

    /**
     * Scope for completed sprints.
     */
    public function scopeCompleted($query)
    {
        return $query->where('end_date', '<', now());
    }

    /**
     * Check if sprint is currently active (date range).
     */
    public function isInProgress(): bool
    {
        $today = now()->startOfDay();
        $start = $this->start_date?->copy()->startOfDay();
        $end = $this->end_date?->copy()->endOfDay();

        if (! $start || ! $end) {
            return false;
        }

        return $today->between($start, $end);
    }

    /**
     * Check if sprint is completed.
     */
    public function isCompleted(): bool
    {
        $end = $this->end_date?->copy()->endOfDay();
        if (! $end) {
            return false;
        }

        return now()->greaterThan($end);
    }

    /**
     * Get sprint duration in days.
     */
    public function getDurationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date);
    }

    /**
     * Get remaining days in sprint.
     */
    public function getRemainingDays(): int
    {
        $end = $this->end_date?->copy()->startOfDay();
        if (! $end) {
            return 0;
        }

        $today = now()->startOfDay();
        if ($today->greaterThan($end)) {
            return 0;
        }

        return $today->diffInDays($end, false);
    }
}
