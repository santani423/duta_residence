<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_promises', function (Blueprint $table) {
            $table->foreignId('collector_id')->nullable()->after('billing_id')->constrained('users')->nullOnDelete();
            $table->foreignId('visit_id')->nullable()->after('collector_id')->constrained('collector_visits')->nullOnDelete();
            $table->decimal('fulfilled_amount', 15, 2)->nullable()->after('notes');
            $table->timestamp('fulfilled_at')->nullable()->after('fulfilled_amount');
            $table->timestamp('broken_at')->nullable()->after('fulfilled_at');
            $table->timestamp('cancelled_at')->nullable()->after('broken_at');
            $table->text('cancel_reason')->nullable()->after('cancelled_at');
            $table->uuid('client_uuid')->nullable()->unique()->after('cancel_reason');
            $table->unsignedInteger('version')->default(1)->after('client_uuid');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();

            $table->index(['status', 'promised_date']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_promises', function (Blueprint $table) {
            $table->dropIndex(['status', 'promised_date']);
            $table->dropConstrainedForeignId('collector_id');
            $table->dropConstrainedForeignId('visit_id');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropUnique(['client_uuid']);
            $table->dropColumn(['fulfilled_amount', 'fulfilled_at', 'broken_at', 'cancelled_at', 'cancel_reason', 'client_uuid', 'version']);
        });
    }
};
