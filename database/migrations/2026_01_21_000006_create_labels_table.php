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
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->default('#6366F1');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'space_id']);
            $table->unique(['workspace_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};
