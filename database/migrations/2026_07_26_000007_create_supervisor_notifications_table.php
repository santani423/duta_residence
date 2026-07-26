<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supervisor_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('category', 50);
            $table->string('priority', 20)->default('normal');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('reference_type')->nullable();
            $table->string('reference_id', 50)->nullable();
            $table->string('read_status', 10)->default('unread');
            $table->string('handled_status', 20)->default('open');
            $table->dateTime('handling_deadline')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('escalation_log')->nullable();
            $table->timestamps();

            $table->index(['category', 'priority']);
            $table->index(['handled_status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_notifications');
    }
};
