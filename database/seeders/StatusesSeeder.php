<?php

namespace Database\Seeders;

use App\Models\Status;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class StatusesSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $definitions = [
            'Manufacturing' => [
                ['name' => 'Backlog',     'type' => 'open',        'color' => '#6B7280', 'position' => 0, 'applies_to' => 'both'],
                ['name' => 'To Do',       'type' => 'open',        'color' => '#3B82F6', 'position' => 1, 'applies_to' => 'both', 'is_default' => true],
                ['name' => 'In Progress', 'type' => 'in_progress', 'color' => '#F59E0B', 'position' => 2, 'applies_to' => 'both'],
                ['name' => 'Review',      'type' => 'review',      'color' => '#8B5CF6', 'position' => 3, 'applies_to' => 'both'],
                ['name' => 'Done',        'type' => 'closed',      'color' => '#10B981', 'position' => 4, 'applies_to' => 'both', 'is_closed' => true],
            ],
            'B2B' => [
                ['name' => 'Backlog',     'type' => 'open',        'color' => '#6B7280', 'position' => 0, 'applies_to' => 'both'],
                ['name' => 'To Do',       'type' => 'open',        'color' => '#3B82F6', 'position' => 1, 'applies_to' => 'both', 'is_default' => true],
                ['name' => 'In Progress', 'type' => 'in_progress', 'color' => '#F59E0B', 'position' => 2, 'applies_to' => 'both'],
                ['name' => 'Review',      'type' => 'review',      'color' => '#8B5CF6', 'position' => 3, 'applies_to' => 'both'],
                ['name' => 'Done',        'type' => 'closed',      'color' => '#10B981', 'position' => 4, 'applies_to' => 'both', 'is_closed' => true],
            ],
            'B2C' => [
                ['name' => 'Backlog',     'type' => 'open',        'color' => '#6B7280', 'position' => 0, 'applies_to' => 'both'],
                ['name' => 'To Do',       'type' => 'open',        'color' => '#3B82F6', 'position' => 1, 'applies_to' => 'both', 'is_default' => true],
                ['name' => 'In Progress', 'type' => 'in_progress', 'color' => '#F59E0B', 'position' => 2, 'applies_to' => 'both'],
                ['name' => 'Review',      'type' => 'review',      'color' => '#8B5CF6', 'position' => 3, 'applies_to' => 'both'],
                ['name' => 'Done',        'type' => 'closed',      'color' => '#10B981', 'position' => 4, 'applies_to' => 'both', 'is_closed' => true],
            ],
        ];

        foreach ($definitions as $spaceName => $statuses) {
            $space = $this->demoSpace($spaceName);
            $space->statuses()->delete();

            foreach ($statuses as $status) {
                Status::create(array_merge([
                    'is_default' => false,
                    'is_closed' => false,
                ], $status, ['space_id' => $space->id]));
            }
        }
    }
}
