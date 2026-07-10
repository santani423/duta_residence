<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('unit_id', 5)->nullable()->after('phone');
            $table->string('language_preference', 10)->default('id')->after('theme_preference');
            $table->json('notification_preferences')->nullable()->after('language_preference');
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
            $table->index('unit_id');
        });

        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->id();
            $table->string('active_gateway', 20)->default('manual');
            $table->json('enabled_gateways')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('mode', 20)->default('sandbox');
            $table->string('currency', 3)->default('IDR');
            $table->decimal('admin_fee', 15, 2)->default(0);
            $table->unsignedInteger('payment_timeout_minutes')->default(1440);
            $table->string('manual_bank_name')->nullable();
            $table->string('manual_account_number')->nullable();
            $table->string('manual_account_name')->nullable();
            $table->text('manual_instructions')->nullable();
            $table->unsignedInteger('proof_max_size_kb')->default(5120);
            $table->json('proof_allowed_extensions')->nullable();
            $table->string('xendit_public_key')->nullable();
            $table->string('midtrans_client_key')->nullable();
            $table->string('callback_url')->nullable();
            $table->text('webhook_notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_complaints', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 150);
            $table->string('category', 80)->nullable();
            $table->string('priority', 20)->default('normal');
            $table->text('description');
            $table->string('status', 30)->default('submitted');
            $table->string('attachment_path')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->index(['unit_id', 'status']);
        });

        Schema::create('customer_complaint_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_complaint_id')->constrained('customer_complaints')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->boolean('is_staff_response')->default(false);
            $table->timestamps();
        });

        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->string('unit_id', 5);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('unit_label', 120)->nullable();
            $table->string('category', 80);
            $table->text('description');
            $table->string('urgency', 20)->default('normal');
            $table->timestamp('preferred_schedule')->nullable();
            $table->string('status', 30)->default('submitted');
            $table->string('technician_name')->nullable();
            $table->timestamp('technician_schedule')->nullable();
            $table->text('technician_notes')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('rating_notes')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('unit_id')->references('id')->on('units')->cascadeOnDelete();
            $table->index(['unit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
        Schema::dropIfExists('customer_complaint_comments');
        Schema::dropIfExists('customer_complaints');
        Schema::dropIfExists('payment_gateway_settings');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropIndex(['unit_id']);
            $table->dropColumn(['language_preference', 'notification_preferences']);
            $table->dropColumn('unit_id');
        });
    }
};
