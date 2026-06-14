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
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 7)->default('#6366F1');
            $table->integer('position')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'slug']);
            $table->index(['workspace_id', 'position']);
        });

        Schema::create('space_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'member', 'guest'])->default('member');
            $table->timestamps();

            $table->unique(['space_id', 'user_id']);
            $table->index('user_id');
        });

        // Per-user starred spaces (global is_starred on the row would be shared across users).
        Schema::create('starred_spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starred_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'space_id']);
            $table->index(['user_id', 'workspace_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('starred_spaces');
        Schema::dropIfExists('space_members');
        Schema::dropIfExists('spaces');
    }
};
