<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\Receivable;
use App\Services\PenaltyService;
use Illuminate\Http\Request;

class ReceivableController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Receivable::query()
            ->with(['unit.cluster', 'unit.resident'])
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('is_settled') !== null, fn ($q) => $q->where('is_settled', request()->boolean('is_settled')));

        return $this->paginated($query->latest('snapshot_date')->paginate($request->integer('per_page', 15)));
    }

    public function aging(PenaltyService $penaltyService)
    {
        $today = now();
        $outstanding = Billing::query()->with('unit')->outstanding()->get();

        $dayBuckets = ['lt_30' => 0, 'd30_60' => 0, 'd60_90' => 0, 'gt_90' => 0];
        // Tier tunggakan sesuai aturan denda: 0 bulan (berjalan), 1-2 bulan, 3 bulan atau lebih.
        $tierBuckets = ['current' => 0, 'tier_1_2_months' => 0, 'tier_3_plus_months' => 0];

        foreach ($outstanding as $billing) {
            $amount = $penaltyService->calculateInvoiceTotal($billing, $today);
            $age = $today->diffInDays(now()->setDate($billing->year, $billing->month, 1));
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
