<?php

namespace Database\Seeders;

use App\Models\Project;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class ProjectsSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $jeff = $this->demoUser('admin@example.com');
        $kevin = $this->demoUser('kevin@example.com');
        $christopher = $this->demoUser('christopher@example.com');
        $marvel = $this->demoUser('marvel@example.com');
        $devin = $this->demoUser('devin@example.com');

        $lists = [
            //  Manufacturing / ERP System
            [
                'name' => 'Inventory Management',
                'space' => 'Manufacturing',
                'folder' => 'ERP System',
                'position' => 0,
                'created_by' => $jeff->id,
                'status' => ['Manufacturing', 'In Progress'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$kevin,       'project_manager'],
                    [$christopher, 'development_team'],
                    [$marvel,      'development_team'],
                    [$devin,       'development_team'],
                ],
            ],
            [
                'name' => 'Production Planning',
                'space' => 'Manufacturing',
                'folder' => 'ERP System',
                'position' => 1,
                'created_by' => $jeff->id,
                'status' => ['Manufacturing', 'To Do'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$kevin,       'project_manager'],
                    [$christopher, 'development_team'],
                    [$marvel,      'development_team'],
                ],
            ],
            [
                'name' => 'Quality Control',
                'space' => 'Manufacturing',
                'folder' => 'ERP System',
                'position' => 2,
                'created_by' => $jeff->id,
                'status' => ['Manufacturing', 'Backlog'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$kevin,       'project_manager'],
                    [$devin,       'development_team'],
                ],
            ],
            //  Manufacturing / IoT & Monitoring
            [
                'name' => 'Sensor Dashboard',
                'space' => 'Manufacturing',
                'folder' => 'IoT & Monitoring',
                'position' => 0,
                'created_by' => $kevin->id,
                'status' => ['Manufacturing', 'In Progress'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$kevin,       'project_manager'],
                    [$christopher, 'development_team'],
                    [$marvel,      'development_team'],
                ],
            ],
            //  B2B / Client Portal
            [
                'name' => 'Client Portal',
                'space' => 'B2B',
                'folder' => 'Client Portal',
                'position' => 0,
                'created_by' => $jeff->id,
                'status' => ['B2B', 'In Progress'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$kevin,       'project_manager'],
                    [$christopher, 'development_team'],
                    [$marvel,      'development_team'],
                    [$devin,       'development_team'],
                ],
            ],
            [
                'name' => 'API Integration',
                'space' => 'B2B',
                'folder' => 'Client Portal',
                'position' => 1,
                'created_by' => $kevin->id,
                'status' => ['B2B', 'To Do'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$kevin,       'project_manager'],
                    [$christopher, 'development_team'],
                    [$devin,       'development_team'],
                ],
            ],
            //  B2C / E-Commerce
            [
                'name' => 'Project Catalog',
                'space' => 'B2C',
                'folder' => 'E-Commerce',
                'position' => 0,
                'created_by' => $jeff->id,
                'status' => ['B2C', 'In Progress'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$kevin,       'project_manager'],
                    [$christopher, 'development_team'],
                    [$marvel,      'development_team'],
                    [$devin,       'development_team'],
                ],
            ],
            [
                'name' => 'Checkout & Payment',
                'space' => 'B2C',
                'folder' => 'E-Commerce',
                'position' => 1,
                'created_by' => $kevin->id,
                'status' => ['B2C', 'In Progress'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$kevin,       'project_manager'],
                    [$christopher, 'development_team'],
                    [$marvel,      'development_team'],
                ],
            ],
            //  B2C / Mobile App
            [
                'name' => 'Android App',
                'space' => 'B2C',
                'folder' => 'Mobile App',
                'position' => 0,
                'created_by' => $jeff->id,
                'status' => ['B2C', 'To Do'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$kevin,       'project_manager'],
                    [$marvel,      'development_team'],
                    [$devin,       'development_team'],
                ],
            ],
            [
                'name' => 'iOS App',
                'space' => 'B2C',
                'folder' => 'Mobile App',
                'position' => 1,
                'created_by' => $jeff->id,
                'status' => ['B2C', 'Backlog'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$kevin,       'project_manager'],
                    [$marvel,      'development_team'],
                    [$devin,       'development_team'],
                ],
            ],
            [
                'name' => 'Customer Support',
                'space' => 'B2C',
                'folder' => 'Mobile App',
                'position' => 2,
                'created_by' => $jeff->id,
                'status' => ['B2C', 'Backlog'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$kevin,       'project_manager'],
                    [$christopher, 'development_team'],
                ],
            ],
            [
                'name' => 'Loyalty Program',
                'space' => 'B2C',
                'folder' => 'Mobile App',
                'position' => 3,
                'created_by' => $jeff->id,
                'status' => ['B2C', 'Backlog'],
                'members' => [
                    [$jeff,        'project_owner'],
                    [$devin,       'development_team'],
                ],
            ],
        ];

        foreach ($lists as $definition) {
            $list = Project::create([
                'name' => $definition['name'],
                'space_id' => $this->demoSpace($definition['space'])->id,
                'folder_id' => $this->demoFolder($definition['folder'])->id,
                'position' => $definition['position'],
                'created_by' => $definition['created_by'],
                'status_id' => $this->demoStatus($definition['status'][0], $definition['status'][1])->id,
            ]);

            foreach ($definition['members'] as [$user, $role]) {
                $list->addMember($user, $role);
            }
        }
    }
}
