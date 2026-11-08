<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama pembayar: pihak yang benar-benar membayar (penyewa, anggota keluarga, kerabat, dll) yang bisa
 * berbeda dari pemilik unit. Opsional - NULL bila tidak diisi, dan tidak pernah diisi otomatis dengan
 * nama pemilik unit. Transaksi dan kuitansi lama tetap NULL sehingga tidak ada data yang berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('payer_name', 150)->nullable()->after('unit_id');
        });

        // Snapshot di kuitansi, sama seperti resident_name, supaya bukti cetak tetap akurat meski data berubah.
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('payer_name', 150)->nullable()->after('resident_name');
        });
    }

    public function down(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn('payer_name');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('payer_name');
        });
    }
};
