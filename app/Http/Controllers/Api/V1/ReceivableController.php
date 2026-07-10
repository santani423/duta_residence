<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\Receivable;
use Illuminate\Http\Request;

class ReceivableController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = Receivable::query()
            ->with(['unit.cluster', 'unit.customer'])
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('is_settled') !== null, fn ($q) => $q->where('is_settled', request()->boolean('is_settled')));

        return $this->paginated($query->latest('snapshot_date')->paginate($request->integer('per_page', 15)));
    }

    public function aging()
    {
        $today = now();
        $unpaid = Billing::query()->with('unit')->unpaid()->get();

        $buckets = [
            'lt_30' => 0,
            'd30_60' => 0,
            'd60_90' => 0,
            'gt_90' => 0,
        ];

        foreach ($unpaid as $billing) {
            $age = $today->diffInDays(now()->setDate($billing->year, $billing->month, 1));
            $bucket = match (true) {
                $age < 30 => 'lt_30',
                $age < 60 => 'd30_60',
                $age < 90 => 'd60_90',
                default => 'gt_90',
            };
            $buckets[$bucket] += (float) $billing->amount;
        }

        return $this->success($buckets);
    }
}
