<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subtask_id' => $this->subtask_id,
            'duration' => $this->duration,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'is_running' => $this->is_running ?? false,
            'is_billable' => $this->is_billable ?? false,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'user' => new UserResource($this->whenLoaded('user')),
            'subtask' => $this->whenLoaded('subtask', fn () => [
                'id' => $this->subtask->id,
                'name' => $this->subtask->name,
                'task_id' => $this->subtask->task_id,
                'task' => $this->subtask->relationLoaded('task') ? [
                    'id' => $this->subtask->task->id,
                    'name' => $this->subtask->task->name,
                    'task_id' => $this->subtask->task->task_id,
                    'project_id' => $this->subtask->task->project_id,
                    'project' => $this->subtask->task->relationLoaded('project') ? [
                        'id' => $this->subtask->task->project->id,
                        'name' => $this->subtask->task->project->name,
                        'space_id' => $this->subtask->task->project->space_id,
                    ] : null,
                ] : null,
            ]),
        ];
    }
}
