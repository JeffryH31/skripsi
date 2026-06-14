<?php

namespace Database\Seeders;

use App\Models\TimeEntry;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class TimeEntriesSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $now = now();

        $entries = [
            //  Manufacturing: Inventory Management
            ['subtask' => 'Analisis kebutuhan & ER diagram inventory',  'user' => 'admin@example.com',        'dur' => 165, 'offset_weeks' => 6, 'offset_days' => 0, 'hour' => 8,  'bill' => false],
            ['subtask' => 'Buat migration & model barang, kategori, satuan', 'user' => 'admin@example.com',   'dur' => 210, 'offset_weeks' => 6, 'offset_days' => 0, 'hour' => 11, 'bill' => false],
            ['subtask' => 'API CRUD master barang',                     'user' => 'christopher@example.com',  'dur' => 315, 'offset_weeks' => 6, 'offset_days' => 1, 'hour' => 8,  'bill' => false],
            ['subtask' => 'UI halaman master barang',                   'user' => 'marvel@example.com',       'dur' => 200, 'offset_weeks' => 6, 'offset_days' => 1, 'hour' => 13, 'bill' => false],
            ['subtask' => 'UI halaman master barang',                   'user' => 'marvel@example.com',       'dur' => 175, 'offset_weeks' => 6, 'offset_days' => 2, 'hour' => 8,  'bill' => false],
            ['subtask' => 'API stok masuk & keluar',                    'user' => 'christopher@example.com',  'dur' => 390, 'offset_weeks' => 5, 'offset_days' => 0, 'hour' => 8,  'bill' => false],
            ['subtask' => 'Fitur stock opname',                         'user' => 'christopher@example.com',  'dur' => 240, 'offset_weeks' => 5, 'offset_days' => 1, 'hour' => 8,  'bill' => false],
            ['subtask' => 'Fitur stock opname',                         'user' => 'christopher@example.com',  'dur' => 210, 'offset_weeks' => 5, 'offset_days' => 2, 'hour' => 8,  'bill' => false],
            ['subtask' => 'Laporan stok real-time (PDF & Excel)',        'user' => 'marvel@example.com',       'dur' => 120, 'offset_weeks' => 4, 'offset_days' => 0, 'hour' => 8,  'bill' => false],

            //  B2B: Client Portal
            ['subtask' => 'Setup autentikasi Laravel Sanctum',          'user' => 'christopher@example.com',  'dur' => 225, 'offset_weeks' => 6, 'offset_days' => 0, 'hour' => 8,  'bill' => true],
            ['subtask' => 'Role-based access control (RBAC)',           'user' => 'christopher@example.com',  'dur' => 285, 'offset_weeks' => 6, 'offset_days' => 0, 'hour' => 13, 'bill' => true],
            ['subtask' => 'Halaman login & lupa password UI',           'user' => 'devin@example.com',        'dur' => 240, 'offset_weeks' => 6, 'offset_days' => 2, 'hour' => 8,  'bill' => true],
            ['subtask' => 'API list order dengan pagination & filter',  'user' => 'christopher@example.com',  'dur' => 330, 'offset_weeks' => 4, 'offset_days' => 0, 'hour' => 8,  'bill' => true],
            ['subtask' => 'Halaman list order dengan badge status',     'user' => 'devin@example.com',        'dur' => 200, 'offset_weeks' => 4, 'offset_days' => 1, 'hour' => 8,  'bill' => true],
            ['subtask' => 'Halaman list order dengan badge status',     'user' => 'devin@example.com',        'dur' => 160, 'offset_weeks' => 4, 'offset_days' => 2, 'hour' => 8,  'bill' => true],
            ['subtask' => 'API detail order & timeline status',         'user' => 'marvel@example.com',       'dur' => 120, 'offset_weeks' => 2, 'offset_days' => 0, 'hour' => 8,  'bill' => true],

            //  B2C: Project Catalog
            ['subtask' => 'API produk & kategori',                      'user' => 'devin@example.com',        'dur' => 315, 'offset_weeks' => 5, 'offset_days' => 0, 'hour' => 8,  'bill' => false],
            ['subtask' => 'Halaman project listing (grid + lazy load)', 'user' => 'marvel@example.com',       'dur' => 210, 'offset_weeks' => 5, 'offset_days' => 1, 'hour' => 8,  'bill' => false],
            ['subtask' => 'Halaman project listing (grid + lazy load)', 'user' => 'marvel@example.com',       'dur' => 150, 'offset_weeks' => 5, 'offset_days' => 2, 'hour' => 8,  'bill' => false],
            ['subtask' => 'Komponen filter multi-parameter',            'user' => 'marvel@example.com',       'dur' => 120, 'offset_weeks' => 4, 'offset_days' => 0, 'hour' => 8,  'bill' => false],

            //  B2C: Checkout & Payment
            ['subtask' => 'Keranjang belanja (tambah, update qty, hapus)', 'user' => 'christopher@example.com', 'dur' => 330, 'offset_weeks' => 3, 'offset_days' => 0, 'hour' => 8,  'bill' => false],
            ['subtask' => 'Integrasi Midtrans payment gateway',         'user' => 'christopher@example.com',  'dur' => 240, 'offset_weeks' => 3, 'offset_days' => 1, 'hour' => 8,  'bill' => false],
            ['subtask' => 'Integrasi Midtrans payment gateway',         'user' => 'christopher@example.com',  'dur' => 240, 'offset_weeks' => 3, 'offset_days' => 2, 'hour' => 8,  'bill' => false],
            ['subtask' => 'Multi-step checkout form',                   'user' => 'marvel@example.com',       'dur' => 180, 'offset_weeks' => 1, 'offset_days' => 0, 'hour' => 8,  'bill' => false],
            ['subtask' => 'Multi-step checkout form',                   'user' => 'marvel@example.com',       'dur' => 180, 'offset_weeks' => 1, 'offset_days' => 1, 'hour' => 8,  'bill' => false],

            //  Kevin as PM billable entries
            ['subtask' => 'Setup autentikasi Laravel Sanctum',          'user' => 'kevin@example.com',        'dur' => 60,  'offset_weeks' => 6, 'offset_days' => 0, 'hour' => 14, 'bill' => true],
            ['subtask' => 'API list order dengan pagination & filter',  'user' => 'kevin@example.com',        'dur' => 60,  'offset_weeks' => 4, 'offset_days' => 0, 'hour' => 14, 'bill' => true],
        ];

        foreach ($entries as $e) {
            $startOfWeek = $now->copy()->subWeeks($e['offset_weeks'])->startOfWeek();
            $start = $startOfWeek->copy()->addDays($e['offset_days'])->setTime($e['hour'], 0, 0);

            TimeEntry::create([
                'subtask_id' => $this->demoSubtask($e['subtask'])->id,
                'user_id' => $this->demoUser($e['user'])->id,
                'duration' => $e['dur'],
                'started_at' => $start,
                'ended_at' => $start->copy()->addMinutes($e['dur']),
                'is_billable' => $e['bill'],
                'is_running' => false,
            ]);
        }
    }
}
