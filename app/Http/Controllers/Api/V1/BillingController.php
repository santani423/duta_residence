<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\BillingService;
use App\Services\ClusterRateScheduleService;
use App\Services\DiscountService;
use App\Services\PenaltyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    use ApiResponse;

    public function index(Request $request, PenaltyService $penaltyService)
    {
        $now = now();
        $query = $this->filteredQuery($request, $penaltyService, $now);

        // Periode terbaru di atas; dalam periode yang sama, tagihan yang paling baru diperbarui di atas.
        $paginator = $query->orderByDesc('year')->orderByDesc('month')->orderByDesc('updated_at')->orderByDesc('id')->paginate($request->integer('per_page', 15));
        $paginator->setCollection($paginator->getCollection()->map(fn (Billing $billing) => [
            ...$billing->toArray(),
            'penalty_detail' => $penaltyService->calculateInvoiceTotal($billing, $now),
        ]));

        return $this->paginated($paginator);
    }

    /**
     * Ringkasan tunggakan untuk dashboard admin: total pokok, total denda, total keseluruhan,
     * jumlah tagihan terlambat, dan jumlah unit yang menunggak. Dihitung dari satu query
     * tagihan outstanding (bukan query per baris) lalu diagregasi lewat PenaltyService.
     */
    public function summary(Request $request, PenaltyService $penaltyService)
    {
        $now = now();
        $billings = $this->filteredQuery($request, $penaltyService, $now)->outstanding()->get();

        $items = $billings->map(fn (Billing $billing) => $penaltyService->calculateInvoiceTotal($billing, $now));

        return $this->success([
            'invoice_count' => $items->count(),
            'total_principal_outstanding' => round($items->sum('outstanding_principal'), 2),
            'total_penalty_outstanding' => round($items->sum('outstanding_penalty'), 2),
            'total_outstanding' => round($items->sum('total_outstanding'), 2),
            'overdue_invoice_count' => $items->filter(fn (array $row) => $row['overdue_months'] >= 1)->count(),
            'unit_count' => $billings->pluck('unit_id')->unique()->count(),
        ]);
    }

    public function prepareMonthly(Request $request, BillingService $service, AuditService $auditService)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ]);

        $billings = $service->prepareMonthly($data['year'], $data['month'], $request->user()->id);
        $auditService->log('monthly_billing_prepared', 'billings', 'PREPARE', null, [], $data);

        return $this->success(['count' => $billings->count(), 'billings' => $billings->values()], 'Tagihan bulanan berhasil disiapkan.', 201);
    }

    public function prepareSpecial(Request $request, BillingService $service)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $billing = $service->prepareSpecial(Unit::findOrFail($data['unit_id']), $data['year'], $data['month'], $data['amount'], $request->user()->id);

        return $this->success($billing->load('unit'), 'Tagihan khusus berhasil dibuat.', 201);
    }

    /**
     * Preview-only lookup for the "Tagihan Mundur" form: resolves the same read-only IPL
     * nominal that prepareBack() would use, without creating any billing.
     */
    public function previewBackRate(Request $request, ClusterRateScheduleService $rateScheduleService)
    {
        $data = $request->validate([
            'unit_id' => ['required', Rule::exists('units', 'id')->where('status_id', 'AK')],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ], [
            'unit_id.exists' => 'Unit tidak ditemukan atau sudah tidak aktif.',
        ]);

        $unit = Unit::with('cluster')->findOrFail($data['unit_id']);

        return $this->success($rateScheduleService->resolveRateForBackdatedPeriod($unit->cluster, $data['year'], $data['month']));
    }

    /**
     * Where the "Tagihan Mundur" range must start for a unit: the month after its last billing.
     * The start is fixed; only the end of the range is the user's choice.
     */
    public function backRange(Request $request, BillingService $service)
    {
        $data = $request->validate([
            'unit_id' => ['required', Rule::exists('units', 'id')->where('status_id', 'AK')],
        ], [
            'unit_id.exists' => 'Unit tidak ditemukan atau sudah tidak aktif.',
        ]);

        $unit = Unit::findOrFail($data['unit_id']);
        $last = $service->lastBilledPeriod($unit);
        $start = $service->backStartPeriod($unit);

        return $this->success([
            'last_billed_period' => $last ? ['year' => $last->year, 'month' => $last->month] : null,
            'start_period' => ['year' => $start->year, 'month' => $start->month],
        ]);
    }

    public function prepareBack(Request $request, BillingService $service)
    {
        $data = $request->validate([
            'unit_id' => ['required', Rule::exists('units', 'id')->where('status_id', 'AK')],
            'periods' => ['required', 'array', 'min:1'],
            'periods.*.year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'periods.*.month' => ['required', 'integer', 'between:1,12'],
        ], [
            'unit_id.exists' => 'Unit tidak ditemukan atau sudah tidak aktif.',
        ]);

        $unit = Unit::with('cluster')->findOrFail($data['unit_id']);

        // The range must begin right after the unit's last billed month and run without gaps.
        $expected = $service->backStartPeriod($unit);
        foreach ($data['periods'] as $index => $period) {
            if ((int) $period['year'] !== $expected->year || (int) $period['month'] !== $expected->month) {
                throw ValidationException::withMessages([
                    "periods.{$index}" => ["Periode tagihan mundur harus berurutan dan dimulai dari {$expected->copy()->locale('id')->translatedFormat('F Y')} (bulan setelah tagihan terakhir unit ini)."],
                ]);
            }
            $expected = $expected->copy()->addMonthNoOverflow();
        }

        $billings = DB::transaction(fn () => collect($data['periods'])->map(
            fn ($period) => $service->prepareBack($unit, $period['year'], $period['month'], $request->user()->id)
        ));

        return $this->success($billings->values(), 'Tagihan mundur berhasil dibuat.', 201);
    }

    public function pendingApproval(Request $request)
    {
        $query = Billing::query()->with(['unit.cluster', 'unit.resident'])->whereNull('approved_at')->where('status_id', '01');

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function approve(Request $request, Billing $billing, BillingService $service)
    {
        $data = $request->validate(['approval_notes' => ['nullable', 'string', 'max:200']]);

        return $this->success($service->approve($billing, $request->user()->id, $data['approval_notes'] ?? null), 'Tagihan berhasil disetujui.');
    }

    public function approveBatch(Request $request, BillingService $service)
    {
        $data = $request->validate([
            'billing_ids' => ['required', 'array', 'min:1'],
            'billing_ids.*' => ['integer', 'exists:billings,id'],
            'approval_notes' => ['nullable', 'string', 'max:200'],
        ]);

        $billings = Billing::query()->whereIn('id', $data['billing_ids'])->get()
            ->map(fn (Billing $billing) => $service->approve($billing, $request->user()->id, $data['approval_notes'] ?? null));

        return $this->success($billings->values(), 'Tagihan batch berhasil disetujui.');
    }

    /**
     * Override manual nominal diskon oleh admin berwenang - berlaku kapan saja sebelum
     * tagihan lunas, tidak terikat pada aturan diskon otomatis milik unit.
     */
    public function updateDiscount(Request $request, Billing $billing, DiscountService $service)
    {
        $data = $request->validate([
            'discount' => ['required', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:200'],
        ]);

        $billing = $service->applyManualDiscount($billing, (float) $data['discount'], $data['reason'], $request->user()->id);

        return $this->success($billing->fresh(['unit.cluster', 'unit.resident', 'status']), 'Diskon tagihan berhasil diperbarui.');
    }

    /**
     * Filter bersama untuk daftar tagihan dan ringkasannya, supaya total di kartu ringkasan
     * selalu sama dengan baris yang tampil. Sengaja TIDAK ada filter tahun bawaan: tahun hanya
     * berlaku bila diminta eksplisit lewat query `year`.
     */
    private function filteredQuery(Request $request, PenaltyService $penaltyService, Carbon $now): Builder
    {
        return Billing::query()
            ->with(['unit.cluster', 'unit.resident', 'status', 'approver'])
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('resident_id'), fn ($q, $value) => $q->whereHas('unit', fn ($inner) => $inner->where('resident_id', $value)))
            ->when($request->query('year'), fn ($q, $value) => $q->where('year', $value))
            ->when($request->query('month'), fn ($q, $value) => $q->where('month', $value))
            ->when($request->query('status_id'), fn ($q, $value) => $q->where('status_id', $value))
            // Halaman "Tagihan": hanya yang belum lunas (Belum Bayar + Sebagian) dari SEMUA tahun.
            // Riwayat Tagihan tidak mengirim flag ini sehingga semua status ikut tampil.
            ->when($request->boolean('outstanding'), fn ($q) => $q->outstanding())
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->whereHas('unit', fn ($inner) => $inner->where('cluster_id', $value)))
            // Umur tunggakan dihitung murni dari selisih year/month (tanpa GREATEST/MAX untuk
            // tetap kompatibel lintas driver - nilai negatif otomatis gagal filter ambang >= 0).
            ->when($request->filled('min_overdue_months'), fn ($q) => $this->whereOverdueMonths($q, $now, '>=', $request->integer('min_overdue_months')))
            ->when($request->filled('max_overdue_months'), fn ($q) => $this->whereOverdueMonths($q, $now, '<=', $request->integer('max_overdue_months')))
            // Pendekatan (bukan hasil PenaltyService penuh): tidak memperhitungkan penalty_rule
            // per-tier yang bernilai 0, penalty_waived_amount, atau override per cluster - hanya
            // aproksimasi murah "outstanding, eligible, dan sudah lewat bulan berjalan" agar filter
            // ini tetap satu query SQL, bukan N+1. Nonaktifkan sepenuhnya saat saklar global mati,
            // supaya tidak bertentangan dengan penalty_amount=0 yang ditampilkan PenaltyService.
            ->when($request->filled('has_penalty'), function ($q) use ($request, $penaltyService, $now) {
                $wantsPenalty = $request->boolean('has_penalty');

                if (! $penaltyService->isPenaltyEnabled()) {
                    return $wantsPenalty ? $q->whereRaw('1 = 0') : $q->outstanding();
                }

                return $this->whereOverdueMonths($q->outstanding()->where('is_penalty_eligible', true), $now, $wantsPenalty ? '>=' : '<', 1);
            });
    }

    private function whereOverdueMonths(Builder $query, Carbon $now, string $operator, int $threshold): Builder
    {
        return $query->whereRaw("((? - year) * 12 + (? - month)) {$operator} ?", [$now->year, $now->month, $threshold]);
    }
}
