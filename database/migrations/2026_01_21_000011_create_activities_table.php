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
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');

            $table->string('action'); // created, updated, deleted, moved, assigned, etc.
            $table->json('properties')->nullable(); // Additional data about the change
            $table->json('changes')->nullable(); // What changed (old/new values)

            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
