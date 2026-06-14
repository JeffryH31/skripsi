<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'subtask_id',
        'user_id',
        'duration',
        'started_at',
        'ended_at',
        'is_billable',
        'is_running',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_billable' => 'boolean',
        'is_running' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        $recalcTimeSpent = function ($entry) {
            if ($entry->subtask_id) {
                Subtask::where('id', $entry->subtask_id)->update([
                    'time_spent' => TimeEntry::where('subtask_id', $entry->subtask_id)->sum('duration'),
                ]);
            }
        };

        static::created($recalcTimeSpent);
        static::updated($recalcTimeSpent);
        static::deleted($recalcTimeSpent);
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(Subtask::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDurationFormattedAttribute(): string
    {
        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;

        if ($hours > 0) {
            return $hours.'h '.$minutes.'m';
        }

        return $minutes.'m';
    }

    public function scopeRunning($query)
    {
        return $query->where('is_running', true);
    }

    public function scopeBillable($query)
    {
        return $query->where('is_billable', true);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('started_at', $date);
    }

    public function scopeForDateRange($query, $start, $end)
    {
        return $query->whereBetween('started_at', [$start, $end]);
    }

    public function stop(): void
    {
        if (! $this->is_running) {
            return;
        }

        $this->update([
            'is_running' => false,
            'ended_at' => now(),
            'duration' => max(1, (int) round($this->started_at->diffInMinutes(now()))),
        ]);
    }

    public static function startTimer(Subtask $subtask, User $user): self
    {
        static::where('user_id', $user->id)
            ->where('is_running', true)
            ->each(fn ($entry) => $entry->stop());

        return static::create([
            'subtask_id' => $subtask->id,
            'user_id' => $user->id,
            'started_at' => now(),
            'duration' => 0,
            'is_running' => true,
        ]);
    }
}
