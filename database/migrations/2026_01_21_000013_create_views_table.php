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
        Schema::create('views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('type')->default('list'); // list, board, calendar, gantt, timeline
            $table->json('filters')->nullable();
            $table->json('sorts')->nullable();
            $table->json('columns')->nullable();
            $table->json('settings')->nullable();

            $table->boolean('is_default')->default(false);
            $table->boolean('is_private')->default(true);
            $table->integer('position')->default(0);

            $table->timestamps();

            $table->index(['project_id', 'user_id']);
            $table->index(['space_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('views');
    }
};
