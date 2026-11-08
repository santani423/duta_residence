<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\Cluster;
use App\Models\Receipt;
use App\Models\Unit;
use App\Support\DashboardDateRange;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;

    /**
     * Penerimaan mengikuti rentang tanggal (start/end, YYYY-MM-DD; default bulan ini sampai hari
     * ini). Angka lain (jumlah cluster/penghuni/tagihan) tetap snapshot.
     */
    public function dashboard(Request $request)
    {
        [$start, $end] = DashboardDateRange::fromRequest($request);

        return $this->success([
            'total_clusters' => Cluster::count(),
            'total_residents' => Unit::count(),
            'total_billings' => Billing::count(),
            'unpaid_billings' => Billing::outstanding()->count(),
            'paid_billings' => Billing::where('status_id', Billing::STATUS_PAID)->count(),
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            'period_receipts_total' => $this->receiptsBetween($start, $end)->sum('grand_total'),
            'today_receipts_total' => Receipt::whereDate('transaction_date', today())->sum('grand_total'),
            'recent_receipts' => Receipt::with('unit.resident')->latest('transaction_date')->limit(5)->get(),
            'unit_occupancy' => $this->unitOccupancySummary(),
        ]);
    }

    private function receiptsBetween(Carbon $start, Carbon $end)
    {
        return Receipt::query()
            ->whereDate('transaction_date', '>=', $start)
            ->whereDate('transaction_date', '<=', $end);
    }

    /**
     * Ready Stock/Tanah Kosong/Booked/Occupied dihitung dari Unit::scopeOccupancyStatus (lihat
     * Unit::getOccupancyStatusAttribute) supaya angkanya selalu sama dengan status yang
     * ditampilkan di halaman Unit/Cluster, bukan dihitung ulang dengan logic terpisah.
     */
    private function unitOccupancySummary(): array
    {
        return [
            'total_units' => Unit::count(),
            'ready_stock' => Unit::occupancyStatus(Unit::OCCUPANCY_STATUS_READY_STOCK)->count(),
            'tanah_kosong' => Unit::occupancyStatus(Unit::OCCUPANCY_STATUS_TANAH_KOSONG)->count(),
            'booked' => Unit::occupancyStatus(Unit::OCCUPANCY_STATUS_BOOKED)->count(),
            'occupied' => Unit::occupancyStatus(Unit::OCCUPANCY_STATUS_OCCUPIED)->count(),
        ];
    }

    /**
     * Tanpa start/end: satu periode tagihan (year/month). Dengan start/end: semua periode tagihan
     * (bulan) yang jatuh di dalam rentang tanggal itu.
     */
    public function monthly(Request $request)
    {
        $query = Billing::query()
            ->select('units.cluster_id', DB::raw('COUNT(*) as billing_count'), DB::raw('SUM(amount) as total_billing'), DB::raw('SUM(billings.principal_paid + billings.penalty_paid) as total_paid'))
            ->join('units', 'units.id', '=', 'billings.unit_id')
            ->groupBy('units.cluster_id');

        if ($request->filled('start') || $request->filled('end')) {
            [$start, $end] = DashboardDateRange::fromRequest($request);
            $query->whereRaw('(billings.year * 100 + billings.month) BETWEEN ? AND ?', [
                $start->year * 100 + $start->month,
                $end->year * 100 + $end->month,
            ]);
        } else {
            $query->where('billings.year', $request->integer('year', now()->year))
                ->where('billings.month', $request->integer('month', now()->month));
        }

        return $this->success($query->get());
    }

    public function dailyReceipt(Request $request)
    {
        $date = $request->query('date', today()->toDateString());
        $receipts = Receipt::query()->with('unit.cluster')->whereDate('transaction_date', $date)->latest('transaction_date')->get();

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

        // Dihitung per baris (bukan SUM di SQL) supaya pakai floor non-negatif yang sama dengan
        // PenaltyService::calculateInvoiceTotal(), bukan rumus SUM terpisah yang bisa minus.
        $outstandingPrincipal = (clone $billings)->outstanding()->get(['amount', 'discount', 'principal_paid'])
            ->sum(fn (Billing $billing) => max(0.0, (float) $billing->amount - (float) $billing->discount - (float) $billing->principal_paid));

        return $this->success([
            'period' => sprintf('%04d-%02d', $year, $month),
            'total_billing' => (clone $billings)->sum('amount'),
            'total_paid' => (clone $billings)->sum(DB::raw('principal_paid + penalty_paid')),
            // Pokok yang belum tertagih saja (belum termasuk denda berjalan) - untuk total
            // tunggakan termasuk denda dinamis, lihat BillingController::summary().
            'total_outstanding_principal' => round($outstandingPrincipal, 2),
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
