<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubtaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subtask_id' => $this->subtask_id,
            'task_id' => $this->task_id,
            'parent_id' => $this->parent_id,
            'depth' => $this->depth,

            // Nesting helpers for the frontend
            'can_add_children' => $this->canAddChildren(),
            'has_kanban_view' => $this->hasKanbanView(),

            'name' => $this->name,
            'description' => $this->description,
            'status' => new StatusResource($this->whenLoaded('status')),
            'priority' => $this->priority,
            'assignees' => UserResource::collection($this->whenLoaded('assignees')),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),

            'start_date' => $this->start_date?->toISOString(),
            'due_date' => $this->due_date?->toISOString(),
            'baseline_start_date' => $this->baseline_start_date?->toISOString(),
            'baseline_due_date' => $this->baseline_due_date?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),

            'time_estimate' => $this->time_estimate,
            'optimistic_estimate' => $this->optimistic_estimate,
            'most_likely_estimate' => $this->most_likely_estimate,
            'pessimistic_estimate' => $this->pessimistic_estimate,
            'pert_expected_estimate' => $this->pert_expected_estimate,
            'pert_variance' => $this->pert_variance,
            'time_spent' => $this->time_spent,

            // Progress is auto-calculated from checklist items
            'progress' => $this->progress,
            'checklist_total' => $this->whenLoaded('checklistItems', fn () => $this->checklistItems->count(), 0),
            'checklist_checked' => $this->whenLoaded('checklistItems', fn () => $this->checklistItems->where('is_checked', true)->count(), 0),
            // Flat list — Vue builds the tree client-side using parent_id
            'checklist_items' => ChecklistItemResource::collection($this->whenLoaded('checklistItems')),

            'children' => self::collection($this->whenLoaded('children')),
            'children_count' => $this->whenLoaded('children', fn () => $this->children->count(), 0),

            'position' => $this->position,
            'is_overdue' => $this->isOverdue(),
            'is_completed' => $this->isCompleted(),
            'schedule_variance_minutes' => $this->baseline_due_date && $this->due_date
                ? $this->baseline_due_date->diffInMinutes($this->due_date, false)
                : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
