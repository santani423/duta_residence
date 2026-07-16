<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('billings')->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->decimal('principal_amount', 15, 2)->default(0);
            $table->decimal('penalty_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('overdue_months')->default(0);
            $table->foreignId('penalty_rule_id')->nullable()->constrained('penalty_rules')->nullOnDelete();
            $table->json('penalty_rule_snapshot')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();
            $table->index(['billing_id', 'payment_transaction_id'], 'payment_allocations_billing_transaction_index');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->foreignId('payment_transaction_id')->nullable()->after('number')
                ->constrained('payment_transactions')->nullOnDelete();
        });

        Schema::create('penalty_waivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_id')->constrained('billings')->cascadeOnDelete();
            $table->decimal('original_penalty_amount', 15, 2);
            $table->decimal('waived_penalty_amount', 15, 2);
            $table->decimal('final_penalty_amount', 15, 2);
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('submitted_by')->constrained('users');
            $table->timestamp('submitted_at');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('review_notes', 200)->nullable();
            $table->timestamps();
            $table->index(['billing_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_waivers');
        Schema::dropIfExists('payment_allocations');
    }
};
