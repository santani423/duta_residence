<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\GenerateSupervisorReportJob;
use App\Models\ReportExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupervisorReportController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = ReportExport::query()
            ->where('requested_by', $request->user()->id)
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    /**
     * Multi-period/bulk exports only (spec K) - queued via GenerateSupervisorReportJob, the
     * first background job in this codebase. Small single-period reports stay synchronous
     * through the existing ReportController/DocumentController endpoints, unchanged.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['collector_performance'])],
            'format' => ['nullable', Rule::in(['csv'])],
            'period_type' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'period_start' => ['nullable', 'date'],
        ]);

        $export = ReportExport::query()->create([
            'type' => $data['type'],
            'format' => $data['format'] ?? 'csv',
            'status' => ReportExport::STATUS_QUEUED,
            'filters' => [
                'period_type' => $data['period_type'] ?? 'monthly',
                'period_start' => $data['period_start'] ?? now()->startOfMonth()->toDateString(),
            ],
            'requested_by' => $request->user()->id,
        ]);

        GenerateSupervisorReportJob::dispatch($export->id);

        return $this->success($export, 'Permintaan laporan sedang diproses.', 201);
    }

    public function show(Request $request, ReportExport $reportExport)
    {
        abort_unless($reportExport->requested_by === $request->user()->id, 403);

        return $this->success($reportExport);
    }

    public function download(Request $request, ReportExport $reportExport)
    {
        abort_unless($reportExport->requested_by === $request->user()->id, 403);
        abort_unless($reportExport->status === ReportExport::STATUS_COMPLETED && $reportExport->file_path, 404, 'Laporan belum siap diunduh.');

        return Storage::disk('public')->download($reportExport->file_path, "laporan-{$reportExport->type}-{$reportExport->id}.csv");
    }
}
