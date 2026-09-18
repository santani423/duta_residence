<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            // Diisi saat penghuni yang sebelumnya terhubung ke suatu unit kehilangan hubungan
            // itu (unit dilepas/dihapus) dan tidak punya unit lain; dikosongkan lagi begitu
            // penghuni terhubung ke unit. Membedakan "Penghuni Tanpa Unit" dari penghuni baru
            // yang memang belum pernah dihubungkan ke unit mana pun.
            $table->timestamp('unit_unlinked_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('residents', function (Blueprint $table) {
            $table->dropColumn('unit_unlinked_at');
        });
    }
};
