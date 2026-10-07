<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Status '03' (Billing::STATUS_PARTIAL) = dibayar sebagian. Label "Sudah Bayar" rancu
     * dengan "Lunas" ('02'), jadi dikembalikan ke "Sebagian". Hanya label; id tidak berubah.
     */
    public function up(): void
    {
        DB::table('billing_statuses')->where('id', '03')->update(['name' => 'Sebagian']);
    }

    public function down(): void
    {
        DB::table('billing_statuses')->where('id', '03')->update(['name' => 'Sudah Bayar']);
    }
};
