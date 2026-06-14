<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();

            // Every checklist item belongs to a subtask (root owner)
            $table->foreignId('subtask_id')
                ->constrained()
                ->cascadeOnDelete();

            // Self-referencing for nested checklist (null = top-level item)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('checklist_items')
                ->cascadeOnDelete();

            $table->string('name');
            $table->boolean('is_checked')->default(false);
            $table->integer('position')->default(0);

            // Depth within the checklist: 0 = top-level, max 6 = 7th level
            $table->tinyInteger('depth')->unsigned()->default(0);

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['subtask_id', 'parent_id', 'position']);
            $table->index('depth');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
    }
};
