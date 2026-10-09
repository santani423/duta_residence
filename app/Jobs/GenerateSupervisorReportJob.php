<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Models\User;
use App\Services\CollectionScopeService;
use App\Services\CollectorPerformanceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * The first background job in this codebase - queue infra (QUEUE_CONNECTION=database) was
 * already configured but unused before this feature. Only multi-period/bulk report exports
 * go through here (see spec K); small single-period reports stay synchronous, matching every
 * existing PDF/CSV controller in this app (DocumentController, ReportController).
 */
class GenerateSupervisorReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $reportExportId) {}

    public function handle(CollectionScopeService $scopeService, CollectorPerformanceService $performanceService): void
    {
        $export = ReportExport::query()->find($this->reportExportId);
        if (! $export) {
            return;
        }

        $export->update(['status' => ReportExport::STATUS_PROCESSING]);

        try {
            $requester = User::query()->findOrFail($export->requested_by);
            $filters = $export->filters ?? [];
            $period = $performanceService->resolvePeriod($filters['period_type'] ?? 'monthly', $filters['period_start'] ?? null);

            $rows = [[
                'Kode Kolektor', 'Nama Kolektor', 'Target', 'Tercapai', 'Persentase', 'Jumlah Kunjungan',
                'Kunjungan Berhasil', 'PTP Terpenuhi', 'Collection Rate', 'Tunggakan Saat Ini',
            ]];

            // Semua collector dalam cakupan peminta (full scope = semua collector), metrik dihitung
            // dalam satu batch — tanpa query per collector.
            $collectors = $scopeService->allCollectorsQuery($requester)
                ->with('collectorProfile')
                ->orderBy('name')
                ->get();
            $metrics = $performanceService->metricsFor($collectors->pluck('id')->all(), $period);

            foreach ($collectors as $collector) {
                $achievement = $performanceService->achievementFromMetrics($metrics[(int) $collector->id], $period);

                $rows[] = [
                    $collector->collectorProfile?->collector_code ?? '-',
                    $collector->name,
                    $achievement['target_amount'],
                    $achievement['collected_amount'],
                    ($achievement['achievement_percent'] ?? 0).'%',
                    $achievement['visit_count'],
                    $achievement['successful_visit_count'],
                    $achievement['ptp_fulfilled'],
                    $achievement['collection_rate'] === null ? '-' : $achievement['collection_rate'].'%',
                    $achievement['outstanding_total'],
                ];
            }

            $path = "report-exports/{$export->id}.csv";
            $handle = fopen('php://temp', 'w+');
            fwrite($handle, "\xEF\xBB\xBF");
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            rewind($handle);
            Storage::disk('public')->put($path, stream_get_contents($handle));
            fclose($handle);

            $export->update([
                'status' => ReportExport::STATUS_COMPLETED,
                'file_path' => $path,
                'row_count' => count($rows) - 1,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $export->update([
                'status' => ReportExport::STATUS_FAILED,
                'failed_reason' => substr($e->getMessage(), 0, 1000),
            ]);
        }
    }
}
