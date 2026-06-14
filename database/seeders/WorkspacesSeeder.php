<?php

namespace Database\Seeders;

use App\Models\Workspace;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class WorkspacesSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $jeff = $this->demoUser('admin@example.com');
        $kevin = $this->demoUser('kevin@example.com');
        $christopher = $this->demoUser('christopher@example.com');
        $marvel = $this->demoUser('marvel@example.com');
        $devin = $this->demoUser('devin@example.com');

        $workspace = Workspace::create([
            'name' => 'MIS Department',
            'slug' => 'mis-department',
            'color' => '#6366F1',
        ]);

        $workspace->addMember($jeff, 'owner');
        $workspace->addMember($kevin, 'admin');
        $workspace->addMember($christopher, 'member');
        $workspace->addMember($marvel, 'member');
        $workspace->addMember($devin, 'member');
    }
}
