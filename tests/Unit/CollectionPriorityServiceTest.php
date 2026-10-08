<?php

namespace Tests\Unit;

use App\Services\CollectionPriorityService;
use Tests\TestCase;

class CollectionPriorityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['collector.large_outstanding_threshold' => 5000000]);
    }

    public function test_no_outstanding_is_always_normal_priority(): void
    {
        $result = app(CollectionPriorityService::class)->evaluate([
            'aging_days' => 400, 'outstanding_total' => 0, 'broken_ptp_count' => 5,
        ]);

        $this->assertSame(['score' => 0, 'level' => 'normal'], $result);
    }

    public function test_score_combines_weighted_components(): void
    {
        $service = app(CollectionPriorityService::class);

        // 90/180*35 = 17.5 + 2.5jt/5jt*25 = 12.5 → 30 → medium
        $this->assertSame(['score' => 30, 'level' => 'medium'], $service->evaluate([
            'aging_days' => 90, 'outstanding_total' => 2500000,
        ]));

        // aging & outstanding penuh (60) + 1 broken PTP (6.67) → 67 → high
        $this->assertSame(['score' => 67, 'level' => 'high'], $service->evaluate([
            'aging_days' => 200, 'outstanding_total' => 9000000, 'broken_ptp_count' => 1,
        ]));

        // Semua komponen penuh → 100 → critical
        $this->assertSame(['score' => 100, 'level' => 'critical'], $service->evaluate([
            'aging_days' => 365, 'outstanding_total' => 9000000, 'broken_ptp_count' => 3,
            'failed_contact_count' => 9, 'failed_visit_count' => 4,
        ]));

        $this->assertSame('normal', $service->evaluate(['aging_days' => 10, 'outstanding_total' => 300000])['level']);
    }

    public function test_disputed_account_is_capped_at_medium(): void
    {
        $result = app(CollectionPriorityService::class)->evaluate([
            'aging_days' => 365, 'outstanding_total' => 9000000, 'broken_ptp_count' => 3, 'is_disputed' => true,
        ]);

        $this->assertSame(80, $result['score']);
        $this->assertSame('medium', $result['level']);
    }
}
