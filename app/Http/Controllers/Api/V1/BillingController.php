<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\Unit;
use App\Services\AuditService;
use App\Services\BillingService;
use App\Services\PenaltyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BillingController extends Controller
{
    use ApiResponse;

    public function index(Request $request, PenaltyService $penaltyService)
    {
        $now = now();
        $query = Billing::query()
            ->with(['unit.cluster', 'unit.resident', 'status', 'approver'])
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('resident_id'), fn ($q, $value) => $q->whereHas('unit', fn ($inner) => $inner->where('resident_id', $value)))
            ->when($request->query('year'), fn ($q, $value) => $q->where('year', $value))
            ->when($request->query('month'), fn ($q, $value) => $q->where('month', $value))
            ->when($request->query('status_id'), fn ($q, $value) => $q->where('status_id', $value))
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

        $paginator = $query->latest()->paginate($request->integer('per_page', 15));
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
        $billings = Billing::query()
            ->with('unit')
            ->outstanding()
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->whereHas('unit', fn ($inner) => $inner->where('cluster_id', $value)))
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->get();

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

    public function prepareBack(Request $request, BillingService $service)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'periods' => ['required', 'array', 'min:1'],
            'periods.*.year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'periods.*.month' => ['required', 'integer', 'between:1,12'],
            'periods.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $unit = Unit::findOrFail($data['unit_id']);
        $billings = collect($data['periods'])->map(fn ($period) => tap(
            $service->prepareSpecial($unit, $period['year'], $period['month'], $period['amount'], $request->user()->id),
            fn (Billing $billing) => $billing->update(['billing_type' => 'back'])
        ));

        return $this->success($billings->values(), 'Tagihan mundur berhasil dibuat.', 201);
    }

    public function pendingApproval(Request $request)
    {
        $query = Billing::query()->with(['unit.cluster', 'unit.resident'])->whereNull('approved_at')->where('status_id', '01');

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
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

    private function whereOverdueMonths(Builder $query, Carbon $now, string $operator, int $threshold): Builder
    {
        return $query->whereRaw("((? - year) * 12 + (? - month)) {$operator} ?", [$now->year, $now->month, $threshold]);
    }
}
