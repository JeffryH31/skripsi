<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $earliestDueDate = null;
        $resolvedAssignees = collect();

        if ($this->relationLoaded('subtasks')) {
            $earliestDueDate = $this->subtasks
                ->filter(fn($subtask) => ! empty($subtask->due_date))
                ->sortBy('due_date')
                ->first()?->due_date;

            $resolvedAssignees = $this->subtasks
                ->flatMap(fn($subtask) => $subtask->relationLoaded('assignees') ? $subtask->assignees : collect())
                ->unique('id')
                ->values();
        } elseif ($this->relationLoaded('assignees')) {
            $resolvedAssignees = $this->assignees;
        }

        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'description' => $this->description,
            'position' => $this->position,
            'progress' => $this->progress,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'due_date' => $this->start_date || $this->due_date
                ? $this->due_date?->format('Y-m-d')
                : $earliestDueDate,
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'is_completed_late' => $this->isCompletedLate(),
            'time_estimate' => $this->time_estimate,
            'time_spent' => $this->time_spent,
            'status_id' => $this->status_id,
            'priority_level' => $this->priority_level,
            'is_archived' => $this->is_archived,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            'status' => new StatusResource($this->whenLoaded('status')),
            'priority' => $this->priority,
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                    'space_id' => $this->project->space_id,
                    'space' => $this->project->relationLoaded('space') && $this->project->space
                        ? [
                            'id' => $this->project->space->id,
                            'workspace_id' => $this->project->space->workspace_id,
                        ]
                        : null,
                ];
            }),
            'assignees' => UserResource::collection($resolvedAssignees),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'subtasks' => SubtaskResource::collection($this->whenLoaded('subtasks')),
            'dependencies' => TaskResource::collection($this->whenLoaded('dependencies')),
            'dependents' => TaskResource::collection($this->whenLoaded('dependents')),
            'parent' => new TaskResource($this->whenLoaded('parent')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
            'time_entries' => TimeEntryResource::collection($this->whenLoaded('timeEntries')),
            'comments_count' => $this->comments_count ?? $this->comments?->count() ?? 0,
            'subtasks_count' => $this->subtasks_count ?? $this->subtasks?->count() ?? 0,
        ];
    }
}
