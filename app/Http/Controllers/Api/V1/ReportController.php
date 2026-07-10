<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\Cluster;
use App\Models\Receipt;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;

    public function dashboard()
    {
        return $this->success([
            'total_clusters' => Cluster::count(),
            'total_customers' => Unit::count(),
            'total_billings' => Billing::count(),
            'unpaid_billings' => Billing::where('status_id', '01')->count(),
            'paid_billings' => Billing::where('status_id', '02')->count(),
            'today_receipts_total' => Receipt::whereDate('transaction_date', today())->sum('grand_total'),
            'recent_receipts' => Receipt::with('unit.customer')->latest('transaction_date')->limit(5)->get(),
        ]);
    }

    public function monthly(Request $request)
    {
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $data = Billing::query()
            ->select('units.cluster_id', DB::raw('COUNT(*) as billing_count'), DB::raw('SUM(amount) as total_billing'), DB::raw('SUM(CASE WHEN billings.status_id = "02" THEN amount + penalty ELSE 0 END) as total_paid'))
            ->join('units', 'units.id', '=', 'billings.unit_id')
            ->where('year', $year)
            ->where('month', $month)
            ->groupBy('units.cluster_id')
            ->get();

        return $this->success($data);
    }

    public function dailyReceipt(Request $request)
    {
        $date = $request->query('date', today()->toDateString());
        $receipts = Receipt::query()->with('unit.cluster')->whereDate('transaction_date', $date)->get();

        return $this->success([
            'date' => $date,
            'total_transactions' => $receipts->count(),
            'grand_total' => $receipts->sum('grand_total'),
            'receipts' => $receipts,
        ]);
    }

    public function reconciliation(Request $request)
    {
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);
        $billings = Billing::query()->where('year', $year)->where('month', $month);

        return $this->success([
            'period' => sprintf('%04d-%02d', $year, $month),
            'total_billing' => (clone $billings)->sum('amount'),
            'total_paid' => (clone $billings)->where('status_id', '02')->sum(DB::raw('amount + penalty - discount')),
            'total_outstanding' => (clone $billings)->where('status_id', '01')->sum('amount'),
        ]);
    }

    public function collector(Request $request)
    {
        $date = $request->query('date', today()->toDateString());
        $data = Receipt::query()
            ->select('cashier_name', DB::raw('COUNT(*) as transaction_count'), DB::raw('SUM(grand_total) as grand_total'))
            ->whereDate('transaction_date', $date)
            ->groupBy('cashier_name')
            ->get();

        return $this->success($data);
    }
}
