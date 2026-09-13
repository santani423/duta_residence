<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('manual_sender_name', 100)->nullable()->after('manual_proof_path');
            $table->string('manual_sender_bank', 100)->nullable()->after('manual_sender_name');
            $table->string('manual_sender_account_number', 100)->nullable()->after('manual_sender_bank');
            $table->decimal('manual_amount', 15, 2)->nullable()->after('manual_sender_account_number');
            $table->timestamp('manual_proof_uploaded_at')->nullable()->after('manual_notes');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'manual_sender_name',
                'manual_sender_bank',
                'manual_sender_account_number',
                'manual_amount',
                'manual_proof_uploaded_at',
            ]);
        });
    }
};
