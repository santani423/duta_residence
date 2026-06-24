<?php

namespace Tests\Unit;

use App\Models\Billing;
use App\Services\PenaltyService;
use Carbon\Carbon;
use Tests\TestCase;

class PenaltyServiceTest extends TestCase
{
    public function test_penalty_is_three_percent_after_penalty_day(): void
    {
        $billing = new Billing([
            'amount' => 100000,
            'is_penalty_eligible' => true,
        ]);

        $service = new PenaltyService();

        $this->assertSame(3000.0, $service->calculate($billing, Carbon::create(2026, 6, 21)));
        $this->assertSame(0.0, $service->calculate($billing, Carbon::create(2026, 6, 20)));
    }
}
