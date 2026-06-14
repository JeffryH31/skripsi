<?php

namespace Tests\Traits;

use App\Models\Label;
use App\Models\Project;
use App\Models\Space;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;

trait CreatesWorkspaceHierarchy
{
    protected function createUser(array $overrides = []): User
    {
        return User::factory()->create($overrides);
    }

    /**
     * Create a full workspace → space → statuses → list → task hierarchy.
     *
     * Space::created() auto-creates 4 default statuses (Open, In Progress, Review, Completed).
     * The $owner is explicitly attached as 'admin' member.
     */
    protected function createFullHierarchy(User $owner, string $suffix = 'A'): array
    {
        $workspace = Workspace::create([
            'name' => "Workspace {$suffix}",
            'color' => '#1D4ED8',
        ]);
        $workspace->addMember($owner, 'owner');

        $space = Space::create([
            'workspace_id' => $workspace->id,
            'name' => "Space {$suffix}",
            'description' => "Test space {$suffix}",
            'color' => '#6366F1',
            'created_by' => $owner->id,
        ]);

        $statuses = Status::where('space_id', $space->id)->orderBy('position')->get();

        $list = Project::create([
            'space_id' => $space->id,
            'name' => "List {$suffix}",
            'description' => "Test list {$suffix}",
            'created_by' => $owner->id,
        ]);

        $task = Task::create([
            'project_id' => $list->id,
            'name' => "Task {$suffix}",
            'description' => "Test task {$suffix}",
            'created_by' => $owner->id,
        ]);

        return compact('workspace', 'space', 'statuses', 'list', 'task');
    }

    protected function createSubtask(Task $task, array $overrides = []): Subtask
    {
        return Subtask::create(array_merge([
            'task_id' => $task->id,
            'name' => 'Test Subtask',
            'created_by' => $task->created_by,
        ], $overrides));
    }

    protected function createSprint(Project $list, array $overrides = []): Sprint
    {
        return Sprint::create(array_merge([
            'space_id' => $list->space_id,
            'project_id' => $list->id,
            'name' => 'Sprint 1',
            'start_date' => now()->startOfDay(),
            'end_date' => now()->addDays(14)->startOfDay(),
            'is_active' => false,
            'position' => $list->sprints()->max('position') + 1,
        ], $overrides));
    }

    protected function createLabel(Workspace $workspace, array $overrides = []): Label
    {
        return Label::create(array_merge([
            'workspace_id' => $workspace->id,
            'name' => 'Bug',
            'color' => '#EF4444',
        ], $overrides));
    }
}
