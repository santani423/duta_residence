<?php

namespace Tests\Unit;

use App\Services\CollectionAgingService;
use Carbon\Carbon;
use Tests\TestCase;

class CollectionAgingServiceTest extends TestCase
{
    public function test_bucket_boundaries(): void
    {
        $service = new CollectionAgingService;

        $this->assertSame('current', $service->bucketFor(0));
        $this->assertSame('1_30', $service->bucketFor(1));
        $this->assertSame('1_30', $service->bucketFor(30));
        $this->assertSame('31_60', $service->bucketFor(31));
        $this->assertSame('31_60', $service->bucketFor(60));
        $this->assertSame('61_90', $service->bucketFor(61));
        $this->assertSame('61_90', $service->bucketFor(90));
        $this->assertSame('91_180', $service->bucketFor(91));
        $this->assertSame('91_180', $service->bucketFor(180));
        $this->assertSame('180_plus', $service->bucketFor(181));
    }

    public function test_aging_days_counts_whole_days_past_due_and_ignores_time_of_day(): void
    {
        $service = new CollectionAgingService;
        $today = Carbon::create(2026, 10, 15, 23, 59);

        $this->assertSame(0, $service->agingDays(null, $today));
        $this->assertSame(0, $service->agingDays(Carbon::create(2026, 10, 15), $today), 'jatuh tempo hari ini belum aging');
        $this->assertSame(0, $service->agingDays(Carbon::create(2026, 10, 20), $today), 'belum jatuh tempo');
        $this->assertSame(1, $service->agingDays(Carbon::create(2026, 10, 14, 18), $today));
        $this->assertSame(56, $service->agingDays(Carbon::create(2026, 8, 20), $today));
    }
}
