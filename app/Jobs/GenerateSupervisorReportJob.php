<?php

namespace App\Jobs;

use App\Models\ReportExport;
use App\Models\User;
use App\Services\CollectorPerformanceService;
use App\Services\SupervisorAssignmentService;
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

    public function handle(SupervisorAssignmentService $scopeService, CollectorPerformanceService $performanceService): void
    {
        $export = ReportExport::query()->find($this->reportExportId);
        if (! $export) {
            return;
        }

        $export->update(['status' => ReportExport::STATUS_PROCESSING]);

        try {
            $requester = User::query()->findOrFail($export->requested_by);
            $collectorIds = $scopeService->collectorIdsFor($requester);
            $filters = $export->filters ?? [];
            $periodType = $filters['period_type'] ?? 'monthly';
            $periodStart = $filters['period_start'] ?? now()->startOfMonth()->toDateString();

            $rows = [['Kode Kolektor', 'Nama Kolektor', 'Target', 'Tercapai', 'Persentase', 'Jumlah Kunjungan']];
            $collectors = User::query()->whereIn('id', $collectorIds)->with('collectorProfile')->get();

            foreach ($collectors as $collector) {
                $achievement = $performanceService->achievementFor($collector, $periodType, $periodStart);

                $rows[] = [
                    $collector->collectorProfile?->collector_code ?? '-',
                    $collector->name,
                    $achievement['target_amount'],
                    $achievement['collected_amount'],
                    ($achievement['achievement_percent'] ?? 0).'%',
                    $achievement['visit_count'],
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
