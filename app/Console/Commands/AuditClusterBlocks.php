<?php

namespace App\Console\Commands;

use App\Services\ClusterCodeService;
use Illuminate\Console\Command;

class AuditClusterBlocks extends Command
{
    protected $signature = 'clusters:audit-blocks {--sync : Terapkan config/cluster_codes.php ke tabel clusters dan isi units.block_number yang valid}';

    protected $description = 'Laporkan unit yang kode bloknya tidak sesuai konfigurasi prefix/blok cluster (tidak mengubah kode unit)';

    public function handle(ClusterCodeService $service): int
    {
        if ($this->option('sync')) {
            foreach ($service->syncClusters() as $warning) {
                $this->warn($warning);
            }

            $this->info('Blok terisi untuk '.$service->backfillUnitBlockNumbers().' unit.');
        }

        $issues = $service->auditUnits();

        if ($issues === []) {
            $this->info('Semua unit sesuai konfigurasi kode cluster.');

            return self::SUCCESS;
        }

        $this->warn(count($issues).' unit perlu dikonfirmasi (tidak diubah otomatis):');
        $this->table(['Unit', 'Cluster', 'Blok', 'Keterangan'], array_map(
            fn (array $issue) => [$issue['unit_id'], $issue['cluster_id'], $issue['block'], $issue['reason']],
            $issues,
        ));

        return self::FAILURE;
    }
}
