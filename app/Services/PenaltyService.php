<?php

namespace App\Services;

use App\Models\Billing;
use Carbon\CarbonInterface;

class PenaltyService
{
    public function calculate(Billing $billing, ?CarbonInterface $paymentDate = null): float
    {
        $date = $paymentDate ?? now();

        if (! $billing->is_penalty_eligible || $date->day <= (int) config('grandduta.notification.penalty_day', 20)) {
            return 0.0;
        }

        return round((float) $billing->amount * 0.03, 2);
    }
}
