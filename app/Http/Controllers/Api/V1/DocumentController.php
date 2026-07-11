<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Cluster;
use App\Models\Receipt;
use App\Models\Unit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function spt(Receipt $receipt)
    {
        $receipt->load(['unit.cluster', 'billings']);

        return Pdf::loadHTML(view('pdf.spt', compact('receipt'))->render())
            ->download("SPT-{$receipt->number}.pdf");
    }

    public function spk(Billing $billing)
    {
        $billing->load('unit.cluster');

        return Pdf::loadHTML(view('pdf.spk', compact('billing'))->render())
            ->download("SPK-{$billing->id}.pdf");
    }

    public function billingRecap(Request $request)
    {
        $billings = Billing::with('unit.cluster')
            ->when($request->query('year'), fn ($q, $value) => $q->where('year', $value))
            ->when($request->query('month'), fn ($q, $value) => $q->where('month', $value))
            ->get();

        return Pdf::loadHTML(view('pdf.billing-recap', compact('billings'))->render())
            ->download('billing-recap.pdf');
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
