<?php

namespace Database\Seeders;

use App\Models\Task;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class TasksSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $jeff = $this->demoUser('admin@example.com');
        $kevin = $this->demoUser('kevin@example.com');
        $christopher = $this->demoUser('christopher@example.com');
        $marvel = $this->demoUser('marvel@example.com');
        $devin = $this->demoUser('devin@example.com');

        // ═══════════════════════════════════════════════════════════════════
        // MANUFACTURING – Inventory Management
        // ═══════════════════════════════════════════════════════════════════
        $this->createTask([
            'name' => 'Sistem Manajemen Inventory',
            'description' => "Membangun modul inventory lengkap:\n- Master barang\n- Stok masuk/keluar\n- Stock opname\n- Laporan stok real-time\n- Barcode scanner",
            'list' => 'Inventory Management',
            'status' => ['Manufacturing', 'In Progress'],
            'priority_level' => 1,
            'created_by' => $jeff->id,
            'start_date' => now()->subWeeks(6)->toDateString(),
            'due_date' => now()->subWeeks(3)->toDateString(),
            'time_estimate' => 4800,
            'position' => 0,
            'assignees' => [$christopher->id, $marvel->id],
            'labels' => ['Feature'],
        ]);

        $this->createTask([
            'name' => 'Database Schema & Migration',
            'description' => "Desain dan implementasi schema database untuk modul inventory:\n- ERD diagram\n- Migration files\n- Seeding data awal",
            'list' => 'Inventory Management',
            'status' => ['Manufacturing', 'Done'],
            'priority_level' => 1,
            'created_by' => $kevin->id,
            'start_date' => now()->subWeeks(7)->toDateString(),
            'due_date' => now()->subWeeks(6)->toDateString(),
            'time_estimate' => 960,
            'position' => 1,
            'assignees' => [$christopher->id],
            'labels' => ['Feature'],
        ]);

        $this->createTask([
            'name' => 'Laporan Stok & Export',
            'description' => "Fitur laporan stok:\n- Laporan stok real-time\n- Export PDF & Excel\n- Filter per kategori",
            'list' => 'Inventory Management',
            'status' => ['Manufacturing', 'To Do'],
            'priority_level' => 2,
            'created_by' => $jeff->id,
            'start_date' => now()->subWeeks(2)->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'time_estimate' => 720,
            'position' => 2,
            'assignees' => [$marvel->id],
            'labels' => ['Feature', 'Documentation'],
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // MANUFACTURING – Sensor Dashboard
        // ═══════════════════════════════════════════════════════════════════
        $this->createTask([
            'name' => 'IoT Sensor Dashboard',
            'description' => "Dashboard monitoring sensor pabrik:\n- Sensor suhu, kelembaban\n- Alert threshold\n- History log",
            'list' => 'Sensor Dashboard',
            'status' => ['Manufacturing', 'In Progress'],
            'priority_level' => 2,
            'created_by' => $kevin->id,
            'start_date' => now()->subWeeks(3)->toDateString(),
            'due_date' => now()->toDateString(),
            'time_estimate' => 1440,
            'position' => 0,
            'assignees' => [$christopher->id, $devin->id],
            'labels' => ['Feature'],
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // B2B – Client Portal
        // ═══════════════════════════════════════════════════════════════════
        $this->createTask([
            'name' => 'Portal Login & Autentikasi',
            'description' => "Sistem autentikasi portal B2B:\n- Login dengan email/password\n- Role-based access control\n- 2FA support",
            'list' => 'Client Portal',
            'status' => ['B2B', 'Done'],
            'priority_level' => 1,
            'created_by' => $jeff->id,
            'start_date' => now()->subWeeks(6)->toDateString(),
            'due_date' => now()->subWeeks(5)->toDateString(),
            'time_estimate' => 1440,
            'position' => 0,
            'assignees' => [$christopher->id, $devin->id],
            'labels' => ['Feature', 'Security'],
        ]);

        $this->createTask([
            'name' => 'Order Management Portal',
            'description' => "Client B2B bisa kelola order:\n- List order dengan filter\n- Detail order dengan timeline\n- Download invoice PDF",
            'list' => 'Client Portal',
            'status' => ['B2B', 'In Progress'],
            'priority_level' => 1,
            'created_by' => $jeff->id,
            'start_date' => now()->subWeeks(4)->toDateString(),
            'due_date' => now()->subWeeks(2)->toDateString(),
            'time_estimate' => 2160,
            'position' => 1,
            'assignees' => [$christopher->id, $marvel->id, $devin->id],
            'labels' => ['Feature', 'UI/UX'],
        ]);

        $this->createTask([
            'name' => 'Notifikasi & Alert',
            'description' => "Sistem notifikasi untuk B2B:\n- Email notifikasi status order\n- Push notification browser\n- Notifikasi WhatsApp",
            'list' => 'Client Portal',
            'status' => ['B2B', 'To Do'],
            'priority_level' => 2,
            'created_by' => $kevin->id,
            'start_date' => now()->subWeeks(2)->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'time_estimate' => 720,
            'position' => 2,
            'assignees' => [$devin->id],
            'labels' => ['Feature'],
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // B2C – Project Catalog
        // ═══════════════════════════════════════════════════════════════════
        $this->createTask([
            'name' => 'Project Catalog & Listing',
            'description' => "Halaman katalog produk:\n- Grid & list view\n- Filter multi-parameter\n- Sorting produk\n- Breadcrumb navigasi",
            'list' => 'Project Catalog',
            'status' => ['B2C', 'In Progress'],
            'priority_level' => 1,
            'created_by' => $jeff->id,
            'start_date' => now()->subWeeks(5)->toDateString(),
            'due_date' => now()->subWeeks(3)->toDateString(),
            'time_estimate' => 1920,
            'position' => 0,
            'assignees' => [$marvel->id, $devin->id],
            'labels' => ['Feature', 'UI/UX'],
        ]);

        $this->createTask([
            'name' => 'Project Detail & Search',
            'description' => "Halaman detail produk dan pencarian:\n- Galeri foto produk\n- Spesifikasi produk\n- Review & rating\n- Search auto-suggest",
            'list' => 'Project Catalog',
            'status' => ['B2C', 'In Progress'],
            'priority_level' => 2,
            'created_by' => $kevin->id,
            'start_date' => now()->subWeeks(3)->toDateString(),
            'due_date' => now()->toDateString(),
            'time_estimate' => 1440,
            'position' => 1,
            'assignees' => [$marvel->id],
            'labels' => ['Feature', 'UI/UX'],
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // B2C – Checkout & Payment
        // ═══════════════════════════════════════════════════════════════════
        $this->createTask([
            'name' => 'Checkout & Payment Gateway',
            'description' => "Alur checkout end-to-end:\n- Keranjang belanja\n- Kalkulasi ongkir\n- Multi-step checkout\n- Integrasi Midtrans",
            'list' => 'Checkout & Payment',
            'status' => ['B2C', 'In Progress'],
            'priority_level' => 1,
            'created_by' => $jeff->id,
            'start_date' => now()->subWeeks(3)->toDateString(),
            'due_date' => now()->subWeek()->toDateString(),
            'time_estimate' => 3360,
            'position' => 0,
            'assignees' => [$christopher->id, $marvel->id, $devin->id],
            'labels' => ['Feature', 'Security'],
        ]);

        $this->createTask([
            'name' => 'Order Management Konsumen',
            'description' => "Halaman pesanan untuk konsumen:\n- List pesanan\n- Detail pesanan + tracking\n- Request pembatalan\n- Download invoice",
            'list' => 'Checkout & Payment',
            'status' => ['B2C', 'To Do'],
            'priority_level' => 2,
            'created_by' => $kevin->id,
            'start_date' => now()->subWeeks(1)->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'time_estimate' => 960,
            'position' => 1,
            'assignees' => [$devin->id],
            'labels' => ['Feature'],
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // B2C – Android App
        // ═══════════════════════════════════════════════════════════════════
        $this->createTask([
            'name' => 'Android App E-Commerce',
            'description' => "Aplikasi Android untuk e-commerce:\n- Autentikasi\n- Browse produk\n- Cart & checkout\n- Order tracking\n- Push notification",
            'list' => 'Android App',
            'status' => ['B2C', 'To Do'],
            'priority_level' => 2,
            'created_by' => $jeff->id,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addWeeks(3)->toDateString(),
            'time_estimate' => 2400,
            'position' => 0,
            'assignees' => [$marvel->id, $devin->id],
            'labels' => ['Feature'],
        ]);
    }

    private function createTask(array $definition): void
    {
        $task = Task::create([
            'name' => $definition['name'],
            'description' => $definition['description'],
            'project_id' => $this->demoProject($definition['list'])->id,
            'status_id' => $this->demoStatus($definition['status'][0], $definition['status'][1])->id,
            'priority_level' => $definition['priority_level'],
            'created_by' => $definition['created_by'],
            'start_date' => $definition['start_date'] ?? null,
            'due_date' => $definition['due_date'] ?? null,
            'time_estimate' => $definition['time_estimate'] ?? null,
            'position' => $definition['position'],
        ]);

        foreach ($definition['assignees'] as $userId) {
            $task->assignees()->attach([
                $userId => ['assigned_by' => $definition['created_by']],
            ]);
        }

        if (! empty($definition['labels'])) {
            $task->labels()->attach(
                collect($definition['labels'])
                    ->map(fn (string $l) => $this->demoLabel($l)->id)
                    ->all()
            );
        }
    }
}
