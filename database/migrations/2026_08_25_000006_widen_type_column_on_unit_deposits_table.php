<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Original VARCHAR(10) truncates every UnitDeposit::TYPE_* constant longer than
        // 10 chars (e.g. 'overpayment', 'manual_credit', 'refund_credit', 'balance_usage'),
        // which MySQL strict mode rejects outright instead of silently truncating.
        Schema::table('unit_deposits', function (Blueprint $table) {
            $table->string('type', 20)->change();
        });
    }

    public function down(): void
    {
        Schema::table('unit_deposits', function (Blueprint $table) {
            $table->string('type', 10)->change();
        });
    }
};
