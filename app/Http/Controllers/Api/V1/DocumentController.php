<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Cluster;
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
