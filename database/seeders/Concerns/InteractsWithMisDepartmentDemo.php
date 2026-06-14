<?php

namespace Database\Seeders\Concerns;

use App\Models\Folder;
use App\Models\Label;
use App\Models\Project;
use App\Models\Space;
use App\Models\Sprint;
use App\Models\Status;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;

trait InteractsWithMisDepartmentDemo
{
    protected function demoUser(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function demoWorkspace(): Workspace
    {
        return Workspace::where('slug', 'mis-department')->firstOrFail();
    }

    protected function demoSpace(string $name): Space
    {
        return Space::where('name', $name)
            ->where('workspace_id', $this->demoWorkspace()->id)
            ->firstOrFail();
    }

    protected function demoStatus(string $spaceName, string $statusName): Status
    {
        return Status::where('space_id', $this->demoSpace($spaceName)->id)
            ->where('name', $statusName)
            ->firstOrFail();
    }

    protected function demoSprint(string $name): Sprint
    {
        return Sprint::where('name', $name)->first() ?? new Sprint;
    }

    protected function demoFolder(string $name): Folder
    {
        return Folder::where('name', $name)->firstOrFail();
    }

    protected function demoProject(string $name): Project
    {
        return Project::where('name', $name)->firstOrFail();
    }

    protected function demoTask(string $name): Task
    {
        return Task::where('name', $name)->firstOrFail();
    }

    protected function demoSubtask(string $name): Subtask
    {
        return Subtask::where('name', $name)->firstOrFail();
    }

    protected function demoLabel(string $name): Label
    {
        return Label::where('workspace_id', $this->demoWorkspace()->id)
            ->where('name', $name)
            ->firstOrFail();
    }
}
