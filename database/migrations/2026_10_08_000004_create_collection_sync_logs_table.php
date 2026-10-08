<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jejak setiap item sinkronisasi offline dari aplikasi collector (idempoten via client_uuid). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_id', 100)->nullable();
            $table->uuid('client_uuid')->unique();
            $table->string('entity_type', 30);
            $table->string('operation', 10);
            $table->string('payload_hash', 64)->nullable();
            $table->string('result', 20);
            $table->unsignedBigInteger('server_entity_id')->nullable();
            $table->text('message')->nullable();
            $table->dateTime('client_created_at')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamps();

            $table->index(['collector_id', 'received_at']);
            $table->index('result');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_sync_logs');
    }
};
