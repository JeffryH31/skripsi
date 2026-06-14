<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Folder;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FolderService
{
    /**
     * Get folders for a space
     */
    public function getFoldersForSpace(Space $space): \Illuminate\Database\Eloquent\Collection
    {
        return $space->folders()
            ->with(['children', 'projects'])
            ->orderBy('position')
            ->get();
    }

    /**
     * Create a new folder
     */
    public function create(array $data, Space $space, User $user, ?Folder $parent = null): Folder
    {
        return DB::transaction(function () use ($data, $space, $user, $parent) {
            $folder = Folder::create([
                'space_id' => $space->id,
                'parent_id' => $parent?->id,
                'name' => $data['name'],
                'slug' => Str::slug($data['name']),
                'description' => $data['description'] ?? null,
                'color' => $data['color'] ?? null,
                'is_hidden' => $data['is_hidden'] ?? false,
                'created_by' => $user->id,
            ]);

            Activity::log($space->workspace, $user, $folder, 'created', [
                'name' => $folder->name,
                'space_name' => $space->name,
            ]);

            return $folder;
        });
    }

    /**
     * Update a folder
     */
    public function update(Folder $folder, array $data, User $user): Folder
    {
        $oldName = $folder->name;

        $folder->update([
            'name' => $data['name'] ?? $folder->name,
            'description' => $data['description'] ?? $folder->description,
            'color' => $data['color'] ?? $folder->color,
            'is_hidden' => $data['is_hidden'] ?? $folder->is_hidden,
        ]);

        if ($oldName !== $folder->name) {
            Activity::log($folder->space->workspace, $user, $folder, 'updated', [
                'name' => $folder->name,
            ], [
                'name' => ['old' => $oldName, 'new' => $folder->name],
            ]);
        }

        return $folder->fresh();
    }

    /**
     * Delete a folder
     */
    public function delete(Folder $folder, User $user): void
    {
        DB::transaction(function () use ($folder, $user) {
            Activity::log($folder->space->workspace, $user, $folder, 'deleted', [
                'name' => $folder->name,
            ]);

            $folder->delete();
        });
    }

    /**
     * Move folder to new parent
     */
    public function move(Folder $folder, ?Folder $newParent, User $user): Folder
    {
        $oldParentName = $folder->parent?->name ?? 'Root';
        $newParentName = $newParent?->name ?? 'Root';

        $folder->update([
            'parent_id' => $newParent?->id,
            'position' => Folder::where('space_id', $folder->space_id)
                ->where('parent_id', $newParent?->id)
                ->max('position') + 1,
        ]);

        Activity::log($folder->space->workspace, $user, $folder, 'moved', [
            'name' => $folder->name,
        ], [
            'parent' => ['old' => $oldParentName, 'new' => $newParentName],
        ]);

        return $folder->fresh();
    }

    /**
     * Reorder folders
     */
    public function reorder(Space $space, array $order): void
    {
        DB::transaction(function () use ($space, $order) {
            foreach ($order as $position => $folderId) {
                Folder::where('id', $folderId)
                    ->where('space_id', $space->id)
                    ->update(['position' => $position]);
            }
        });
    }
}
