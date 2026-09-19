<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->string('discount_type', 20)->default('nominal');
            $table->decimal('original_principal', 15, 2);
            $table->decimal('principal_discount', 15, 2)->default(0);
            $table->decimal('original_penalty', 15, 2)->default(0);
            $table->decimal('penalty_reduction', 15, 2)->default(0);
            $table->decimal('final_amount', 15, 2);
            $table->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
            $table->index(['unit_id', 'status']);
        });

        Schema::create('payment_scheme_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_scheme_id')->constrained('payment_schemes')->cascadeOnDelete();
            $table->foreignId('billing_id')->constrained('billings')->restrictOnDelete();
            // Snapshot of the billing at submission - kept for audit and to detect stale schemes.
            $table->decimal('original_principal', 15, 2);
            $table->decimal('principal_discount', 15, 2)->default(0);
            $table->decimal('final_principal', 15, 2);
            $table->decimal('original_penalty', 15, 2)->default(0);
            $table->decimal('penalty_reduction', 15, 2)->default(0);
            $table->decimal('final_penalty', 15, 2)->default(0);
            $table->decimal('previous_discount', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['payment_scheme_id', 'billing_id']);
            $table->index('billing_id');
        });

        Schema::table('billings', function (Blueprint $table) {
            $table->foreignId('payment_scheme_id')->nullable()->after('discount_reason')->constrained('payment_schemes')->nullOnDelete();
            // Penalty frozen by an approved payment scheme; overrides the tier calculation while unpaid.
            $table->decimal('penalty_fixed', 15, 2)->nullable()->after('payment_scheme_id');
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_scheme_id');
            $table->dropColumn('penalty_fixed');
        });

        Schema::dropIfExists('payment_scheme_items');
        Schema::dropIfExists('payment_schemes');
    }
};
