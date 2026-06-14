<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class View extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'space_id',
        'user_id',
        'name',
        'type',
        'filters',
        'sorts',
        'columns',
        'settings',
        'is_default',
        'is_private',
        'position',
    ];

    protected $casts = [
        'filters' => 'array',
        'sorts' => 'array',
        'columns' => 'array',
        'settings' => 'array',
        'is_default' => 'boolean',
        'is_private' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere('is_private', false);
        });
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
