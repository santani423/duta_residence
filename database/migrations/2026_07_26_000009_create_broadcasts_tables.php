<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->text('message');
            $table->json('target_criteria')->nullable();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('fail_count')->default(0);
            $table->timestamps();
        });

        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->string('recipient_type', 20);
            $table->string('recipient_id', 20)->nullable();
            $table->string('name', 150)->nullable();
            $table->string('phone', 30);
            $table->string('delivery_status', 20)->default('pending');
            $table->text('provider_response')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['broadcast_id', 'delivery_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
        Schema::dropIfExists('broadcasts');
    }
};
