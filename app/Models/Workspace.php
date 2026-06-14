<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'is_personal',
    ];

    protected $casts = [
        'is_personal' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($workspace) {
            if (empty($workspace->slug)) {
                $workspace->slug = Str::slug($workspace->name);
            }

            $originalSlug = $workspace->slug;
            $count = 1;
            while (static::where('slug', $workspace->slug)->exists()) {
                $workspace->slug = $originalSlug.'-'.$count++;
            }
        });
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Space::class)->orderBy('position');
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    public function getInitialsAttribute(): string
    {
        return strtoupper(substr($this->name, 0, 2));
    }

    public function scopeForUser($query, User $user)
    {
        return $query->whereHas('members', fn ($q) => $q->where('user_id', $user->id));
    }

    public function addMember(User $user, string $role = 'member'): void
    {
        $this->members()->syncWithoutDetaching([
            $user->id => ['role' => $role],
        ]);
    }

    public function removeMember(User $user): void
    {
        $this->members()->detach($user->id);
    }

    public function isMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function getMemberRole(User $user): ?string
    {
        $member = $this->members()->where('user_id', $user->id)->first();

        return $member?->pivot->role;
    }
}
