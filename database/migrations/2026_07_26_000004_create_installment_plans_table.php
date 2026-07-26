<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installment_plans', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->foreignId('billing_id')->nullable()->constrained('billings')->nullOnDelete();
            $table->decimal('total_outstanding', 15, 2);
            $table->unsignedTinyInteger('number_of_installments');
            $table->decimal('installment_amount', 15, 2);
            $table->string('frequency', 20)->default('monthly');
            $table->date('start_date');
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installment_plans');
    }
};
