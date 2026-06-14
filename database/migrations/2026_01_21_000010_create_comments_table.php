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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('subtask_id')->nullable()->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();

            $table->text('content');
            $table->json('mentions')->nullable();
            $table->json('attachments')->nullable();

            $table->boolean('is_resolved')->default(false);
            $table->timestamp('edited_at')->nullable();

            $table->timestamps();

            $table->index(['task_id', 'created_at']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
