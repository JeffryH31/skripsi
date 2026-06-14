<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'subtask_id' => $this->subtask_id,
            'parent_id' => $this->parent_id,
            'content' => $this->content,
            'mentions' => $this->mentions ?? [],
            'is_resolved' => $this->is_resolved ?? false,
            'edited_at' => $this->edited_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'user' => new UserResource($this->whenLoaded('user')),
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
        ];
    }
}
