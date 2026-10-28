<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\Cluster;
use App\Models\ClusterRateSchedule;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\ClusterCodeService;
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
            ...$this->codeRules(),
            'monthly_rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (! empty($data['code_prefix'])) {
            app(ClusterCodeService::class)->assertNoCodeConflict($data['code_prefix'], $data['allowed_blocks'] ?? null);
        }

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
            ...$this->codeRules(),
            'monthly_rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $codeService = app(ClusterCodeService::class);
        $codeChanged = $this->codeConfigChanged($cluster, $data);

        if ($codeChanged) {
            // Prefix/blok yang sudah dipakai unit tidak diubah diam-diam karena kode unit lama bisa jadi tidak valid.
            if ($cluster->code_prefix !== null && $cluster->units()->exists()) {
                return $this->error('Prefix dan konfigurasi blok tidak dapat diubah karena cluster sudah memiliki unit.', 422);
            }

            $newPrefix = array_key_exists('code_prefix', $data) ? $data['code_prefix'] : $cluster->code_prefix;
            if ($newPrefix !== null) {
                $codeService->assertNoCodeConflict($newPrefix, array_key_exists('allowed_blocks', $data) ? $data['allowed_blocks'] : $cluster->allowed_blocks, $cluster->id);
            }
        }

        $old = $cluster->toArray();
        $cluster->update($data);

        if ($codeChanged) {
            // Cluster yang baru diberi prefix: isi nomor blok unit lama yang kodenya valid (sisanya dibiarkan NULL).
            $codeService->flush();
            $codeService->backfillUnitBlockNumbers();
        }
        $auditService->log('cluster_updated', 'clusters', 'UPDATE', $cluster, $old, $cluster->toArray());

        return $this->success($cluster->refresh(), 'Tarif klaster berhasil diperbarui.');
    }

    /** Daftar nomor blok yang valid untuk cluster (dropdown Blok pada form unit/filter). */
    public function blocks(Cluster $cluster, ClusterCodeService $codeService)
    {
        return $this->success([
            'cluster_id' => $cluster->id,
            'code_prefix' => $cluster->code_prefix,
            'area_type' => $cluster->area_type,
            'blocks' => collect($codeService->blockNumbersFor($cluster))
                ->map(fn (int $number) => ['block_number' => $number, 'code' => $cluster->code_prefix ? $codeService->compose($cluster, $number) : (string) $number, 'label' => "Blok {$number}"])
                ->values(),
        ]);
    }

    private function codeRules(): array
    {
        return [
            'code_prefix' => ['sometimes', 'nullable', 'string', 'regex:/^[A-Z]{1,5}$/'],
            'area_type' => ['sometimes', Rule::in(Cluster::AREA_TYPES)],
            'allowed_blocks' => ['sometimes', 'nullable', 'array', 'min:1'],
            'allowed_blocks.*' => ['integer', 'min:1', 'max:99999', 'distinct'],
        ];
    }

    private function codeConfigChanged(Cluster $cluster, array $data): bool
    {
        foreach (['code_prefix', 'allowed_blocks'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] != $cluster->{$field}) {
                return true;
            }
        }

        return false;
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
