<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber tunggal timeline penagihan per unit: kontak (call/WA/SMS/email), visit, PTP, pembayaran,
 * catatan, dan seterusnya. Append-only - koreksi dicatat sebagai aktivitas baru, bukan edit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_activities', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->string('customer_resident_id', 8)->nullable();
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);
            $table->string('event', 30)->default('created');
            $table->string('channel_result', 30)->nullable();
            $table->nullableMorphs('subject');
            $table->string('summary');
            $table->json('details')->nullable();
            $table->dateTime('occurred_at');
            $table->dateTime('next_follow_up_at')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->uuid('client_uuid')->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('customer_resident_id')->references('id')->on('residents')->nullOnDelete();
            $table->index(['unit_id', 'occurred_at']);
            $table->index(['collector_id', 'occurred_at']);
            $table->index('type');
            // Satu event per subjek (mis. visit #12 "created") - membuat observer & backfill idempoten.
            $table->unique(['subject_type', 'subject_id', 'event'], 'collection_activities_subject_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_activities');
    }
};
