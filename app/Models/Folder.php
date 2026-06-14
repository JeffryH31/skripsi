<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Folder extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'space_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'color',
        'is_hidden',
        'position',
        'created_by',
    ];

    protected $casts = [
        'is_hidden' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($folder) {
            if (empty($folder->slug)) {
                $baseSlug = Str::slug($folder->name);
                $slug = $baseSlug;
                $counter = 1;
                while (static::where('space_id', $folder->space_id)
                    ->where('slug', $slug)
                    ->exists()) {
                    $slug = $baseSlug.'-'.$counter++;
                }
                $folder->slug = $slug;
            }

            if (empty($folder->position)) {
                $folder->position = static::where('space_id', $folder->space_id)
                    ->where('parent_id', $folder->parent_id)
                    ->max('position') + 1;
            }
        });
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('position');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->orderBy('position');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function allChildren(): HasMany
    {
        return $this->children()->with('allChildren');
    }

    public function allProjects(): HasMany
    {
        return $this->projects()->with('tasks');
    }

    public function getDepth(): int
    {
        $depth = 0;
        $parent = $this->parent;

        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    public function getBreadcrumbs(): array
    {
        $breadcrumbs = [$this];
        $parent = $this->parent;

        while ($parent) {
            array_unshift($breadcrumbs, $parent);
            $parent = $parent->parent;
        }

        return $breadcrumbs;
    }
}
