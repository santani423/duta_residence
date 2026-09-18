<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Uploading a payment proof now sets payment_method = bank_transfer automatically. Bring
     * existing rows in line: manual transactions and anything that already carries a proof.
     */
    public function up(): void
    {
        DB::table('payment_transactions')
            ->where(fn ($q) => $q->where('payment_provider', 'manual')->orWhereNotNull('manual_proof_path'))
            ->where(fn ($q) => $q->whereNull('payment_method')->orWhere('payment_method', '!=', 'bank_transfer'))
            ->update(['payment_method' => 'bank_transfer']);
    }

    public function down(): void
    {
        // Irreversible: the previous per-row values were not recorded.
    }
};
