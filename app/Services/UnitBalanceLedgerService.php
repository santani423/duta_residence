<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\UnitDeposit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Single source of truth for unit_deposits/units.balance writes - every credit, debit,
 * balance-usage, adjustment and reversal for a unit's balance must go through here so the
 * cached units.balance column and the unit_deposits ledger never drift apart.
 */
class UnitBalanceLedgerService
{
    public function currentBalance(Unit $unit): float
    {
        return (float) (Unit::query()->whereKey($unit->id)->value('balance') ?? 0);
    }

    public function credit(Unit $unit, string $type, float $amount, array $meta = []): UnitDeposit
    {
        return $this->post($unit, $type, UnitDeposit::DIRECTION_CREDIT, $amount, $meta);
    }

    public function debit(Unit $unit, string $type, float $amount, array $meta = [], bool $allowNegative = false): UnitDeposit
    {
        return $this->post($unit, $type, UnitDeposit::DIRECTION_DEBIT, $amount, $meta, $allowNegative);
    }

    public function reconcile(Unit $unit): array
    {
        $stored = (float) (Unit::query()->whereKey($unit->id)->value('balance') ?? 0);
        $credits = (float) UnitDeposit::query()->where('unit_id', $unit->id)->where('direction', UnitDeposit::DIRECTION_CREDIT)->sum('amount');
        $debits = (float) UnitDeposit::query()->where('unit_id', $unit->id)->where('direction', UnitDeposit::DIRECTION_DEBIT)->sum('amount');
        $calculated = round($credits - $debits, 2);
        $difference = round($stored - $calculated, 2);

        return [
            'unit_id' => $unit->id,
            'stored_balance' => $stored,
            'calculated_balance' => $calculated,
            'total_credits' => round($credits, 2),
            'total_debits' => round($debits, 2),
            'difference' => $difference,
            'status' => abs($difference) < 0.01 ? 'balanced' : 'mismatch',
        ];
    }

    /**
     * Locks the unit row before reading/writing its balance - this is what makes concurrent
     * payments against the same unit safe (the previous implementation read the latest
     * unit_deposits row unlocked, a race condition under concurrent writes).
     */
    private function post(Unit $unit, string $type, string $direction, float $amount, array $meta, bool $allowNegative = false): UnitDeposit
    {
        $amount = round($amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => ['Nominal harus lebih besar dari nol.']]);
        }

        return DB::transaction(function () use ($unit, $type, $direction, $amount, $meta, $allowNegative) {
            $locked = Unit::query()->whereKey($unit->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = (float) $locked->balance;
            $balanceAfter = $direction === UnitDeposit::DIRECTION_CREDIT
                ? round($balanceBefore + $amount, 2)
                : round($balanceBefore - $amount, 2);

            if ($balanceAfter < 0 && ! $allowNegative) {
                throw ValidationException::withMessages(['amount' => ['Saldo unit tidak mencukupi untuk transaksi ini.']]);
            }

            $entry = UnitDeposit::query()->create([
                'unit_id' => $unit->id,
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'payment_transaction_id' => $meta['payment_transaction_id'] ?? null,
                'receipt_number' => $meta['receipt_number'] ?? null,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'reversal_of_id' => $meta['reversal_of_id'] ?? null,
                'notes' => $meta['notes'] ?? null,
                'created_by' => $meta['created_by'] ?? null,
            ]);

            $locked->forceFill(['balance' => $balanceAfter])->save();
            $unit->setAttribute('balance', $balanceAfter);

            return $entry;
        });
    }
}
