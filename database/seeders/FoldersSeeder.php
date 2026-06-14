<?php

namespace Database\Seeders;

use App\Models\Folder;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class FoldersSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $jeff = $this->demoUser('admin@example.com');
        $kevin = $this->demoUser('kevin@example.com');

        $folders = [
            ['name' => 'ERP System',    'space' => 'Manufacturing', 'position' => 0, 'color' => '#F97316', 'created_by' => $jeff->id],
            ['name' => 'IoT & Monitoring', 'space' => 'Manufacturing', 'position' => 1, 'color' => '#EF4444', 'created_by' => $jeff->id],
            ['name' => 'Client Portal', 'space' => 'B2B',           'position' => 0, 'color' => '#3B82F6', 'created_by' => $kevin->id],
            ['name' => 'E-Commerce',    'space' => 'B2C',           'position' => 0, 'color' => '#10B981', 'created_by' => $kevin->id],
            ['name' => 'Mobile App',    'space' => 'B2C',           'position' => 1, 'color' => '#7C3AED', 'created_by' => $kevin->id],
        ];

        foreach ($folders as $f) {
            Folder::create([
                'name' => $f['name'],
                'space_id' => $this->demoSpace($f['space'])->id,
                'position' => $f['position'],
                'color' => $f['color'],
                'created_by' => $f['created_by'],
            ]);
        }
    }
}
