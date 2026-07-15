<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guided_tours', function (Blueprint $table) {
            $table->id();
            $table->string('module', 60);
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->json('roles')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_start')->default(true);
            $table->unsignedInteger('order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('module');
        });

        Schema::create('guided_tour_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guided_tour_id')->constrained()->cascadeOnDelete();
            $table->string('target', 150);
            $table->string('title', 150);
            $table->text('content');
            $table->string('placement', 20)->default('bottom');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('user_tour_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guided_tour_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('last_step')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'guided_tour_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tour_progress');
        Schema::dropIfExists('guided_tour_steps');
        Schema::dropIfExists('guided_tours');
    }
};
