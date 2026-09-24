<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\Cluster;
use App\Models\ClusterRateSchedule;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\CollectorAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClusterController extends Controller
{
    use ApiResponse;

    public function index(Request $request, CollectorAssignmentService $assignmentService)
    {
        $query = Cluster::query()->orderBy('name');

        if ($request->user()->hasRole('collector')) {
            $clusterIds = Unit::query()->whereIn('id', $assignmentService->unitIdsFor($request->user()))->pluck('cluster_id')->unique();
            $query->whereIn('id', $clusterIds);
        }

        return $this->success($query->get());
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'size:2', Rule::unique('clusters', 'id')],
            'name' => ['required', 'string', 'max:50'],
            'monthly_rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $cluster = Cluster::query()->create($data);
        ClusterRateSchedule::query()->create([
            'cluster_id' => $cluster->id,
            'rate' => $cluster->monthly_rate,
            'effective_date' => now()->toDateString(),
            'notes' => 'Tarif awal saat cluster dibuat.',
            'is_active' => true,
            'activated_at' => now(),
            'created_by' => $request->user()->id,
        ]);
        $auditService->log('cluster_created', 'clusters', 'CREATE', $cluster, [], $cluster->toArray());

        return $this->success($cluster, 'Cluster berhasil dibuat.', 201);
    }

    public function show(Request $request, Cluster $cluster, CollectorAssignmentService $assignmentService)
    {
        if ($request->user()->hasRole('collector')) {
            $clusterIds = Unit::query()->whereIn('id', $assignmentService->unitIdsFor($request->user()))->pluck('cluster_id')->unique();
            abort_unless($clusterIds->contains($cluster->id), 403, 'Cluster ini tidak ditugaskan kepada Anda.');
        }

        $cluster->loadCount('units');

        return $this->success([
            ...$cluster->toArray(),
            'current_rate_schedule' => $cluster->currentRateSchedule(),
            'next_rate_schedule' => $cluster->nextRateSchedule(),
        ]);
    }

    public function update(Request $request, Cluster $cluster, AuditService $auditService)
    {
        $data = $request->validate([
            'monthly_rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $old = $cluster->toArray();
        $cluster->update($data);
        $auditService->log('cluster_updated', 'clusters', 'UPDATE', $cluster, $old, $cluster->toArray());

        return $this->success($cluster->refresh(), 'Tarif klaster berhasil diperbarui.');
    }

    /**
     * Penghasilan cluster per bulan untuk 12 bulan terakhir (bergeser otomatis mengikuti bulan
     * berjalan). Hanya menghitung billing yang benar-benar lunas (STATUS_PAID) berdasarkan
     * tanggal pelunasan (paid_at) - bukan periode tagihan (year/month) - dan dijumlahkan dari
     * principal_paid + penalty_paid per baris billing sehingga tidak ada double counting
     * meskipun satu billing dibayar lewat beberapa transaksi/cicilan.
     */
    public function incomeStatistics(Request $request, Cluster $cluster, CollectorAssignmentService $assignmentService)
    {
        if ($request->user()->hasRole('collector')) {
            $clusterIds = Unit::query()->whereIn('id', $assignmentService->unitIdsFor($request->user()))->pluck('cluster_id')->unique();
            abort_unless($clusterIds->contains($cluster->id), 403, 'Cluster ini tidak ditugaskan kepada Anda.');
        }

        $end = now()->startOfMonth();
        $start = $end->copy()->subMonths(11);

        $monthlyIncome = collect();
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addMonth()) {
            $income = Billing::query()
                ->join('units', 'units.id', '=', 'billings.unit_id')
                ->where('units.cluster_id', $cluster->id)
                ->where('billings.status_id', Billing::STATUS_PAID)
                ->whereYear('billings.paid_at', $cursor->year)
                ->whereMonth('billings.paid_at', $cursor->month)
                ->selectRaw('COALESCE(SUM(billings.principal_paid + billings.penalty_paid), 0) as income')
                ->value('income');

            $monthlyIncome->push([
                'month' => $cursor->format('Y-m'),
                'label' => $cursor->copy()->locale('id')->translatedFormat('F Y'),
                'income' => round((float) $income, 2),
            ]);
        }

        return $this->success([
            'cluster_id' => $cluster->id,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->copy()->endOfMonth()->toDateString(),
            ],
            'monthly_income' => $monthlyIncome->values(),
            'total_income' => round($monthlyIncome->sum('income'), 2),
        ]);
    }

    public function destroy(Cluster $cluster, AuditService $auditService)
    {
        if ($cluster->units()->exists()) {
            return $this->error('Cluster tidak dapat dihapus karena masih memiliki unit.', 422);
        }

        $old = $cluster->toArray();
        $cluster->delete();
        $auditService->log('cluster_deleted', 'clusters', 'DELETE', $cluster, $old, []);

        return $this->success(null, 'Cluster berhasil dihapus.');
    }
}
