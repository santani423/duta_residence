<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menggabungkan Kavling Developer (K) dan Kavling Penghuni (P) menjadi satu tipe Kavling (K).
 * Tipe unit yang tersisa: B (Bangunan), K (Kavling), R (Ruko).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('property_types')->updateOrInsert(
            ['id' => 'K'],
            ['name' => 'Kavling', 'description' => 'Kavling / lahan tanpa bangunan'],
        );

        // Termasuk unit yang soft-deleted supaya FK ke 'P' tidak tersisa.
        DB::table('units')->where('property_type_id', 'P')->update(['property_type_id' => 'K']);

        DB::table('property_types')->where('id', 'P')->delete();
    }

    public function down(): void
    {
        // Unit yang dulunya 'P' tidak bisa dibedakan lagi dari 'K', jadi hanya data referensinya yang dipulihkan.
        DB::table('property_types')->where('id', 'K')->update(['name' => 'Kavling Developer', 'description' => 'Kavling milik developer']);
        DB::table('property_types')->insertOrIgnore(['id' => 'P', 'name' => 'Kavling Penghuni', 'description' => 'Kavling milik penghuni']);
    }
};
