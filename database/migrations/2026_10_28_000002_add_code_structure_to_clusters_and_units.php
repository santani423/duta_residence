<?php

use App\Services\ClusterCodeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memisahkan kode cluster (prefix) dari nomor blok. Kolom units.block tetap menyimpan kode
 * gabungan lama (mis. "AX3") agar data, laporan, penugasan kolektor, dan kuitansi lama tidak
 * berubah; units.block_number hanya diisi bila kode lama dapat dibaca tanpa ambigu (data yang
 * tidak sesuai dibiarkan NULL dan dilaporkan oleh `php artisan clusters:audit-blocks`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clusters', function (Blueprint $table) {
            if (Schema::hasColumn('clusters', 'code')) {
                // Kolom sementara dari draf sebelumnya (pola teks "E(BLOK)(1-5)"), digantikan kolom terstruktur.
                $table->dropColumn('code');
            }
        });

        Schema::table('clusters', function (Blueprint $table) {
            $table->string('code_prefix', 5)->nullable()->after('name');
            $table->string('area_type', 20)->default('cluster')->after('code_prefix');
            $table->json('allowed_blocks')->nullable()->after('area_type');
            $table->index('code_prefix');
        });

        Schema::table('units', function (Blueprint $table) {
            $table->unsignedSmallInteger('block_number')->nullable()->after('block');
            $table->index(['cluster_id', 'block_number']);
        });

        $service = app(ClusterCodeService::class);
        $service->syncClusters();
        $service->backfillUnitBlockNumbers();
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropIndex(['cluster_id', 'block_number']);
            $table->dropColumn('block_number');
        });

        Schema::table('clusters', function (Blueprint $table) {
            $table->dropIndex(['code_prefix']);
            $table->dropColumn(['code_prefix', 'area_type', 'allowed_blocks']);
        });
    }
};
