<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Services\PenaltyService;
use App\Support\UnitFilters;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReceivableController extends Controller
{
    use ApiResponse;

    /**
     * Daftar piutang (tagihan belum lunas) dihitung langsung dari tabel billings lewat
     * PenaltyService, bukan dari tabel `receivables` (snapshot lama yang tidak pernah diisi
     * oleh proses manapun) - supaya nominal denda/tunggakan yang ditampilkan selalu akurat
     * dan konsisten dengan halaman Tagihan.
     */
    public function index(Request $request, PenaltyService $penaltyService)
    {
        $now = now();
        $query = Billing::query()
            ->with(['unit.cluster', 'unit.resident'])
            ->tap(fn ($q) => UnitFilters::apply($q, $request))
            ->when(
                $request->query('status_id'),
                fn ($q, $value) => $q->where('status_id', $value),
                fn ($q) => $q->outstanding()
            );

        $paginator = $query->orderBy('year')->orderBy('month')->paginate($request->integer('per_page', 15));
        $paginator->setCollection($paginator->getCollection()->map(fn (Billing $billing) => [
            ...$billing->toArray(),
            'penalty_detail' => $penaltyService->calculateInvoiceTotal($billing, $now),
        ]));

        return $this->paginated($paginator);
    }

    public function aging(Request $request, PenaltyService $penaltyService)
    {
        $today = now();
        // Kartu umur piutang mengikuti filter Cluster/Blok/Unit/Customer/Alamat yang sama dengan tabel.
        $outstanding = UnitFilters::apply(Billing::query()->with('unit')->outstanding(), $request)->get();

        $dayBuckets = ['lt_30' => 0, 'd30_60' => 0, 'd60_90' => 0, 'gt_90' => 0];
        // Tier tunggakan sesuai aturan denda: 0 bulan (berjalan), 1-2 bulan, 3 bulan atau lebih.
        $tierBuckets = ['current' => 0, 'tier_1_2_months' => 0, 'tier_3_plus_months' => 0];

        foreach ($outstanding as $billing) {
            $amount = $penaltyService->calculateInvoiceTotal($billing, $today);
            // abs() wajib di sini - Carbon 3's diffInDays() mengembalikan nilai signed
            // (negatif untuk tanggal yang sudah lewat), tanpa abs() semua tagihan lama akan
            // salah masuk ke bucket "< 30 hari" karena umur negatif selalu < 30.
            $periodStart = Carbon::create((int) $billing->year, (int) $billing->month, 1)->startOfDay();
            $age = abs($today->copy()->startOfDay()->diffInDays($periodStart));
            $dayBucket = match (true) {
                $age < 30 => 'lt_30',
                $age < 60 => 'd30_60',
                $age < 90 => 'd60_90',
                default => 'gt_90',
            };
            $dayBuckets[$dayBucket] += $amount['total_outstanding'];

            $tierBucket = match (true) {
                $amount['overdue_months'] === 0 => 'current',
                $amount['overdue_months'] <= 2 => 'tier_1_2_months',
                default => 'tier_3_plus_months',
            };
            $tierBuckets[$tierBucket] += $amount['total_outstanding'];
        }

        return $this->success([
            'day_buckets' => $dayBuckets,
            'penalty_tier_buckets' => $tierBuckets,
            ...$dayBuckets,
        ]);
    }
}
