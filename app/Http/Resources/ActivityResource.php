<?php

namespace App\Http\Resources;

use App\Models\Folder;
use App\Models\Project;
use App\Models\Space;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'description' => $this->description,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id,
            'subject_url' => $this->resolveSubjectUrl(),
            'context' => $this->resolveContext(),
            'properties' => $this->properties ?? [],
            'changes' => $this->changes ?? [],
            'user' => new UserResource($this->whenLoaded('user')),
            'created_at' => $this->created_at?->toISOString(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }

    private function resolveSubjectUrl(): ?string
    {
        $subject = $this->relationLoaded('subject') ? $this->subject : null;
        if (! $subject) {
            return null;
        }

        $workspaceId = $this->workspace_id;

        return match (true) {
            $subject instanceof Workspace => route('workspaces.show', $workspaceId),

            $subject instanceof Space => route('spaces.show', [$workspaceId, $subject->id]),

            $subject instanceof Folder => $subject->space_id
                ? route('spaces.show', [$workspaceId, $subject->space_id])
                : null,

            $subject instanceof Project => $subject->space_id
                ? route('projects.show', [$workspaceId, $subject->space_id, $subject->id])
                : null,

            $subject instanceof Task => $subject->project?->space_id
                ? route('projects.show', [$workspaceId, $subject->project->space_id, $subject->project_id])
                : null,

            $subject instanceof Subtask => $subject->task?->project?->space_id
                ? route('projects.show', [$workspaceId, $subject->task->project->space_id, $subject->task->project_id])
                : null,

            default => null,
        };
    }

    private function resolveContext(): ?string
    {
        $subject = $this->relationLoaded('subject') ? $this->subject : null;
        if (! $subject) {
            return null;
        }

        return match (true) {
            $subject instanceof Task => $subject->project?->space
                ? $subject->project->space->name.' / '.$subject->project->name
                : null,

            $subject instanceof Subtask => $subject->task?->project?->space
                ? $subject->task->project->space->name.' / '.$subject->task->project->name
                : null,

            $subject instanceof Project => $subject->space?->name,

            $subject instanceof Folder => $subject->space?->name,

            default => null,
        };
    }
}
