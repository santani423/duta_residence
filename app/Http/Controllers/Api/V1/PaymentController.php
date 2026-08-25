<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Billing;
use App\Models\Receipt;
use App\Models\Unit;
use App\Services\CollectorAssignmentService;
use App\Services\PaymentService;
use App\Services\PenaltyService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function search(Request $request, PenaltyService $penaltyService, CollectorAssignmentService $assignmentService)
    {
        $data = $request->validate(['unit_id' => ['required', 'exists:units,id']]);

        if ($request->user()->hasRole('collector')) {
            $assignmentService->assertUnitAssigned($request->user(), $data['unit_id']);
        }

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $unit = Unit::query()
            ->with(['cluster', 'resident', 'billings' => function ($q) use ($dateFrom, $dateTo) {
                $q->outstanding()->approved()
                    ->when($dateFrom, function ($q, $value) {
                        [$year, $month] = array_map('intval', explode('-', $value));
                        $q->whereRaw('(year * 100 + month) >= ?', [$year * 100 + $month]);
                    })
                    ->when($dateTo, function ($q, $value) {
                        [$year, $month] = array_map('intval', explode('-', $value));
                        $q->whereRaw('(year * 100 + month) <= ?', [$year * 100 + $month]);
                    })
                    ->orderBy('year')->orderBy('month');
            }])
            ->findOrFail($data['unit_id']);

        $now = now();
        $billings = $unit->billings->map(function (Billing $billing) use ($unit, $penaltyService) {
            $billing->setRelation('unit', $unit);

            return [
                ...$billing->toArray(),
                'penalty_detail' => $penaltyService->calculateInvoiceTotal($billing),
            ];
        });
        $unit->setRelation('billings', $billings);

        $totalOutstanding = round($billings->sum(fn ($row) => $row['penalty_detail']['total_outstanding']), 2);
        $totalUpcoming = round($billings->filter(fn ($row) => ($row['year'] * 100 + $row['month']) > ($now->year * 100 + $now->month))
            ->sum(fn ($row) => $row['penalty_detail']['total_outstanding']), 2);

        return $this->success([
            ...$unit->toArray(),
            'total_outstanding' => $totalOutstanding,
            'total_upcoming' => $totalUpcoming,
        ]);
    }

    public function preview(Request $request, PaymentService $service, CollectorAssignmentService $assignmentService)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'use_balance' => ['nullable', 'boolean'],
            'billing_ids' => ['nullable', 'array', 'min:1'],
            'billing_ids.*' => ['integer', 'exists:billings,id'],
        ]);

        if ($request->user()->hasRole('collector')) {
            $assignmentService->assertUnitAssigned($request->user(), $data['unit_id']);
        }

        $preview = $service->preview(
            Unit::findOrFail($data['unit_id']),
            (float) ($data['amount'] ?? 0),
            (bool) ($data['use_balance'] ?? true),
            $data['billing_ids'] ?? null,
        );

        return $this->success($preview);
    }

    public function process(Request $request, PaymentService $service, CollectorAssignmentService $assignmentService)
    {
        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'billing_ids' => ['nullable', 'array', 'min:1'],
            'billing_ids.*' => ['integer', 'exists:billings,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'use_balance' => ['nullable', 'boolean'],
            'payment_method_id' => ['required', 'exists:payment_methods,id'],
            'payment_channel_id' => ['nullable', 'exists:payment_channels,id'],
            'loket_code' => ['nullable', 'string', 'max:20'],
            'cashier_name' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($request->user()->hasRole('collector')) {
            $assignmentService->assertUnitAssigned($request->user(), $data['unit_id']);
        }

        $receipt = $service->process(Unit::findOrFail($data['unit_id']), $data['billing_ids'] ?? null, $data, $request->user()->id);

        return $this->success($receipt, 'Pembayaran berhasil diproses.', 201);
    }

    public function receipts(Request $request)
    {
        $query = Receipt::query()
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
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value));

        return $this->paginated($query->latest('transaction_date')->paginate($request->integer('per_page', 15)));
    }

    public function showReceipt(Receipt $receipt)
    {
        return $this->success($receipt->load(['unit.cluster', 'billings']));
    }
}
