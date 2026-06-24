<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\Receipt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function spt(Receipt $receipt)
    {
        $receipt->load(['customer.cluster', 'billings']);

        return Pdf::loadHTML(view('pdf.spt', compact('receipt'))->render())
            ->download("SPT-{$receipt->number}.pdf");
    }

    public function spk(Billing $billing)
    {
        $billing->load('customer.cluster');

        return Pdf::loadHTML(view('pdf.spk', compact('billing'))->render())
            ->download("SPK-{$billing->id}.pdf");
    }

    public function billingRecap(Request $request)
    {
        $billings = Billing::with('customer.cluster')
            ->when($request->query('year'), fn ($q, $value) => $q->where('year', $value))
            ->when($request->query('month'), fn ($q, $value) => $q->where('month', $value))
            ->get();

        return Pdf::loadHTML(view('pdf.billing-recap', compact('billings'))->render())
            ->download('billing-recap.pdf');
    }

    public function customerList()
    {
        $customers = Customer::with('cluster')->orderBy('cluster_id')->orderBy('block')->get();

        return Pdf::loadHTML(view('pdf.customer-list', compact('customers'))->render())
            ->download('customer-list.pdf');
    }

    public function clusterRecap()
    {
        $clusters = Cluster::withCount('customers')->orderBy('name')->get();

        return Pdf::loadHTML(view('pdf.cluster-recap', compact('clusters'))->render())
            ->download('cluster-recap.pdf');
    }
}
