<?php

namespace Database\Seeders;

use App\Models\Activity;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class ActivitiesSeeder extends Seeder
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

        $act = function (
            $user, $subject, string $action,
            array $props, string $ts,
            array $changes = []
        ) use ($workspace): void {
            Activity::create([
                'workspace_id' => $workspace->id,
                'user_id' => $user->id,
                'subject_type' => get_class($subject),
                'subject_id' => $subject->id,
                'action' => $action,
                'properties' => $props,
                'changes' => $changes ?: null,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]);
        };

        $invTask = $this->demoTask('Sistem Manajemen Inventory');
        $authTask = $this->demoTask('Portal Login & Autentikasi');
        $orderTask = $this->demoTask('Order Management Portal');
        $catTask = $this->demoTask('Project Catalog & Listing');
        $checkTask = $this->demoTask('Checkout & Payment Gateway');

        // Task creation activities
        $act($jeff, $invTask, 'created', ['name' => $invTask->name], now()->subWeeks(6)->startOfWeek()->setTime(8, 5)->toDateTimeString());
        $act($jeff, $invTask, 'assigned', ['name' => $invTask->name, 'assignees' => 'Christopher, Marvel'], now()->subWeeks(6)->startOfWeek()->setTime(8, 6)->toDateTimeString());
        $act($jeff, $authTask, 'created', ['name' => $authTask->name], now()->subWeeks(6)->startOfWeek()->setTime(8, 10)->toDateTimeString());
        $act($jeff, $authTask, 'assigned', ['name' => $authTask->name, 'assignees' => 'Christopher, Devin'], now()->subWeeks(6)->startOfWeek()->setTime(8, 11)->toDateTimeString());
        $act($jeff, $catTask, 'created', ['name' => $catTask->name], now()->subWeeks(5)->startOfWeek()->setTime(8, 5)->toDateTimeString());
        $act($jeff, $checkTask, 'created', ['name' => $checkTask->name], now()->subWeeks(3)->startOfWeek()->setTime(8, 5)->toDateTimeString());

        // Subtask activities - Inventory
        $inv1Sub = $this->demoSubtask('Analisis kebutuhan & ER diagram inventory');
        $act($christopher, $inv1Sub, 'created', ['name' => $inv1Sub->name], now()->subWeeks(6)->startOfWeek()->setTime(8, 10)->toDateTimeString());
        $act($christopher, $inv1Sub, 'completed', ['name' => $inv1Sub->name], now()->subWeeks(6)->startOfWeek()->setTime(10, 45)->toDateTimeString());

        $inv2Sub = $this->demoSubtask('Buat migration & model barang, kategori, satuan');
        $act($christopher, $inv2Sub, 'created', ['name' => $inv2Sub->name], now()->subWeeks(6)->startOfWeek()->setTime(11, 5)->toDateTimeString());
        $act($christopher, $inv2Sub, 'completed', ['name' => $inv2Sub->name], now()->subWeeks(6)->startOfWeek()->setTime(16, 30)->toDateTimeString());

        // Subtask activities - Auth
        $auth1Sub = $this->demoSubtask('Setup autentikasi Laravel Sanctum');
        $act($christopher, $auth1Sub, 'created', ['name' => $auth1Sub->name], now()->subWeeks(6)->startOfWeek()->setTime(8, 12)->toDateTimeString());
        $act($christopher, $auth1Sub, 'completed', ['name' => $auth1Sub->name], now()->subWeeks(6)->startOfWeek()->setTime(11, 45)->toDateTimeString());

        $auth2Sub = $this->demoSubtask('Role-based access control (RBAC)');
        $act($christopher, $auth2Sub, 'completed', ['name' => $auth2Sub->name], now()->subWeeks(6)->startOfWeek()->addDay()->setTime(16, 30)->toDateTimeString());

        $auth3Sub = $this->demoSubtask('Halaman login & lupa password UI');
        $act($devin, $auth3Sub, 'completed', ['name' => $auth3Sub->name], now()->subWeeks(6)->startOfWeek()->addDays(3)->setTime(16, 0)->toDateTimeString());

        $act($jeff, $authTask, 'updated', ['name' => $authTask->name],
            now()->subWeeks(5)->endOfWeek()->setTime(16, 0)->toDateTimeString(),
            ['status' => ['old' => 'In Progress', 'new' => 'Done']]);

        // Order Management activities
        $act($jeff, $orderTask, 'created', ['name' => $orderTask->name], now()->subWeeks(4)->startOfWeek()->setTime(8, 5)->toDateTimeString());
        $act($jeff, $orderTask, 'assigned', ['name' => $orderTask->name, 'assignees' => 'Christopher, Marvel, Devin'], now()->subWeeks(4)->startOfWeek()->setTime(8, 6)->toDateTimeString());

        $ord1Sub = $this->demoSubtask('API list order dengan pagination & filter');
        $act($christopher, $ord1Sub, 'created', ['name' => $ord1Sub->name], now()->subWeeks(4)->startOfWeek()->setTime(8, 10)->toDateTimeString());
        $act($christopher, $ord1Sub, 'completed', ['name' => $ord1Sub->name], now()->subWeeks(4)->startOfWeek()->setTime(13, 30)->toDateTimeString());

        $ord2Sub = $this->demoSubtask('Halaman list order dengan badge status');
        $act($devin, $ord2Sub, 'completed', ['name' => $ord2Sub->name], now()->subWeeks(4)->startOfWeek()->addDays(2)->setTime(16, 45)->toDateTimeString());

        // Catalog activities
        $cat1Sub = $this->demoSubtask('API produk & kategori');
        $act($devin, $cat1Sub, 'created', ['name' => $cat1Sub->name], now()->subWeeks(5)->startOfWeek()->setTime(8, 10)->toDateTimeString());
        $act($devin, $cat1Sub, 'completed', ['name' => $cat1Sub->name], now()->subWeeks(5)->startOfWeek()->setTime(13, 15)->toDateTimeString());

        // Checkout activities
        $act($jeff, $checkTask, 'updated', ['name' => $checkTask->name],
            now()->subWeeks(3)->startOfWeek()->setTime(8, 5)->toDateTimeString(),
            ['status' => ['old' => 'To Do', 'new' => 'In Progress']]);

        $chk1Sub = $this->demoSubtask('Keranjang belanja (tambah, update qty, hapus)');
        $act($christopher, $chk1Sub, 'created', ['name' => $chk1Sub->name], now()->subWeeks(3)->startOfWeek()->setTime(8, 5)->toDateTimeString());
        $act($christopher, $chk1Sub, 'completed', ['name' => $chk1Sub->name], now()->subWeeks(3)->startOfWeek()->setTime(13, 30)->toDateTimeString());
    }
}
