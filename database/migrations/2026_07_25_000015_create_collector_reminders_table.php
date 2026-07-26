<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->string('resident_id', 8);
            $table->foreignId('billing_id')->nullable()->constrained('billings')->nullOnDelete();
            $table->text('message');
            $table->string('phone', 30);
            $table->dateTime('sent_at');
            $table->foreignId('sent_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->foreign('resident_id')->references('id')->on('residents')->restrictOnDelete();
            $table->index(['unit_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_reminders');
    }
};
