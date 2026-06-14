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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('task_id')->unique(); // Human-readable ID like "PROJ-123"
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('status_id')->nullable()->constrained()->nullOnDelete();
            $table->tinyInteger('priority_level')->nullable();

            $table->string('name');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('time_estimate')->nullable()->comment('Estimated duration in minutes');

            $table->integer('position')->default(0);

            $table->boolean('is_archived')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status_id', 'position']);
            $table->index(['created_by']);
            $table->index('is_archived');
            $table->index('start_date');
            $table->index('due_date');
            $table->index('completed_at');
        });

        Schema::create('task_assignees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['task_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('task_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['task_id', 'label_id']);
            $table->index('label_id');
        });

        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('depends_on_task_id')->constrained('tasks')->cascadeOnDelete();
            $table->enum('dependency_type', ['blocks', 'relates_to'])->default('blocks');
            $table->timestamps();

            $table->unique(['task_id', 'depends_on_task_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('task_labels');
        Schema::dropIfExists('task_assignees');
        Schema::dropIfExists('tasks');
    }
};
