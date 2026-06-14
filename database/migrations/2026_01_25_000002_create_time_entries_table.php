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
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->integer('duration'); // in minutes

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            $table->boolean('is_billable')->default(false);
            $table->boolean('is_running')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['subtask_id', 'user_id']);
            $table->index(['user_id', 'started_at']);
            $table->index('is_running');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_entries');
    }
};
