<?php

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Database\Seeders\IplData;
use PHPUnit\Framework\TestCase;

class IplDataTest extends TestCase
{
    public function test_it_reads_the_period_formats_written_in_the_manual_ipl_book(): void
    {
        $paidAt = CarbonImmutable::create(2024, 7, 29);

        $this->assertSame(['2024-01'], IplData::parsePeriods('2024-01-01T00:00:00'));
        $this->assertSame(['2026-10'], IplData::parsePeriods('Okt 26'));
        $this->assertSame(['2024-03'], IplData::parsePeriods('Mart=24'));
        $this->assertSame(['2023-11', '2023-12', '2024-01'], IplData::parsePeriods('Nov-23 -Jan 24'));
        $this->assertSame(['2023-11', '2023-12', '2024-01'], IplData::parsePeriods('Nov-Des 23-Jan 24'));
        $this->assertSame(['2023-11', '2023-12', '2024-01'], IplData::parsePeriods('Nov-Jan 24'));
        $this->assertSame(['2023-08', '2023-09', '2023-10', '2023-11', '2024-02', '2024-03'], IplData::parsePeriods('Ags-Nov 23 dan Feb-Mart 24'));
        $this->assertCount(12, IplData::parsePeriods('Jan-Des 24'));
        $this->assertSame(['2024-11'], IplData::parsePeriods('Nov-4', $paidAt));
        $this->assertSame(['2024-02', '2024-03'], IplData::parsePeriods('Feb-Mart', $paidAt));
    }

    public function test_it_rejects_periods_it_cannot_read(): void
    {
        $this->assertNull(IplData::parsePeriods(''));
        $this->assertNull(IplData::parsePeriods('Feb-Mart'));
        $this->assertNull(IplData::parsePeriods("September' 2010 - Desember'20011"));
        $this->assertNull(IplData::parsePeriods('Oktober 2013-Desember 2013 s/d January -Juni 2014 ( Tidak Huni )'));
    }
}
