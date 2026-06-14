<?php

namespace Database\Seeders;

use App\Models\Label;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class LabelsSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $workspace = $this->demoWorkspace();

        foreach ([
            ['name' => 'Bug',           'color' => '#EF4444'],
            ['name' => 'Feature',       'color' => '#3B82F6'],
            ['name' => 'Enhancement',   'color' => '#10B981'],
            ['name' => 'Documentation', 'color' => '#6B7280'],
            ['name' => 'UI/UX',         'color' => '#8B5CF6'],
            ['name' => 'Refactor',      'color' => '#F59E0B'],
            ['name' => 'Security',      'color' => '#DC2626'],
            ['name' => 'Performance',   'color' => '#0EA5E9'],
        ] as $label) {
            Label::create([
                'workspace_id' => $workspace->id,
                'name' => $label['name'],
                'color' => $label['color'],
            ]);
        }
    }
}
