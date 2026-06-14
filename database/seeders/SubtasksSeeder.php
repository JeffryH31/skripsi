<?php

namespace Database\Seeders;

use App\Models\Subtask;
use Database\Seeders\Concerns\InteractsWithMisDepartmentDemo;
use Illuminate\Database\Seeder;

class SubtasksSeeder extends Seeder
{
    use InteractsWithMisDepartmentDemo;

    public function run(): void
    {
        $jeff = $this->demoUser('admin@example.com');
        $kevin = $this->demoUser('kevin@example.com');
        $christopher = $this->demoUser('christopher@example.com');
        $marvel = $this->demoUser('marvel@example.com');
        $devin = $this->demoUser('devin@example.com');

        $mfgDone = $this->demoStatus('Manufacturing', 'Done');
        $mfgProgress = $this->demoStatus('Manufacturing', 'In Progress');
        $mfgTodo = $this->demoStatus('Manufacturing', 'To Do');
        $mfgBacklog = $this->demoStatus('Manufacturing', 'Backlog');

        $b2bDone = $this->demoStatus('B2B', 'Done');
        $b2bProgress = $this->demoStatus('B2B', 'In Progress');
        $b2bTodo = $this->demoStatus('B2B', 'To Do');
        $b2bBacklog = $this->demoStatus('B2B', 'Backlog');

        $b2cDone = $this->demoStatus('B2C', 'Done');
        $b2cProgress = $this->demoStatus('B2C', 'In Progress');
        $b2cTodo = $this->demoStatus('B2C', 'To Do');
        $b2cBacklog = $this->demoStatus('B2C', 'Backlog');

        // sprints
        $s1Inv = $this->demoSprint('Sprint 1 - Inventory Foundation');
        $s2Inv = $this->demoSprint('Sprint 2 - Stock Management');
        $s3Inv = $this->demoSprint('Sprint 3 - Reporting & Integration');
        $s1B2B = $this->demoSprint('Sprint 1 - Auth & Portal');
        $s2B2B = $this->demoSprint('Sprint 2 - Order Management');
        $s3B2B = $this->demoSprint('Sprint 3 - Current Sprint');
        $s1B2C = $this->demoSprint('Sprint 1 - Catalog Core');
        $s1Pay = $this->demoSprint('Sprint 1 - Checkout Flow');
        $s2Pay = $this->demoSprint('Sprint 2 - Current Sprint');

        // ═══════════════════════════════════════════════════════════════════
        // MANUFACTURING: Sistem Manajemen Inventory (CPM demo task)
        // ═══════════════════════════════════════════════════════════════════
        $invTask = $this->demoTask('Sistem Manajemen Inventory');

        $inv1 = $this->createSubtask([
            'name' => 'Analisis kebutuhan & ER diagram inventory',
            'task_id' => $invTask->id,
            'status_id' => $mfgDone->id,
            'priority_level' => 1,
            'sprint_id' => $s1Inv->id,
            'time_estimate' => 180,
            'optimistic_estimate' => 120,
            'most_likely_estimate' => 180,
            'pessimistic_estimate' => 300,
            'start_date' => now()->subWeeks(6)->startOfWeek()->setTime(8, 0),
            'due_date' => now()->subWeeks(6)->startOfWeek()->setTime(11, 0),
            'completed_at' => now()->subWeeks(6)->startOfWeek()->setTime(10, 45),
            'time_spent' => 165,
            'progress' => 100,
            'position' => 0,
            'created_by' => $christopher->id,
        ], [$christopher->id], ['Documentation']);

        $inv2 = $this->createSubtask([
            'name' => 'Buat migration & model barang, kategori, satuan',
            'task_id' => $invTask->id,
            'status_id' => $mfgDone->id,
            'priority_level' => 1,
            'sprint_id' => $s1Inv->id,
            'time_estimate' => 240,
            'optimistic_estimate' => 180,
            'most_likely_estimate' => 240,
            'pessimistic_estimate' => 360,
            'start_date' => now()->subWeeks(6)->startOfWeek()->setTime(11, 0),
            'due_date' => now()->subWeeks(6)->startOfWeek()->setTime(17, 0),
            'completed_at' => now()->subWeeks(6)->startOfWeek()->setTime(16, 30),
            'time_spent' => 210,
            'progress' => 100,
            'position' => 1,
            'created_by' => $christopher->id,
        ], [$christopher->id], ['Feature']);

        $inv3 = $this->createSubtask([
            'name' => 'API CRUD master barang',
            'task_id' => $invTask->id,
            'status_id' => $mfgDone->id,
            'priority_level' => 2,
            'sprint_id' => $s1Inv->id,
            'time_estimate' => 300,
            'optimistic_estimate' => 240,
            'most_likely_estimate' => 300,
            'pessimistic_estimate' => 420,
            'start_date' => now()->subWeeks(6)->startOfWeek()->addDay()->setTime(8, 0),
            'due_date' => now()->subWeeks(6)->startOfWeek()->addDay()->setTime(13, 0),
            'completed_at' => now()->subWeeks(6)->startOfWeek()->addDay()->setTime(13, 15),
            'time_spent' => 315,
            'progress' => 100,
            'position' => 2,
            'created_by' => $christopher->id,
        ], [$christopher->id], ['Feature']);

        $inv4 = $this->createSubtask([
            'name' => 'UI halaman master barang',
            'task_id' => $invTask->id,
            'status_id' => $mfgDone->id,
            'priority_level' => 2,
            'sprint_id' => $s1Inv->id,
            'time_estimate' => 360,
            'optimistic_estimate' => 270,
            'most_likely_estimate' => 360,
            'pessimistic_estimate' => 480,
            'start_date' => now()->subWeeks(6)->startOfWeek()->addDay()->setTime(13, 0),
            'due_date' => now()->subWeeks(6)->startOfWeek()->addDays(2)->setTime(11, 0),
            'completed_at' => now()->subWeeks(6)->startOfWeek()->addDays(2)->setTime(11, 45),
            'time_spent' => 375,
            'progress' => 100,
            'position' => 3,
            'created_by' => $marvel->id,
        ], [$marvel->id], ['UI/UX']);

        $inv5 = $this->createSubtask([
            'name' => 'API stok masuk & keluar',
            'task_id' => $invTask->id,
            'status_id' => $mfgDone->id,
            'priority_level' => 1,
            'sprint_id' => $s2Inv->id,
            'time_estimate' => 360,
            'optimistic_estimate' => 300,
            'most_likely_estimate' => 360,
            'pessimistic_estimate' => 480,
            'start_date' => now()->subWeeks(5)->startOfWeek()->setTime(8, 0),
            'due_date' => now()->subWeeks(5)->startOfWeek()->setTime(14, 0),
            'completed_at' => now()->subWeeks(5)->startOfWeek()->setTime(14, 30),
            'time_spent' => 390,
            'progress' => 100,
            'position' => 4,
            'created_by' => $christopher->id,
        ], [$christopher->id], ['Feature']);

        $inv6 = $this->createSubtask([
            'name' => 'Fitur stock opname',
            'task_id' => $invTask->id,
            'status_id' => $mfgDone->id,
            'priority_level' => 2,
            'sprint_id' => $s2Inv->id,
            'time_estimate' => 480,
            'optimistic_estimate' => 360,
            'most_likely_estimate' => 480,
            'pessimistic_estimate' => 660,
            'start_date' => now()->subWeeks(5)->startOfWeek()->addDays(1)->setTime(8, 0),
            'due_date' => now()->subWeeks(5)->startOfWeek()->addDays(2)->setTime(12, 0),
            'completed_at' => now()->subWeeks(5)->startOfWeek()->addDays(2)->setTime(11, 30),
            'time_spent' => 450,
            'progress' => 100,
            'position' => 5,
            'created_by' => $christopher->id,
        ], [$christopher->id], ['Feature']);

        $inv7 = $this->createSubtask([
            'name' => 'Laporan stok real-time (PDF & Excel)',
            'task_id' => $invTask->id,
            'status_id' => $mfgProgress->id,
            'priority_level' => 2,
            'sprint_id' => $s3Inv->id,
            'time_estimate' => 300,
            'optimistic_estimate' => 240,
            'most_likely_estimate' => 300,
            'pessimistic_estimate' => 420,
            'start_date' => now()->subWeeks(4)->startOfWeek()->setTime(8, 0),
            'due_date' => now()->subWeeks(4)->startOfWeek()->addDays(1)->setTime(17, 0),
            'time_spent' => 120,
            'progress' => 40,
            'position' => 6,
            'created_by' => $marvel->id,
        ], [$marvel->id], ['Documentation']);

        $inv8 = $this->createSubtask([
            'name' => 'Integrasi barcode scanner',
            'task_id' => $invTask->id,
            'status_id' => $mfgTodo->id,
            'priority_level' => 3,
            'sprint_id' => $s3Inv->id,
            'time_estimate' => 240,
            'optimistic_estimate' => 180,
            'most_likely_estimate' => 240,
            'pessimistic_estimate' => 360,
            'start_date' => now()->subWeeks(4)->startOfWeek()->addDays(2)->setTime(8, 0),
            'due_date' => now()->subWeeks(4)->startOfWeek()->addDays(3)->setTime(17, 0),
            'time_spent' => 0,
            'progress' => 0,
            'position' => 7,
            'created_by' => $marvel->id,
        ], [$marvel->id]);

        $inv2->dependencies()->attach($inv1->id, ['dependency_type' => 'blocks']);
        $inv3->dependencies()->attach($inv2->id, ['dependency_type' => 'blocks']);
        $inv4->dependencies()->attach($inv3->id, ['dependency_type' => 'blocks']);
        $inv5->dependencies()->attach($inv2->id, ['dependency_type' => 'blocks']);
        $inv6->dependencies()->attach($inv5->id, ['dependency_type' => 'blocks']);
        $inv7->dependencies()->attach($inv6->id, ['dependency_type' => 'blocks']);
        $inv8->dependencies()->attach($inv7->id, ['dependency_type' => 'blocks']);

        // ═══════════════════════════════════════════════════════════════════
        // B2B: Portal Login & Autentikasi
        // ═══════════════════════════════════════════════════════════════════
        $authTask = $this->demoTask('Portal Login & Autentikasi');

        $auth1 = $this->createSubtask([
            'name' => 'Setup autentikasi Laravel Sanctum',
            'task_id' => $authTask->id,
            'status_id' => $b2bDone->id,
            'priority_level' => 1,
            'sprint_id' => $s1B2B->id,
            'time_estimate' => 240,
            'optimistic_estimate' => 180,
            'most_likely_estimate' => 240,
            'pessimistic_estimate' => 360,
            'start_date' => now()->subWeeks(6)->startOfWeek()->setTime(8, 0),
            'due_date' => now()->subWeeks(6)->startOfWeek()->setTime(12, 0),
            'completed_at' => now()->subWeeks(6)->startOfWeek()->setTime(11, 45),
            'time_spent' => 225,
            'progress' => 100,
            'position' => 0,
            'created_by' => $christopher->id,
        ], [$christopher->id], ['Security']);

        $auth2 = $this->createSubtask([
            'name' => 'Role-based access control (RBAC)',
            'task_id' => $authTask->id,
            'status_id' => $b2bDone->id,
            'priority_level' => 1,
            'sprint_id' => $s1B2B->id,
            'time_estimate' => 300,
            'optimistic_estimate' => 240,
            'most_likely_estimate' => 300,
            'pessimistic_estimate' => 420,
            'start_date' => now()->subWeeks(6)->startOfWeek()->setTime(13, 0),
            'due_date' => now()->subWeeks(6)->startOfWeek()->addDay()->setTime(17, 0),
            'completed_at' => now()->subWeeks(6)->startOfWeek()->addDay()->setTime(16, 30),
            'time_spent' => 285,
            'progress' => 100,
            'position' => 1,
            'created_by' => $christopher->id,
        ], [$christopher->id], ['Security', 'Feature']);

        $auth3 = $this->createSubtask([
            'name' => 'Halaman login & lupa password UI',
            'task_id' => $authTask->id,
            'status_id' => $b2bDone->id,
            'priority_level' => 2,
            'sprint_id' => $s1B2B->id,
            'time_estimate' => 240,
            'optimistic_estimate' => 180,
            'most_likely_estimate' => 240,
            'pessimistic_estimate' => 360,
            'start_date' => now()->subWeeks(6)->startOfWeek()->addDays(2)->setTime(8, 0),
            'due_date' => now()->subWeeks(6)->startOfWeek()->addDays(3)->setTime(17, 0),
            'completed_at' => now()->subWeeks(6)->startOfWeek()->addDays(3)->setTime(16, 0),
            'time_spent' => 240,
            'progress' => 100,
            'position' => 2,
            'created_by' => $devin->id,
        ], [$devin->id], ['UI/UX']);

        $auth2->dependencies()->attach($auth1->id, ['dependency_type' => 'blocks']);
        $auth3->dependencies()->attach($auth1->id, ['dependency_type' => 'blocks']);

        // ═══════════════════════════════════════════════════════════════════
        // B2B: Order Management Portal
        // ═══════════════════════════════════════════════════════════════════
        $orderTask = $this->demoTask('Order Management Portal');

        $ord1 = $this->createSubtask([
            'name' => 'API list order dengan pagination & filter',
            'task_id' => $orderTask->id,
            'status_id' => $b2bDone->id,
            'priority_level' => 1,
            'sprint_id' => $s2B2B->id,
            'time_estimate' => 300,
            'optimistic_estimate' => 240,
            'most_likely_estimate' => 300,
            'pessimistic_estimate' => 420,
            'start_date' => now()->subWeeks(4)->startOfWeek()->setTime(8, 0),
            'due_date' => now()->subWeeks(4)->startOfWeek()->setTime(13, 0),
            'completed_at' => now()->subWeeks(4)->startOfWeek()->setTime(13, 30),
            'time_spent' => 330,
            'progress' => 100,
            'position' => 0,
            'created_by' => $christopher->id,
        ], [$christopher->id], ['Feature']);

        $ord2 = $this->createSubtask([
            'name' => 'Halaman list order dengan badge status',
            'task_id' => $orderTask->id,
            'status_id' => $b2bDone->id,
            'priority_level' => 2,
            'sprint_id' => $s2B2B->id,
            'time_estimate' => 360,
            'optimistic_estimate' => 270,
            'most_likely_estimate' => 360,
            'pessimistic_estimate' => 480,
            'start_date' => now()->subWeeks(4)->startOfWeek()->addDay()->setTime(8, 0),
            'due_date' => now()->subWeeks(4)->startOfWeek()->addDays(2)->setTime(17, 0),
            'completed_at' => now()->subWeeks(4)->startOfWeek()->addDays(2)->setTime(16, 45),
            'time_spent' => 360,
            'progress' => 100,
            'position' => 1,
            'created_by' => $devin->id,
        ], [$devin->id], ['UI/UX']);

        $ord3 = $this->createSubtask([
            'name' => 'API detail order & timeline status',
            'task_id' => $orderTask->id,
            'status_id' => $b2bProgress->id,
            'priority_level' => 1,
            'sprint_id' => $s3B2B->id,
            'time_estimate' => 240,
            'optimistic_estimate' => 180,
            'most_likely_estimate' => 240,
            'pessimistic_estimate' => 360,
            'start_date' => now()->subWeeks(2)->startOfWeek()->setTime(8, 0),
            'due_date' => now()->subWeeks(2)->startOfWeek()->setTime(14, 0),
            'time_spent' => 120,
            'progress' => 50,
            'position' => 2,
            'created_by' => $marvel->id,
        ], [$marvel->id], ['Feature']);

        $ord2->dependencies()->attach($ord1->id, ['dependency_type' => 'blocks']);
        $ord3->dependencies()->attach($ord1->id, ['dependency_type' => 'blocks']);

        // ═══════════════════════════════════════════════════════════════════
        // B2C: Project Catalog & Listing
        // ═══════════════════════════════════════════════════════════════════
        $catTask = $this->demoTask('Project Catalog & Listing');

        $cat1 = $this->createSubtask([
            'name' => 'API produk & kategori',
            'task_id' => $catTask->id,
            'status_id' => $b2cDone->id,
            'priority_level' => 1,
            'sprint_id' => $s1B2C->id,
            'time_estimate' => 300,
            'optimistic_estimate' => 240,
            'most_likely_estimate' => 300,
            'pessimistic_estimate' => 420,
            'start_date' => now()->subWeeks(5)->startOfWeek()->setTime(8, 0),
            'due_date' => now()->subWeeks(5)->startOfWeek()->setTime(13, 0),
            'completed_at' => now()->subWeeks(5)->startOfWeek()->setTime(13, 15),
            'time_spent' => 315,
            'progress' => 100,
            'position' => 0,
            'created_by' => $devin->id,
        ], [$devin->id], ['Feature']);

        $cat2 = $this->createSubtask([
            'name' => 'Halaman project listing (grid + lazy load)',
            'task_id' => $catTask->id,
            'status_id' => $b2cDone->id,
            'priority_level' => 2,
            'sprint_id' => $s1B2C->id,
            'time_estimate' => 360,
            'optimistic_estimate' => 270,
            'most_likely_estimate' => 360,
            'pessimistic_estimate' => 480,
            'start_date' => now()->subWeeks(5)->startOfWeek()->addDay()->setTime(8, 0),
            'due_date' => now()->subWeeks(5)->startOfWeek()->addDays(2)->setTime(17, 0),
            'completed_at' => now()->subWeeks(5)->startOfWeek()->addDays(2)->setTime(16, 30),
            'time_spent' => 360,
            'progress' => 100,
            'position' => 1,
            'created_by' => $marvel->id,
        ], [$marvel->id], ['UI/UX']);

        $cat3 = $this->createSubtask([
            'name' => 'Komponen filter multi-parameter',
            'task_id' => $catTask->id,
            'status_id' => $b2cProgress->id,
            'priority_level' => 2,
            'sprint_id' => $s1B2C->id,
            'time_estimate' => 240,
            'optimistic_estimate' => 180,
            'most_likely_estimate' => 240,
            'pessimistic_estimate' => 360,
            'start_date' => now()->subWeeks(4)->startOfWeek()->setTime(8, 0),
            'due_date' => now()->subWeeks(4)->startOfWeek()->addDay()->setTime(12, 0),
            'time_spent' => 120,
            'progress' => 50,
            'position' => 2,
            'created_by' => $marvel->id,
        ], [$marvel->id], ['UI/UX']);

        $cat2->dependencies()->attach($cat1->id, ['dependency_type' => 'blocks']);
        $cat3->dependencies()->attach($cat2->id, ['dependency_type' => 'blocks']);

        // ═══════════════════════════════════════════════════════════════════
        // B2C: Checkout & Payment Gateway
        // ═══════════════════════════════════════════════════════════════════
        $checkTask = $this->demoTask('Checkout & Payment Gateway');

        $chk1 = $this->createSubtask([
            'name' => 'Keranjang belanja (tambah, update qty, hapus)',
            'task_id' => $checkTask->id,
            'status_id' => $b2cDone->id,
            'priority_level' => 1,
            'sprint_id' => $s1Pay->id,
            'time_estimate' => 300,
            'optimistic_estimate' => 240,
            'most_likely_estimate' => 300,
            'pessimistic_estimate' => 420,
            'start_date' => now()->subWeeks(3)->startOfWeek()->setTime(8, 0),
            'due_date' => now()->subWeeks(3)->startOfWeek()->setTime(13, 0),
            'completed_at' => now()->subWeeks(3)->startOfWeek()->setTime(13, 30),
            'time_spent' => 330,
            'progress' => 100,
            'position' => 0,
            'created_by' => $christopher->id,
        ], [$christopher->id], ['Feature']);

        $chk2 = $this->createSubtask([
            'name' => 'Integrasi Midtrans payment gateway',
            'task_id' => $checkTask->id,
            'status_id' => $b2cDone->id,
            'priority_level' => 1,
            'sprint_id' => $s1Pay->id,
            'time_estimate' => 480,
            'optimistic_estimate' => 360,
            'most_likely_estimate' => 480,
            'pessimistic_estimate' => 660,
            'start_date' => now()->subWeeks(3)->startOfWeek()->addDay()->setTime(8, 0),
            'due_date' => now()->subWeeks(3)->startOfWeek()->addDays(3)->setTime(17, 0),
            'completed_at' => now()->subWeeks(3)->startOfWeek()->addDays(3)->setTime(16, 30),
            'time_spent' => 480,
            'progress' => 100,
            'position' => 1,
            'created_by' => $christopher->id,
        ], [$christopher->id], ['Feature', 'Security']);

        $chk3 = $this->createSubtask([
            'name' => 'Multi-step checkout form',
            'task_id' => $checkTask->id,
            'status_id' => $b2cProgress->id,
            'priority_level' => 2,
            'sprint_id' => $s2Pay->id,
            'time_estimate' => 360,
            'optimistic_estimate' => 270,
            'most_likely_estimate' => 360,
            'pessimistic_estimate' => 480,
            'start_date' => now()->subWeeks(1)->startOfWeek()->setTime(8, 0),
            'due_date' => now()->subWeeks(1)->startOfWeek()->addDays(2)->setTime(17, 0),
            'time_spent' => 180,
            'progress' => 50,
            'position' => 2,
            'created_by' => $marvel->id,
        ], [$marvel->id], ['UI/UX']);

        $chk2->dependencies()->attach($chk1->id, ['dependency_type' => 'blocks']);
        $chk3->dependencies()->attach($chk2->id, ['dependency_type' => 'blocks']);
    }

