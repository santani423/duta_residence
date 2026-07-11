<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly PenaltyService $penaltyService,
        private readonly ReceiptService $receiptService,
        private readonly AuditService $auditService,
    ) {}

    public function preview(Unit $unit, array $billingIds): array
    {
        $billings = $this->validatedPayableBillings($unit, $billingIds);
        $items = $billings->map(function (Billing $billing) {
            $penalty = $this->penaltyService->calculate($billing);

            return [
                'id' => $billing->id,
                'period' => sprintf('%04d-%02d', $billing->year, $billing->month),
                'amount' => (float) $billing->amount,
                'penalty' => $penalty,
                'total' => (float) $billing->amount + $penalty,
            ];
        });

        return [
            'items' => $items->values(),
            'total_billing' => $items->sum('amount'),
            'total_penalty' => $items->sum('penalty'),
            'grand_total' => $items->sum('total'),
        ];
    }

    public function process(Unit $unit, array $billingIds, array $data, int $userId): Receipt
    {
        return DB::transaction(function () use ($unit, $billingIds, $data, $userId) {
            $billings = $this->validatedPayableBillings($unit, $billingIds, true);
            $preview = $this->preview($unit, $billingIds);
            $receiptNumber = $this->receiptService->generateReceiptNumber();

            $receipt = Receipt::query()->create([
                'number' => $receiptNumber,
                'unit_id' => $unit->id,
                'transaction_date' => now(),
                'resident_name' => $unit->resident->name,
                'cluster_name' => $unit->cluster->name,
                'block' => $unit->block,
                'lot_number' => $unit->lot_number,
                'total_billing' => $preview['total_billing'],
                'total_penalty' => $preview['total_penalty'],
                'grand_total' => $preview['grand_total'],
                'billing_count' => $billings->count(),
                'billing_periods' => $billings->map(fn (Billing $billing) => sprintf('%04d-%02d', $billing->year, $billing->month))->implode(', '),
                'loket_code' => $data['loket_code'] ?? null,
                'cashier_name' => $data['cashier_name'] ?? null,
                'payment_method_id' => $data['payment_method_id'] ?? 'C',
                'payment_channel_id' => $data['payment_channel_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($billings as $billing) {
                $billing->forceFill([
                    'status_id' => '02',
                    'penalty' => $this->penaltyService->calculate($billing),
                    'paid_at' => now(),
                    'receipt_number' => $receiptNumber,
                    'loket_code' => $data['loket_code'] ?? null,
                    'processed_by' => $userId,
                ])->save();
            }

            PaymentTransaction::query()->create([
                'transaction_number' => 'TRX-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'invoice_number' => 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(6)),
                'unit_id' => $unit->id,
                'subtotal' => $preview['total_billing'],
                'tax' => 0,
                'admin_fee' => 0,
                'total' => $preview['grand_total'],
                'payment_provider' => 'loket',
                'payment_method' => $data['payment_method_id'] ?? 'C',
                'status' => 'paid',
                'paid_at' => now(),
                'created_by' => $userId,
            ])->billings()->sync($billings->pluck('id'));

            $this->auditService->log('payment_processed', 'payments', 'PROCESS', $receipt, [], $receipt->toArray());

            return $receipt->load('billings');
        });
    }

    private function validatedPayableBillings(Unit $unit, array $billingIds, bool $lock = false): Collection
    {
        $query = Billing::query()
            ->with('unit.cluster')
            ->whereIn('id', $billingIds)
            ->where('unit_id', $unit->id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $billings = $query->get();

        if ($billings->count() !== count(array_unique($billingIds))) {
            throw ValidationException::withMessages(['billing_ids' => ['Tagihan tidak ditemukan atau bukan milik unit ini.']]);
        }

        if ($billings->contains(fn (Billing $billing) => $billing->status_id !== '01')) {
            throw ValidationException::withMessages(['billing_ids' => ['Semua tagihan harus berstatus belum bayar.']]);
        }

        if ($billings->contains(fn (Billing $billing) => blank($billing->approved_at))) {
            throw ValidationException::withMessages(['billing_ids' => ['Semua tagihan harus sudah disetujui.']]);
        }

        return $billings;
    }
}
