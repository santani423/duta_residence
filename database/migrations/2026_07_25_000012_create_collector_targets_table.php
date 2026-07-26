<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->string('period_type', 10);
            $table->date('period_start');
            $table->decimal('target_amount', 15, 2);
            $table->unsignedInteger('target_visit_count')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['collector_id', 'period_type', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_targets');
    }
};
