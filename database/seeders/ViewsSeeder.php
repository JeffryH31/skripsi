<?php

namespace Database\Seeders;

use App\Models\View;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class ViewsSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $jeff = $this->demoUser('admin@example.com');
        $kevin = $this->demoUser('kevin@example.com');
        $christopher = $this->demoUser('christopher@example.com');
        $marvel = $this->demoUser('marvel@example.com');
        $devin = $this->demoUser('devin@example.com');

        $views = [
            //  Manufacturing space views
            ['space' => 'Manufacturing', 'project' => null,                'user' => $jeff,        'name' => 'All Tasks',        'type' => 'list',     'is_default' => true,  'position' => 0],
            ['space' => 'Manufacturing', 'project' => null,                'user' => $kevin,       'name' => 'Board View',       'type' => 'board',    'is_default' => false, 'position' => 1],

            //  Inventory Management project views
            ['space' => null, 'project' => 'Inventory Management',        'user' => $jeff,        'name' => 'Board',            'type' => 'board',    'is_default' => true,  'position' => 0],
            ['space' => null, 'project' => 'Inventory Management',        'user' => $kevin,       'name' => 'Gantt',            'type' => 'gantt',    'is_default' => false, 'position' => 1],
            ['space' => null, 'project' => 'Inventory Management',        'user' => $christopher, 'name' => 'My Tasks',         'type' => 'list',     'is_default' => false, 'position' => 2],

            //  B2B space views
            ['space' => 'B2B',  'project' => null,                        'user' => $jeff,        'name' => 'All B2B Tasks',    'type' => 'list',     'is_default' => true,  'position' => 0],
            ['space' => 'B2B',  'project' => null,                        'user' => $kevin,       'name' => 'B2B Board',        'type' => 'board',    'is_default' => false, 'position' => 1],

            //  Client Portal project views
            ['space' => null, 'project' => 'Client Portal',               'user' => $kevin,       'name' => 'Board',            'type' => 'board',    'is_default' => true,  'position' => 0],
            ['space' => null, 'project' => 'Client Portal',               'user' => $christopher, 'name' => 'My Tasks',         'type' => 'list',     'is_default' => false, 'position' => 1],

            //  B2C space views
            ['space' => 'B2C',  'project' => null,                        'user' => $jeff,        'name' => 'All B2C Tasks',    'type' => 'list',     'is_default' => true,  'position' => 0],
            ['space' => 'B2C',  'project' => null,                        'user' => $kevin,       'name' => 'B2C Board',        'type' => 'board',    'is_default' => false, 'position' => 1],
            ['space' => 'B2C',  'project' => null,                        'user' => $kevin,       'name' => 'B2C Calendar',     'type' => 'calendar', 'is_default' => false, 'position' => 2],

            //  Project Catalog project views
            ['space' => null, 'project' => 'Project Catalog',             'user' => $jeff,        'name' => 'Board',            'type' => 'board',    'is_default' => true,  'position' => 0],
            ['space' => null, 'project' => 'Project Catalog',             'user' => $marvel,      'name' => 'My Tasks',         'type' => 'list',     'is_default' => false, 'position' => 1],

            //  Checkout & Payment project views
            ['space' => null, 'project' => 'Checkout & Payment',          'user' => $jeff,        'name' => 'Board',            'type' => 'board',    'is_default' => true,  'position' => 0],
            ['space' => null, 'project' => 'Checkout & Payment',          'user' => $kevin,       'name' => 'Gantt',            'type' => 'gantt',    'is_default' => false, 'position' => 1],
        ];

        foreach ($views as $v) {
            View::create([
                'space_id' => $v['space'] ? $this->demoSpace($v['space'])->id : null,
                'project_id' => $v['project'] ? $this->demoProject($v['project'])->id : null,
                'user_id' => $v['user']->id,
                'name' => $v['name'],
                'type' => $v['type'],
                'is_default' => $v['is_default'],
                'position' => $v['position'],
                'is_private' => false,
            ]);
        }
    }
}
