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
use App\Support\DashboardDateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClusterController extends Controller
{
    use ApiResponse;

    /** Batas jumlah bulan untuk grafik penghasilan cluster (mencegah query per bulan yang tak terbatas). */
    private const MAX_INCOME_RANGE_MONTHS = 60;

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
            'id' => ['required', 'string', 'regex:/^[A-Z]{1,2}$/', Rule::unique('clusters', 'id')],
            'name' => ['required', 'string', 'max:50'],
            ...$this->codeRules(),
            'monthly_rate' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ], ['id.regex' => 'Kode cluster harus 1-2 huruf kapital tanpa angka.']);

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
     * Penghasilan cluster per bulan dalam rentang tanggal (`start` & `end`, YYYY-MM-DD, inklusif).
     * Tanpa parameter: 12 bulan terakhir (bergeser otomatis mengikuti bulan berjalan).
     *
     * Penghasilan = uang yang benar-benar diterima, dikelompokkan menurut tanggal bayar (bukan
     * periode tagihan year/month):
     * - Sumber utama: payment_allocations dari transaksi berstatus `paid`, per tanggal paid_at
     *   transaksi. Cicilan/pembayaran sebagian ikut terhitung pada tanggal masing-masing, dan
     *   transaksi yang di-reversal (status `reversed`) otomatis tidak terhitung.
     * - Data lama tanpa baris alokasi: billing lunas (STATUS_PAID) per paid_at billing, dijumlahkan
     *   dari principal_paid + penalty_paid. Billing yang sudah punya alokasi tidak dihitung lagi
     *   di sini, jadi tidak ada double counting.
     * Bulan parsial di ujung rentang hanya menghitung pembayaran yang jatuh di dalam rentang,
     * sehingga total = jumlah seluruh pembayaran dalam rentang.
     */
    public function incomeStatistics(Request $request, Cluster $cluster, CollectorAssignmentService $assignmentService)
    {
        if ($request->user()->hasRole('collector')) {
            $clusterIds = Unit::query()->whereIn('id', $assignmentService->unitIdsFor($request->user()))->pluck('cluster_id')->unique();
            abort_unless($clusterIds->contains($cluster->id), 403, 'Cluster ini tidak ditugaskan kepada Anda.');
        }

        [$start, $rangeEnd] = DashboardDateRange::fromRequest(
            $request,
            now()->subMonths(11)->startOfMonth(),
            now()->endOfMonth(),
        );
        $end = $rangeEnd->endOfDay();

        $months = (int) $start->copy()->startOfMonth()->diffInMonths($end->copy()->startOfMonth()) + 1;
        if ($months > self::MAX_INCOME_RANGE_MONTHS) {
            throw ValidationException::withMessages(['start' => 'Rentang maksimal '.self::MAX_INCOME_RANGE_MONTHS.' bulan.']);
        }

        $incomeByMonth = $this->incomeByMonth($cluster, $start, $end);

        $monthlyIncome = collect();
        for ($cursor = $start->copy()->startOfMonth(); $cursor->lte($end); $cursor->addMonth()) {
            $monthlyIncome->push([
                'month' => $cursor->format('Y-m'),
                'label' => $cursor->copy()->locale('id')->translatedFormat('F Y'),
                'income' => round($incomeByMonth[$cursor->format('Y-m')] ?? 0.0, 2),
            ]);
        }

        return $this->success([
            'cluster_id' => $cluster->id,
            'period' => [
                'start' => $start->toDateString(),
                'end' => $rangeEnd->toDateString(),
            ],
            'monthly_income' => $monthlyIncome->values(),
            'total_income' => round($monthlyIncome->sum('income'), 2),
        ]);
    }

    /**
     * Total pembayaran per bulan (kunci `Y-m`) untuk unit di cluster ini dalam rentang [$start, $end].
     * Dijumlahkan per hari di SQL (DATE() berlaku di MySQL & SQLite), lalu digabung per bulan di PHP.
     *
     * @return array<string, float>
     */
    private function incomeByMonth(Cluster $cluster, Carbon $start, Carbon $end): array
    {
        $allocated = DB::table('payment_allocations')
            ->join('payment_transactions', 'payment_transactions.id', '=', 'payment_allocations.payment_transaction_id')
            ->join('billings', 'billings.id', '=', 'payment_allocations.billing_id')
            ->join('units', 'units.id', '=', 'billings.unit_id')
            ->where('units.cluster_id', $cluster->id)
            ->where('payment_transactions.status', 'paid')
            ->whereBetween('payment_transactions.paid_at', [$start, $end])
            ->groupByRaw('DATE(payment_transactions.paid_at)')
            ->selectRaw('DATE(payment_transactions.paid_at) as paid_date, SUM(payment_allocations.principal_amount + payment_allocations.penalty_amount) as income')
            ->get();

        $legacy = DB::table('billings')
            ->join('units', 'units.id', '=', 'billings.unit_id')
            ->where('units.cluster_id', $cluster->id)
            ->where('billings.status_id', Billing::STATUS_PAID)
            ->whereBetween('billings.paid_at', [$start, $end])
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('payment_allocations')
                ->whereColumn('payment_allocations.billing_id', 'billings.id'))
            ->groupByRaw('DATE(billings.paid_at)')
            ->selectRaw('DATE(billings.paid_at) as paid_date, SUM(billings.principal_paid + billings.penalty_paid) as income')
            ->get();

        $byMonth = [];
        foreach ($allocated->concat($legacy) as $row) {
            $month = substr((string) $row->paid_date, 0, 7);
            $byMonth[$month] = ($byMonth[$month] ?? 0.0) + (float) $row->income;
        }

        return $byMonth;
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
