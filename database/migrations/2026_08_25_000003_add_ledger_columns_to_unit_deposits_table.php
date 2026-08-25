<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_deposits', function (Blueprint $table) {
            $table->string('direction', 10)->default('credit')->after('type');
            $table->decimal('balance_before', 15, 2)->default(0)->after('amount');
            $table->string('reference_type', 100)->nullable()->after('receipt_number');
            $table->string('reference_id', 50)->nullable()->after('reference_type');
            $table->foreignId('reversal_of_id')->nullable()->after('reference_id')->constrained('unit_deposits')->nullOnDelete();
        });

        // Existing rows predate `direction`; every row written so far is a credit
        // (overpayment), so backfill matches what `type='credit'` already meant.
        DB::table('unit_deposits')->update(['direction' => 'credit']);
    }

    public function down(): void
    {
        Schema::table('unit_deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropColumn(['direction', 'balance_before', 'reference_type', 'reference_id']);
        });
    }
};
