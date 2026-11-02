<?php

use App\Services\ClusterCodeService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mengganti clusters.id dari kode angka sistem lama (01-14) menjadi kode huruf cluster. D dan E
 * dipakai bersama oleh cluster hunian dan ruko, jadi id ruko-nya RD dan RE; prefix kode properti
 * (clusters.code_prefix) dan kode blok (D7, E7) tidak berubah.
 *
 * Yang sengaja tidak diubah: units.id (sudah menjadi username penghuni dan nomor VA), audit_logs
 * (riwayat), dan cluster lain yang dibuat manual di luar daftar ini.
 */
return new class extends Migration
{
    private const CODES = [
        '01' => 'D',
        '02' => 'G',
        '03' => 'J',
        '04' => 'AR',
        '05' => 'DD',
        '06' => 'AM',
        '07' => 'AL',
        '08' => 'JA',
        '09' => 'C',
        '10' => 'E',
        '11' => 'AX',
        '12' => 'RE',
        '13' => 'B',
        '14' => 'RD',
    ];

    /** Semua tabel dengan foreign key cluster_id ke clusters.id. */
    private const REFERENCING_TABLES = [
        'units',
        'cluster_rate_schedules',
        'penalty_rules',
        'cluster_maps',
        'collector_assignments',
        'supervisor_assignments',
    ];

    public function up(): void
    {
        $this->renameClusters(self::CODES);

        $service = app(ClusterCodeService::class);
        $service->syncClusters();
        $service->backfillUnitBlockNumbers();
    }

    public function down(): void
    {
        $this->renameClusters(array_flip(self::CODES));
    }

    /** @param array<string, string> $codes kode lama => kode baru */
    private function renameClusters(array $codes): void
    {
        DB::transaction(function () use ($codes) {
            foreach ($codes as $from => $to) {
                // Kunci numerik seperti '10' dibaca PHP sebagai integer.
                [$from, $to] = [(string) $from, (string) $to];
                $cluster = DB::table('clusters')->where('id', $from)->first();

                if (! $cluster) {
                    continue;
                }

                if (DB::table('clusters')->where('id', $to)->exists()) {
                    throw new RuntimeException("Kode cluster {$to} sudah dipakai cluster lain, {$cluster->name} ({$from}) tidak dapat diubah.");
                }

                // Baris baru dibuat lebih dulu supaya foreign key tetap valid selama data anak dipindahkan.
                DB::table('clusters')->insert([
                    ...(array) $cluster,
                    'id' => $to,
                    'description' => $cluster->description === null ? null : str_replace("blok {$from}.", "blok {$to}.", $cluster->description),
                ]);

                foreach (self::REFERENCING_TABLES as $table) {
                    DB::table($table)->where('cluster_id', $from)->update(['cluster_id' => $to]);
                }

                DB::table('clusters')->where('id', $from)->delete();
            }

            // broadcasts.target_criteria menyimpan cluster_id di JSON, tanpa foreign key.
            foreach (DB::table('broadcasts')->whereNotNull('target_criteria')->get(['id', 'target_criteria']) as $broadcast) {
                $criteria = json_decode($broadcast->target_criteria, true);

                if (isset($criteria['cluster_id'], $codes[$criteria['cluster_id']])) {
                    $criteria['cluster_id'] = (string) $codes[$criteria['cluster_id']];
                    DB::table('broadcasts')->where('id', $broadcast->id)->update(['target_criteria' => json_encode($criteria)]);
                }
            }
        });
    }
};
