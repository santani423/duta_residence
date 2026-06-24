<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function prepareMonthly(int $year, int $month, int $userId): Collection
    {
        return DB::transaction(function () use ($year, $month, $userId) {
            return Customer::query()
                ->with('cluster')
                ->where('status_id', 'AK')
                ->get()
                ->map(function (Customer $customer) use ($year, $month, $userId) {
                    return Billing::query()->firstOrCreate(
                        [
                            'customer_id' => $customer->id,
                            'year' => $year,
                            'month' => $month,
                        ],
                        [
                            'amount' => $customer->cluster->monthly_rate,
                            'status_id' => '01',
                            'is_penalty_eligible' => $customer->is_penalty_eligible,
                            'is_discount_eligible' => $customer->is_discount_eligible,
                            'billing_type' => 'regular',
                            'created_by' => $userId,
                        ]
                    );
                });
        });
    }

    public function prepareSpecial(Customer $customer, int $year, int $month, float $amount, int $userId): Billing
    {
        return Billing::query()->create([
            'customer_id' => $customer->id,
            'year' => $year,
            'month' => $month,
            'amount' => $amount,
            'status_id' => '01',
            'billing_type' => 'special',
            'is_penalty_eligible' => $customer->is_penalty_eligible,
            'is_discount_eligible' => $customer->is_discount_eligible,
            'created_by' => $userId,
        ]);
    }

    public function approve(Billing $billing, int $userId, ?string $notes = null): Billing
    {
        $billing->forceFill([
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ])->save();

        return $billing->refresh();
    }
}
