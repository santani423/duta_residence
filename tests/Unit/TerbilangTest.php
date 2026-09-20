<?php

namespace Tests\Unit;

use App\Support\Terbilang;
use PHPUnit\Framework\TestCase;

class TerbilangTest extends TestCase
{
    public function test_it_spells_rupiah_amounts_in_indonesian(): void
    {
        $this->assertSame('Nol rupiah', Terbilang::rupiah(0));
        $this->assertSame('Sebelas ribu rupiah', Terbilang::rupiah(11000));
        $this->assertSame('Seratus ribu rupiah', Terbilang::rupiah(100000));
        $this->assertSame('Satu juta dua ratus tiga puluh empat ribu lima ratus enam puluh tujuh rupiah', Terbilang::rupiah(1234567));
    }
}
