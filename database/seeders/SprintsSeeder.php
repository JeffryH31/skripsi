<?php

namespace Database\Seeders;

use App\Models\Sprint;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class SprintsSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $sprints = [
            //  Manufacturing sprints
            [
                'space' => 'Manufacturing',
                'list' => 'Inventory Management',
                'name' => 'Sprint 1 - Inventory Foundation',
                'goal' => 'Setup database schema dan CRUD dasar modul inventory',
                'start_date' => now()->subWeeks(6)->startOfWeek()->toDateString(),
                'end_date' => now()->subWeeks(6)->endOfWeek()->toDateString(),
                'is_active' => false,
                'position' => 0,
            ],
            [
                'space' => 'Manufacturing',
                'list' => 'Inventory Management',
                'name' => 'Sprint 2 - Stock Management',
                'goal' => 'Fitur stok masuk, keluar, dan stock opname',
                'start_date' => now()->subWeeks(5)->startOfWeek()->toDateString(),
                'end_date' => now()->subWeeks(5)->endOfWeek()->toDateString(),
                'is_active' => false,
                'position' => 1,
            ],
            [
                'space' => 'Manufacturing',
                'list' => 'Inventory Management',
                'name' => 'Sprint 3 - Reporting & Integration',
                'goal' => 'Laporan stok dan integrasi barcode scanner',
                'start_date' => now()->subWeeks(4)->startOfWeek()->toDateString(),
                'end_date' => now()->subWeeks(4)->endOfWeek()->toDateString(),
                'is_active' => false,
                'position' => 2,
            ],
            [
                'space' => 'Manufacturing',
                'list' => 'Sensor Dashboard',
                'name' => 'Sprint 1 - IoT Dashboard',
                'goal' => 'Dashboard sensor real-time dan alert sistem',
                'start_date' => now()->subWeeks(3)->startOfWeek()->toDateString(),
                'end_date' => now()->subWeeks(3)->endOfWeek()->toDateString(),
                'is_active' => false,
                'position' => 0,
            ],

            //  B2B sprints
            [
                'space' => 'B2B',
                'list' => 'Client Portal',
                'name' => 'Sprint 1 - Auth & Portal',
                'goal' => 'Autentikasi dan portal utama client B2B',
                'start_date' => now()->subWeeks(6)->startOfWeek()->toDateString(),
                'end_date' => now()->subWeeks(6)->endOfWeek()->toDateString(),
                'is_active' => false,
                'position' => 0,
            ],
            [
                'space' => 'B2B',
                'list' => 'Client Portal',
                'name' => 'Sprint 2 - Order Management',
                'goal' => 'Manajemen order dan tracking pengiriman',
                'start_date' => now()->subWeeks(4)->startOfWeek()->toDateString(),
                'end_date' => now()->subWeeks(4)->endOfWeek()->toDateString(),
                'is_active' => false,
                'position' => 1,
            ],
            [
                'space' => 'B2B',
                'list' => 'Client Portal',
                'name' => 'Sprint 3 - Current Sprint',
                'goal' => 'Invoice sistem dan notifikasi',
                'start_date' => now()->subWeeks(2)->startOfWeek()->toDateString(),
                'end_date' => now()->subWeeks(2)->endOfWeek()->toDateString(),
                'is_active' => true,
                'position' => 2,
            ],

            //  B2C sprints
            [
                'space' => 'B2C',
                'list' => 'Project Catalog',
                'name' => 'Sprint 1 - Catalog Core',
                'goal' => 'API produk, halaman listing, dan filter',
                'start_date' => now()->subWeeks(5)->startOfWeek()->toDateString(),
                'end_date' => now()->subWeeks(5)->endOfWeek()->toDateString(),
                'is_active' => false,
                'position' => 0,
            ],
            [
                'space' => 'B2C',
                'list' => 'Checkout & Payment',
                'name' => 'Sprint 1 - Checkout Flow',
                'goal' => 'Keranjang belanja dan integrasi payment gateway',
                'start_date' => now()->subWeeks(3)->startOfWeek()->toDateString(),
                'end_date' => now()->subWeeks(3)->endOfWeek()->toDateString(),
                'is_active' => false,
                'position' => 0,
            ],
            [
                'space' => 'B2C',
                'list' => 'Checkout & Payment',
                'name' => 'Sprint 2 - Current Sprint',
                'goal' => 'Order management dan notifikasi konsumen',
                'start_date' => now()->subWeeks(1)->startOfWeek()->toDateString(),
                'end_date' => now()->subWeeks(1)->endOfWeek()->toDateString(),
                'is_active' => true,
                'position' => 1,
            ],
        ];

        foreach ($sprints as $s) {
            Sprint::create([
                'space_id' => $this->demoSpace($s['space'])->id,
                'project_id' => $this->demoProject($s['list'])->id,
                'name' => $s['name'],
                'goal' => $s['goal'],
                'start_date' => $s['start_date'],
                'end_date' => $s['end_date'],
                'is_active' => $s['is_active'],
                'position' => $s['position'],
            ]);
        }
    }
}
