<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->string('va_bank_code', 10)->nullable()->after('manual_instructions');
            $table->string('va_company_code', 10)->nullable()->after('va_bank_code');
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->dropColumn(['va_bank_code', 'va_company_code']);
        });
    }
};
