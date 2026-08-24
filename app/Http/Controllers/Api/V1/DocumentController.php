<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Cluster;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\Unit;
use App\Services\PenaltyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function spt(Receipt $receipt)
    {
        $receipt->load(['unit.cluster', 'billings', 'paymentTransaction.allocations.billing']);

        return Pdf::loadHTML(view('pdf.spt', compact('receipt'))->render())
            ->download("SPT-{$receipt->number}.pdf");
    }

    public function spk(Billing $billing, PenaltyService $penaltyService)
    {
        $billing->load(['unit.cluster', 'status']);
        $penaltyDetail = $penaltyService->calculateInvoiceTotal($billing);

        return Pdf::loadHTML(view('pdf.spk', compact('billing', 'penaltyDetail'))->render())
            ->download("SPK-{$billing->id}.pdf");
    }

    public function billingRecap(Request $request, PenaltyService $penaltyService)
    {
        $billings = $this->filteredBillings($request)->get();
        $penaltyDetails = $billings->mapWithKeys(fn (Billing $billing) => [$billing->id => $penaltyService->calculateInvoiceTotal($billing)]);

        return Pdf::loadHTML(view('pdf.billing-recap', compact('billings', 'penaltyDetails'))->render())
            ->download('billing-recap.pdf');
    }

    /**
     * Ekspor CSV (dibuka langsung oleh Excel/Sheets, tanpa perlu dependency tambahan)
     * dari daftar tagihan sesuai filter yang sedang aktif di halaman Tagihan - bukan cuma
     * kolom mentah, tapi juga umur tunggakan/denda/total hasil hitung PenaltyService supaya
     * konsisten dengan yang tampil di layar.
     */
    public function billingRecapExcel(Request $request, PenaltyService $penaltyService)
    {
        $billings = $this->filteredBillings($request)->get();

        return response()->streamDownload(function () use ($billings, $penaltyService) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM supaya Excel mendeteksi UTF-8 dengan benar.
            fputcsv($out, [
                'ID', 'Penghuni', 'Unit', 'Cluster', 'Periode', 'Tipe', 'Pokok', 'Diskon',
                'Umur Tunggakan (bulan)', 'Denda', 'Total', 'Sudah Dibayar', 'Sisa Tagihan',
                'Status', 'Disetujui', 'Tanggal Disetujui',
            ]);

            foreach ($billings as $billing) {
                $detail = $penaltyService->calculateInvoiceTotal($billing);
                fputcsv($out, [
                    $billing->id,
                    $billing->unit->resident->name ?? '-',
                    $billing->unit_id,
                    $billing->unit->cluster->name ?? '-',
                    sprintf('%04d-%02d', $billing->year, $billing->month),
                    $billing->billing_type,
                    (float) $billing->amount,
                    (float) $billing->discount,
                    $detail['overdue_months'],
                    $detail['penalty_amount'],
                    $detail['total_amount'],
                    $detail['total_paid'],
                    $detail['total_outstanding'],
                    $billing->status->name ?? $billing->status_id,
                    $billing->approved_at ? 'Ya' : 'Tidak',
                    $billing->approved_at?->format('Y-m-d H:i') ?? '-',
                ]);
            }

            fclose($out);
        }, 'tagihan.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredBillings(Request $request)
    {
        return Billing::query()
            ->with(['unit.cluster', 'unit.resident', 'status'])
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->whereHas('unit', fn ($inner) => $inner->where('cluster_id', $value)))
            ->when($request->query('year'), fn ($q, $value) => $q->where('year', $value))
            ->when($request->query('month'), fn ($q, $value) => $q->where('month', $value))
            ->when($request->query('status_id'), fn ($q, $value) => $q->where('status_id', $value))
            ->orderBy('year')->orderBy('month');
    }

    public function paymentTransactions(Request $request)
    {
        $transactions = $this->filteredTransactions($request)->get();

        return Pdf::loadHTML(view('pdf.payment-transactions', compact('transactions'))->render())
            ->download('transaksi-gateway.pdf');
    }

    /**
     * Ekspor CSV (dibuka langsung oleh Excel/Sheets) dari daftar transaksi gateway sesuai
     * filter yang sedang aktif di tab "Transaksi Gateway" halaman Pembayaran.
     */
    public function paymentTransactionsExcel(Request $request)
    {
        $transactions = $this->filteredTransactions($request)->get();

        return response()->streamDownload(function () use ($transactions) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Invoice', 'Penghuni', 'Unit', 'Alamat Unit', 'Provider', 'Total', 'Status', 'Dibuat']);

            foreach ($transactions as $transaction) {
                fputcsv($out, [
                    $transaction->invoice_number,
                    $transaction->unit->resident->name ?? '-',
                    $transaction->unit_id,
                    trim(($transaction->unit->cluster->name ?? '').' '.($transaction->unit->block ?? '').'/'.($transaction->unit->lot_number ?? '')),
                    $transaction->payment_provider,
                    (float) $transaction->total,
                    $transaction->status,
                    $transaction->created_at?->format('Y-m-d H:i') ?? '-',
                ]);
            }

            fclose($out);
        }, 'transaksi-gateway.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredTransactions(Request $request)
    {
        return PaymentTransaction::query()
            ->with(['unit.cluster', 'unit.resident'])
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('transaction_number', 'like', "%{$value}%")
                ->orWhere('invoice_number', 'like', "%{$value}%")
                ->orWhere('provider_reference', 'like', "%{$value}%")
                ->orWhere('unit_id', 'like', "%{$value}%")
                ->orWhereHas('unit', fn ($u) => $u
                    ->where('block', 'like', "%{$value}%")
                    ->orWhere('lot_number', 'like', "%{$value}%")
                    ->orWhereHas('cluster', fn ($c) => $c->where('name', 'like', "%{$value}%"))
                    ->orWhereHas('resident', fn ($r) => $r->where('name', 'like', "%{$value}%")))))
            ->when($request->query('address'), fn ($q, $value) => $q->whereHas('unit', fn ($u) => $u
                ->where('block', 'like', "%{$value}%")
                ->orWhere('lot_number', 'like', "%{$value}%")
                ->orWhereHas('cluster', fn ($c) => $c->where('name', 'like', "%{$value}%"))))
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->whereHas('unit', fn ($u) => $u->where('cluster_id', $value)))
            ->when($request->query('customer'), fn ($q, $value) => $q->whereHas('unit.resident', fn ($r) => $r->where('name', 'like', "%{$value}%")))
            ->when($request->query('provider'), fn ($q, $value) => $q->where('payment_provider', $value))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('date_from'), fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($request->query('date_to'), fn ($q, $value) => $q->whereDate('created_at', '<=', $value))
            ->latest();
    }

    public function paymentReceipts(Request $request)
    {
        $receipts = $this->filteredReceipts($request)->get();

        return Pdf::loadHTML(view('pdf.payment-receipts', compact('receipts'))->render())
            ->download('riwayat-kuitansi.pdf');
    }

    /**
     * Ekspor CSV (dibuka langsung oleh Excel/Sheets) dari daftar kuitansi sesuai filter
     * yang sedang aktif di tab "Riwayat Kuitansi" halaman Pembayaran.
     */
    public function paymentReceiptsExcel(Request $request)
    {
        $receipts = $this->filteredReceipts($request)->get();

        return response()->streamDownload(function () use ($receipts) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Nomor', 'Penghuni', 'Unit', 'Alamat Unit', 'Tanggal', 'Periode', 'Total', 'Status', 'Kasir']);

            foreach ($receipts as $receipt) {
                fputcsv($out, [
                    $receipt->number,
                    $receipt->resident_name,
                    $receipt->unit_id,
                    trim(($receipt->cluster_name ?? '').' '.($receipt->block ?? '').'/'.($receipt->lot_number ?? '')),
                    $receipt->transaction_date?->format('Y-m-d H:i') ?? '-',
                    $receipt->billing_periods,
                    (float) $receipt->grand_total,
                    $receipt->status,
                    $receipt->cashier_name,
                ]);
            }

            fclose($out);
        }, 'riwayat-kuitansi.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredReceipts(Request $request)
    {
        return Receipt::query()
            ->with('unit.cluster')
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('number', 'like', "%{$value}%")
                ->orWhere('unit_id', 'like', "%{$value}%")
                ->orWhere('resident_name', 'like', "%{$value}%")))
            ->when($request->query('address'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('cluster_name', 'like', "%{$value}%")
                ->orWhere('block', 'like', "%{$value}%")
                ->orWhere('lot_number', 'like', "%{$value}%")))
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->whereHas('unit', fn ($u) => $u->where('cluster_id', $value)))
            ->when($request->query('customer'), fn ($q, $value) => $q->where('resident_name', 'like', "%{$value}%"))
            ->when($request->query('date_from'), fn ($q, $value) => $q->whereDate('transaction_date', '>=', $value))
            ->when($request->query('date_to'), fn ($q, $value) => $q->whereDate('transaction_date', '<=', $value))
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->latest('transaction_date');
    }

    public function residentList()
    {
        $units = Unit::with(['cluster', 'resident'])->orderBy('cluster_id')->orderBy('block')->get();

        return Pdf::loadHTML(view('pdf.resident-list', compact('units'))->render())
            ->download('resident-list.pdf');
    }

    public function clusterRecap()
    {
        $clusters = Cluster::withCount('units')->orderBy('name')->get();

        return Pdf::loadHTML(view('pdf.cluster-recap', compact('clusters'))->render())
            ->download('cluster-recap.pdf');
    }
}