    private function createSubtask(array $definition, array $assigneeIds = [], array $labelNames = []): Subtask
    {
        $subtask = Subtask::create([
            'name' => $definition['name'],
            'task_id' => $definition['task_id'],
            'status_id' => $definition['status_id'],
            'priority_level' => $definition['priority_level'] ?? 2,
            'sprint_id' => $definition['sprint_id'] ?? null,
            'time_estimate' => $definition['time_estimate'] ?? null,
            'optimistic_estimate' => $definition['optimistic_estimate'] ?? null,
            'most_likely_estimate' => $definition['most_likely_estimate'] ?? null,
            'pessimistic_estimate' => $definition['pessimistic_estimate'] ?? null,
            'start_date' => $definition['start_date'] ?? null,
            'due_date' => $definition['due_date'] ?? null,
            'baseline_start_date' => $definition['baseline_start_date'] ?? $definition['start_date'] ?? null,
            'baseline_due_date' => $definition['baseline_due_date'] ?? $definition['due_date'] ?? null,
            'completed_at' => $definition['completed_at'] ?? null,
            'time_spent' => $definition['time_spent'] ?? 0,
            'progress' => $definition['progress'] ?? 0,
            'position' => $definition['position'] ?? 0,
            'created_by' => $definition['created_by'],
        ]);

        foreach ($assigneeIds as $userId) {
            $subtask->assignees()->attach([
                $userId => ['assigned_by' => $definition['created_by']],
            ]);
        }

        if (! empty($labelNames)) {
            $subtask->labels()->attach(
                collect($labelNames)
                    ->map(fn (string $l) => $this->demoLabel($l)->id)
                    ->all()
            );
        }

        return $subtask;
    }
}
