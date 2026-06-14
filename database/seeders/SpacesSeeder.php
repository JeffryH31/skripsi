<?php

namespace Database\Seeders;

use App\Models\Space;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class SpacesSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $workspace = $this->demoWorkspace();
        $jeff = $this->demoUser('admin@example.com');
        $kevin = $this->demoUser('kevin@example.com');
        $christopher = $this->demoUser('christopher@example.com');
        $marvel = $this->demoUser('marvel@example.com');
        $devin = $this->demoUser('devin@example.com');

        //  Space 1: Manufacturing
        $manufacturing = Space::create([
            'workspace_id' => $workspace->id,
            'name' => 'Manufacturing',
            'color' => '#F97316',
            'position' => 0,
            'created_by' => $jeff->id,
        ]);
        $manufacturing->members()->syncWithoutDetaching([
            $jeff->id => ['role' => 'admin'],
            $kevin->id => ['role' => 'admin'],
            $christopher->id => ['role' => 'member'],
            $marvel->id => ['role' => 'member'],
            $devin->id => ['role' => 'member'],
        ]);

        //  Space 2: B2B
        $b2b = Space::create([
            'workspace_id' => $workspace->id,
            'name' => 'B2B',
            'color' => '#3B82F6',
            'position' => 1,
            'created_by' => $jeff->id,
        ]);
        $b2b->members()->syncWithoutDetaching([
            $jeff->id => ['role' => 'admin'],
            $kevin->id => ['role' => 'admin'],
            $christopher->id => ['role' => 'member'],
            $marvel->id => ['role' => 'member'],
            $devin->id => ['role' => 'member'],
        ]);

        //  Space 3: B2C
        $b2c = Space::create([
            'workspace_id' => $workspace->id,
            'name' => 'B2C',
            'color' => '#10B981',
            'position' => 2,
            'created_by' => $jeff->id,
        ]);
        $b2c->members()->syncWithoutDetaching([
            $jeff->id => ['role' => 'admin'],
            $kevin->id => ['role' => 'admin'],
            $christopher->id => ['role' => 'member'],
            $marvel->id => ['role' => 'member'],
            $devin->id => ['role' => 'member'],
        ]);
    }
}
