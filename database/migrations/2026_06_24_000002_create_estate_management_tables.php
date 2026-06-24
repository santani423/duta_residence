<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clusters', function (Blueprint $table) {
            $table->string('id', 2)->primary();
            $table->string('name', 50);
            $table->decimal('monthly_rate', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('property_types', function (Blueprint $table) {
            $table->char('id', 1)->primary();
            $table->string('name', 30);
            $table->string('description', 100)->nullable();
        });

        Schema::create('occupancy_statuses', function (Blueprint $table) {
            $table->char('id', 1)->primary();
            $table->string('name', 20);
        });

        Schema::create('customer_statuses', function (Blueprint $table) {
            $table->string('id', 2)->primary();
            $table->string('name', 30);
            $table->string('description', 100)->nullable();
        });

        Schema::create('billing_statuses', function (Blueprint $table) {
            $table->string('id', 2)->primary();
            $table->string('name', 20);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->char('id', 1)->primary();
            $table->string('name', 20);
        });

        Schema::create('payment_channels', function (Blueprint $table) {
            $table->char('id', 1)->primary();
            $table->string('name', 50);
        });

        Schema::create('regencies', function (Blueprint $table) {
            $table->string('id', 5)->primary();
            $table->string('name', 100)->index();
        });

        Schema::create('districts', function (Blueprint $table) {
            $table->string('id', 6)->primary();
            $table->string('regency_id', 5);
            $table->string('name', 100);
            $table->foreign('regency_id')->references('id')->on('regencies');
            $table->index('regency_id');
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->string('id', 5)->primary();
            $table->string('name', 100);
            $table->string('cluster_id', 2);
            $table->string('block', 5);
            $table->string('lot_number', 10);
            $table->char('property_type_id', 1)->default('B');
            $table->string('phone', 20)->nullable();
            $table->string('telephone', 20)->nullable();
            $table->string('id_card_address', 200)->nullable();
            $table->string('district_id', 6)->nullable();
            $table->decimal('building_area', 8, 2)->nullable();
            $table->decimal('land_area', 8, 2)->nullable();
            $table->string('email', 100)->nullable();
            $table->date('handover_date')->nullable();
            $table->char('occupancy_id', 1)->default('1');
            $table->string('status_id', 2)->default('AK');
            $table->boolean('is_penalty_eligible')->default(true);
            $table->boolean('is_discount_eligible')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('cluster_id')->references('id')->on('clusters');
            $table->foreign('property_type_id')->references('id')->on('property_types');
            $table->foreign('occupancy_id')->references('id')->on('occupancy_statuses');
            $table->foreign('status_id')->references('id')->on('customer_statuses');
            $table->foreign('district_id')->references('id')->on('districts')->nullOnDelete();
            $table->unique(['cluster_id', 'block', 'lot_number']);
            $table->index(['cluster_id', 'status_id']);
            $table->index('name');
        });

        Schema::create('special_billing_rates', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id', 5);
            $table->decimal('amount', 15, 2);
            $table->string('notes', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->index('customer_id');
        });

        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id', 5);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 15, 2);
            $table->decimal('penalty', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->string('status_id', 2)->default('01');
            $table->boolean('is_penalty_eligible')->default(true);
            $table->boolean('is_discount_eligible')->default(false);
            $table->string('billing_type', 20)->default('regular');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('approval_notes', 200)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('receipt_number', 20)->nullable();
            $table->string('loket_code', 10)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('spt_print_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('status_id')->references('id')->on('billing_statuses');
            $table->unique(['customer_id', 'year', 'month']);
            $table->index(['year', 'month']);
            $table->index(['customer_id', 'status_id']);
            $table->index('receipt_number');
        });

        Schema::create('receipts', function (Blueprint $table) {
            $table->string('number', 20)->primary();
            $table->string('customer_id', 5);
            $table->dateTime('transaction_date');
            $table->string('customer_name', 100);
            $table->string('cluster_name', 50);
            $table->string('block', 5);
            $table->string('lot_number', 10);
            $table->decimal('total_billing', 15, 2);
            $table->decimal('total_penalty', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2);
            $table->unsignedTinyInteger('billing_count')->default(1);
            $table->string('billing_periods', 200)->nullable();
            $table->string('loket_code', 20)->nullable();
            $table->string('cashier_name', 50)->nullable();
            $table->char('payment_method_id', 1)->default('C');
            $table->char('payment_channel_id', 1)->nullable();
            $table->string('status', 20)->default('paid');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->foreign('payment_method_id')->references('id')->on('payment_methods');
            $table->foreign('payment_channel_id')->references('id')->on('payment_channels')->nullOnDelete();
            $table->index(['customer_id', 'transaction_date']);
        });

        Schema::create('installments', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id', 5);
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('notes', 200)->nullable();
            $table->string('allocated_to', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->index(['customer_id', 'payment_date']);
        });

        Schema::create('reversals', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 20);
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('submitted_by')->constrained('users');
            $table->timestamp('submitted_at');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_notes', 200)->nullable();
            $table->timestamps();
            $table->foreign('receipt_number')->references('number')->on('receipts');
            $table->index(['status', 'receipt_number']);
        });

        Schema::create('receivables', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id', 5);
            $table->foreignId('billing_id')->constrained('billings');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 15, 2);
            $table->date('snapshot_date');
            $table->boolean('is_settled')->default(false);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->index(['customer_id', 'is_settled']);
            $table->index(['year', 'month']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 40)->unique();
            $table->string('invoice_number', 40)->unique();
            $table->string('customer_id', 5);
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('admin_fee', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->string('currency', 3)->default('IDR');
            $table->string('payment_provider', 20);
            $table->string('payment_method', 30)->nullable();
            $table->string('provider_reference', 100)->nullable()->unique();
            $table->string('status', 30)->default('pending');
            $table->string('payment_url')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('manual_proof_path')->nullable();
            $table->date('manual_transfer_date')->nullable();
            $table->text('manual_notes')->nullable();
            $table->text('verification_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->json('provider_payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers');
            $table->index(['payment_provider', 'status']);
        });

        Schema::create('billing_payment_transaction', function (Blueprint $table) {
            $table->foreignId('billing_id')->constrained('billings')->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->primary(['billing_id', 'payment_transaction_id'], 'billing_payment_transaction_primary');
        });

        Schema::create('payment_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('event_id', 120);
            $table->string('provider_reference', 120)->nullable();
            $table->string('status', 30)->default('received');
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('managed_files', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('stored_filename');
            $table->string('path');
            $table->string('disk')->default('local');
            $table->string('mime_type', 100);
            $table->string('extension', 20);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('entity');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 100)->nullable();
            $table->string('user_role', 100)->nullable();
            $table->string('activity', 100);
            $table->string('module', 50);
            $table->string('action', 50);
            $table->string('http_method', 10)->nullable();
            $table->string('endpoint')->nullable();
            $table->string('entity_type')->nullable();
            $table->string('entity_id', 50)->nullable();
            $table->json('old_data')->nullable();
            $table->json('new_data')->nullable();
            $table->json('changed_fields')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('request_id', 60)->nullable();
            $table->string('status', 20)->default('success');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
            $table->index(['module', 'action']);
        });

        Schema::create('notification_queues', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id', 5)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 80);
            $table->string('channel', 20)->default('whatsapp');
            $table->string('recipient', 100);
            $table->text('message');
            $table->string('read_status', 10)->default('unread');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->string('failed_reason')->nullable();
            $table->timestamps();
            $table->foreign('customer_id')->references('id')->on('customers')->nullOnDelete();
            $table->index(['status', 'type']);
        });
    }

    public function down(): void
    {
        foreach ([
            'notification_queues',
            'audit_logs',
            'managed_files',
            'payment_webhook_events',
            'billing_payment_transaction',
            'payment_transactions',
            'receivables',
            'reversals',
            'installments',
            'receipts',
            'billings',
            'special_billing_rates',
            'customers',
            'districts',
            'regencies',
            'payment_channels',
            'payment_methods',
            'billing_statuses',
            'customer_statuses',
            'occupancy_statuses',
            'property_types',
            'clusters',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
