<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Akun customer lama (mis. hasil seeding) hanya punya unit_id, belum punya resident_id.
     * Portal penghuni sekarang mengandalkan resident_id sebagai identitas utama, jadi
     * isi dulu dari pemilik unit yang sedang mereka pakai supaya tidak perlu login ulang
     * atau ditautkan manual satu per satu.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('resident_id')
            ->whereNotNull('unit_id')
            ->orderBy('id')
            ->chunkById(200, function ($users) {
                foreach ($users as $user) {
                    $residentId = DB::table('units')->where('id', $user->unit_id)->value('resident_id');

                    if ($residentId) {
                        DB::table('users')->where('id', $user->id)->update(['resident_id' => $residentId]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Data backfill, tidak ada state sebelumnya yang perlu dikembalikan.
    }
};
