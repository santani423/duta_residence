<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache turunan status penagihan per unit (unit = collection account). Bukan sumber kebenaran:
 * selalu bisa dibangun ulang dari billings/aktivitas lewat `collection:refresh-account-states`.
 * Ada supaya daftar akun collector bisa di-filter/sort berdasarkan aging & priority dengan
 * pagination tanpa menghitung ulang denda ribuan tagihan di setiap request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_account_states', function (Blueprint $table) {
            $table->string('unit_id', 5)->primary();
            $table->string('customer_resident_id', 8)->nullable();
            $table->foreignId('collector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('outstanding_principal', 15, 2)->default(0);
            $table->decimal('outstanding_penalty', 15, 2)->default(0);
            $table->decimal('outstanding_total', 15, 2)->default(0);
            $table->unsignedInteger('open_invoice_count')->default(0);
            $table->date('oldest_due_date')->nullable();
            $table->date('next_due_date')->nullable();
            $table->unsignedInteger('aging_days')->default(0);
            $table->string('aging_bucket', 20)->default('current');
            $table->string('status', 30)->default('paid');
            $table->unsignedSmallInteger('priority_score')->default(0);
            $table->string('priority_level', 20)->default('normal');
            $table->dateTime('last_contact_at')->nullable();
            $table->string('last_contact_result', 30)->nullable();
            $table->dateTime('next_follow_up_at')->nullable();
            $table->unsignedInteger('failed_contact_count')->default(0);
            $table->unsignedInteger('failed_visit_count')->default(0);
            $table->unsignedInteger('broken_ptp_count')->default(0);
            $table->foreignId('active_promise_id')->nullable()->constrained('payment_promises')->nullOnDelete();
            $table->timestamp('refreshed_at')->nullable();
            $table->timestamps();

            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->foreign('customer_resident_id')->references('id')->on('residents')->nullOnDelete();
            $table->index(['collector_id', 'priority_level']);
            $table->index('status');
            $table->index('aging_bucket');
            $table->index('next_follow_up_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_account_states');
    }
};
