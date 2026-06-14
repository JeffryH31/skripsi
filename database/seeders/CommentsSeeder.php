<?php

namespace Database\Seeders;

use App\Models\Comment;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class CommentsSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $jeff = $this->demoUser('admin@example.com');
        $kevin = $this->demoUser('kevin@example.com');
        $christopher = $this->demoUser('christopher@example.com');
        $marvel = $this->demoUser('marvel@example.com');
        $devin = $this->demoUser('devin@example.com');

        $invTask = $this->demoTask('Sistem Manajemen Inventory');
        $authTask = $this->demoTask('Portal Login & Autentikasi');
        $orderTask = $this->demoTask('Order Management Portal');
        $catTask = $this->demoTask('Project Catalog & Listing');
        $checkTask = $this->demoTask('Checkout & Payment Gateway');

        //  Inventory comments
        $c1 = Comment::create([
            'task_id' => $invTask->id,
            'user_id' => $jeff->id,
            'content' => 'Modul inventory ini prioritas utama sprint ini. Pastikan ER diagram sudah final sebelum mulai coding.',
            'created_at' => now()->subWeeks(6)->startOfWeek()->setTime(8, 15),
            'updated_at' => now()->subWeeks(6)->startOfWeek()->setTime(8, 15),
        ]);

        Comment::create([
            'task_id' => $invTask->id,
            'user_id' => $christopher->id,
            'parent_id' => $c1->id,
            'content' => 'Siap Jeff. ER diagram sudah aku finalisasi tadi pagi. Ada 6 tabel utama. Bisa dicek di dokumentasi.',
            'created_at' => now()->subWeeks(6)->startOfWeek()->setTime(10, 30),
            'updated_at' => now()->subWeeks(6)->startOfWeek()->setTime(10, 30),
        ]);

        Comment::create([
            'task_id' => $invTask->id,
            'user_id' => $marvel->id,
            'parent_id' => $c1->id,
            'content' => 'Aku mulai UI master barang setelah migration-nya jadi ya.',
            'created_at' => now()->subWeeks(6)->startOfWeek()->setTime(11, 0),
            'updated_at' => now()->subWeeks(6)->startOfWeek()->setTime(11, 0),
        ]);

        $c2 = Comment::create([
            'task_id' => $invTask->id,
            'user_id' => $kevin->id,
            'content' => 'Untuk stock opname pastikan ada audit trail siapa yang melakukan penyesuaian stok dan alasannya.',
            'created_at' => now()->subWeeks(5)->startOfWeek()->setTime(9, 0),
            'updated_at' => now()->subWeeks(5)->startOfWeek()->setTime(9, 0),
        ]);

        Comment::create([
            'task_id' => $invTask->id,
            'user_id' => $christopher->id,
            'parent_id' => $c2->id,
            'content' => 'Sudah, ada field adjusted_by dan reason di tabel stock_adjustments.',
            'created_at' => now()->subWeeks(5)->startOfWeek()->setTime(10, 0),
            'updated_at' => now()->subWeeks(5)->startOfWeek()->setTime(10, 0),
        ]);

        //  Auth comments
        $c3 = Comment::create([
            'task_id' => $authTask->id,
            'user_id' => $jeff->id,
            'content' => 'Sprint 1 B2B sudah selesai semua. Good job Christopher & Devin! Semua fitur auth sudah pass testing.',
            'created_at' => now()->subWeeks(5)->endOfWeek()->setTime(16, 30),
            'updated_at' => now()->subWeeks(5)->endOfWeek()->setTime(16, 30),
        ]);

        Comment::create([
            'task_id' => $authTask->id,
            'user_id' => $christopher->id,
            'parent_id' => $c3->id,
            'content' => 'Terima kasih Jeff. Untuk 2FA, bisa tambah opsi email OTP selain TOTP di sprint berikutnya.',
            'created_at' => now()->subWeeks(5)->endOfWeek()->setTime(17, 0),
            'updated_at' => now()->subWeeks(5)->endOfWeek()->setTime(17, 0),
        ]);

        //  Order Management comments
        Comment::create([
            'task_id' => $orderTask->id,
            'user_id' => $christopher->id,
            'content' => 'API order list sudah include pagination, filter status, dan sort by tanggal. Ready untuk di-consume frontend.',
            'created_at' => now()->subWeeks(4)->startOfWeek()->setTime(9, 0),
            'updated_at' => now()->subWeeks(4)->startOfWeek()->setTime(9, 0),
        ]);

        Comment::create([
            'task_id' => $orderTask->id,
            'user_id' => $devin->id,
            'content' => 'Halaman list order sudah live di staging. Bisa dicek di staging/orders.',
            'created_at' => now()->subWeeks(4)->startOfWeek()->addDays(2)->setTime(16, 45),
            'updated_at' => now()->subWeeks(4)->startOfWeek()->addDays(2)->setTime(16, 45),
        ]);

        //  Catalog comments
        $c4 = Comment::create([
            'task_id' => $catTask->id,
            'user_id' => $kevin->id,
            'content' => 'Search pakai debounce berapa ms? Jangan terlalu agresif nge-hit API.',
            'created_at' => now()->subWeeks(4)->startOfWeek()->setTime(10, 0),
            'updated_at' => now()->subWeeks(4)->startOfWeek()->setTime(10, 0),
        ]);

        Comment::create([
            'task_id' => $catTask->id,
            'user_id' => $devin->id,
            'parent_id' => $c4->id,
            'content' => '300ms, Kevin. Sudah test dengan Lighthouse, score performance 94.',
            'created_at' => now()->subWeeks(4)->startOfWeek()->setTime(10, 45),
            'updated_at' => now()->subWeeks(4)->startOfWeek()->setTime(10, 45),
        ]);

        //  Checkout comments
        $c5 = Comment::create([
            'task_id' => $checkTask->id,
            'user_id' => $christopher->id,
            'content' => 'Midtrans sandbox sudah bisa test semua payment method. Butuh API key production dari Finance untuk go-live.',
            'created_at' => now()->subWeeks(3)->startOfWeek()->setTime(14, 0),
            'updated_at' => now()->subWeeks(3)->startOfWeek()->setTime(14, 0),
        ]);

        Comment::create([
            'task_id' => $checkTask->id,
            'user_id' => $jeff->id,
            'parent_id' => $c5->id,
            'content' => 'Sudah aku minta ke Finance tadi pagi. Key production akan dikirim besok via email.',
            'created_at' => now()->subWeeks(3)->startOfWeek()->setTime(15, 30),
            'updated_at' => now()->subWeeks(3)->startOfWeek()->setTime(15, 30),
        ]);

        Comment::create([
            'task_id' => $checkTask->id,
            'user_id' => $marvel->id,
            'content' => 'Multi-step form sudah progress 50%. Step 1 (alamat) dan Step 2 (pengiriman) sudah jalan.',
            'created_at' => now()->subWeeks(2)->startOfWeek()->setTime(16, 0),
            'updated_at' => now()->subWeeks(2)->startOfWeek()->setTime(16, 0),
        ]);
    }
}
