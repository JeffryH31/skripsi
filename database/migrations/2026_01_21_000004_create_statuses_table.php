<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 7)->default('#6B7280');
            $table->string('type')->default('custom'); // open, in_progress, review, closed, custom
            $table->enum('applies_to', ['tasks', 'subtasks', 'both'])->default('both'); // NEW: controls where status can be used
            $table->integer('position')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->unique(['space_id', 'slug']);
            $table->index(['space_id', 'position']);
            $table->index(['space_id', 'applies_to']); // NEW: index for filtering
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
