<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subtask_id' => $this->subtask_id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'is_checked' => $this->is_checked,
            'position' => $this->position,
            'depth' => $this->depth,
            'can_add_children' => $this->canAddChildren(),
            // Nested children loaded on demand
            'children' => self::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
