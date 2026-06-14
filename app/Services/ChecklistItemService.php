<?php

namespace App\Services;

use App\Models\ChecklistItem;
use App\Models\Subtask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChecklistItemService
{
    /**
     * Create a new checklist item under a subtask.
     */
    public function create(array $data, Subtask $subtask, User $user): ChecklistItem
    {
        $parentId = $data['parent_id'] ?? null;

        if ($parentId) {
            $parent = ChecklistItem::where('subtask_id', $subtask->id)->findOrFail((int) $parentId);

            if ($parent->depth >= ChecklistItem::MAX_DEPTH) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Maximum checklist nesting depth ('.(ChecklistItem::MAX_DEPTH + 1).' levels) reached.'],
                ]);
            }
        }

        // progress recalculation triggered by ChecklistItem::saved boot hook
        return ChecklistItem::create([
            'subtask_id' => $subtask->id,
            'parent_id' => $parentId,
            'name' => $data['name'],
            'is_checked' => $data['is_checked'] ?? false,
            'created_by' => $user->id,
        ]);
    }

    /**
     * Update a checklist item's name.
     */
    public function update(ChecklistItem $item, array $data): ChecklistItem
    {
        $item->update(array_filter([
            'name' => $data['name'] ?? null,
        ], fn ($v) => $v !== null));

        return $item->fresh();
    }

    /**
     * Toggle the checked state of a checklist item.
     * Optionally cascade to all descendants.
     */
    public function toggle(ChecklistItem $item, bool $cascade = false): ChecklistItem
    {
        $newState = ! $item->is_checked;

        DB::transaction(function () use ($item, $newState, $cascade) {
            $item->update(['is_checked' => $newState]);

            if ($cascade) {
                $this->cascadeCheckedState($item, $newState);
            }
        });

        // progress recalculation is triggered by the saved boot hook
        return $item->fresh();
    }

    /**
     * Delete a checklist item (children cascade via FK).
     */
    public function delete(ChecklistItem $item): void
    {
        // Children are hard-deleted via FK cascade.
        // The deleted hook on each item triggers subtask progress recalc.
        $item->delete();
    }

    /**
     * Reorder checklist items within the same parent.
     *
     */
    public function reorder(Subtask $subtask, array $itemIds, ?int $parentId = null): void
    {
        DB::transaction(function () use ($subtask, $itemIds, $parentId) {
            foreach ($itemIds as $position => $itemId) {
                ChecklistItem::where('id', $itemId)
                    ->where('subtask_id', $subtask->id)
                    ->where('parent_id', $parentId)
                    ->update(['position' => $position]);
            }
        });
    }

    //  Private helpers
    private function cascadeCheckedState(ChecklistItem $item, bool $state): void
    {
        foreach ($item->children as $child) {
            $child->update(['is_checked' => $state]);
            $this->cascadeCheckedState($child, $state);
        }
    }
}
