<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('billings')->restrictOnDelete();
            $table->string('adjustment_type', 20);
            $table->decimal('original_value', 15, 2);
            $table->decimal('new_value', 15, 2);
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_adjustments');
    }
};
