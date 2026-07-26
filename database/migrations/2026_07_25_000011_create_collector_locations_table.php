<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collector_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collector_id')->constrained('users')->restrictOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->dateTime('recorded_at');
            $table->timestamps();

            $table->index(['collector_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_locations');
    }
};
