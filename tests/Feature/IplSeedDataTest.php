<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\ClusterRateSchedule;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use App\Models\Unit;
use Database\Seeders\IplData;
use Database\Seeders\PenghuniData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Data awal IPL dari seluruh buku IPL docs/final_revisi_1.md, 2010 sampai data terbaru. Unit
 * 09005 = blok C1/6 (Jhoni Candra): bayar setiap bulan Jan 2024 - Sep 2026, satu bulan per kuitansi.
 * Unit 07012 = AL2/15: bayar per rentang sampai Apr 2026 (mis. Rp1.800.000 untuk "Nov-25 - Aprl-26"
 * = 6 x Rp300.000).
 */
class IplSeedDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // phpunit.xml membatasi buku supaya test lain cepat; di sini yang diuji seluruh buku.
        config(['grandduta.ipl_seed_from_period' => null]);
    }

    private function unitId(string $block, string $lot): string
    {
        return Unit::where('block', $block)->whereIn('lot_number', [$lot, str_pad($lot, 2, '0', STR_PAD_LEFT)])->sole()->id;
    }

    public function test_billings_and_receipts_follow_the_manual_ipl_book(): void
    {
        $this->seed();

        $this->assertTheWholeBookIsImported();
        $this->assertRowsWithMissingValuesAreEstimatedAndNoted();
        $this->assertBookkeepingDuplicatesAreLeftOut();
        $this->assertRecentPaymentsAndArrearsAreIntact();
    }

    private function assertTheWholeBookIsImported(): void
    {
        // Setiap baris sumber masuk atau punya alasan: 37.492 + 583 = 38.075.
        $this->assertSame(37492, Receipt::where('notes', 'like', 'Impor buku IPL manual.%')->count());
        $this->assertEquals(14102067321, Receipt::sum('grand_total'));
        $this->assertEquals(14102067321, PaymentAllocation::sum('total_amount'));
        $this->assertEquals(14102067321, Billing::where('status_id', Billing::STATUS_PAID)->sum('amount'));

        $this->assertSame('2010-01-01', Receipt::min('transaction_date'));
        $this->assertSame('2026-10-07', Receipt::max('transaction_date'));

        // Baris pertama buku: Dewi, E4/19, Rp112.500 untuk "Januari - Maret 2010".
        $unitId = $this->unitId('E4', '19');
        $billing = Billing::where('unit_id', $unitId)->where('year', 2010)->where('month', 2)->firstOrFail();
        $this->assertSame(Billing::STATUS_PAID, $billing->status_id);
        $this->assertEquals(37500, $billing->amount);
        $receipt = Receipt::where('number', $billing->receipt_number)->firstOrFail();
        $this->assertSame('Dewi', $receipt->resident_name);
        $this->assertEquals(112500, $receipt->grand_total);
        $this->assertSame(3, (int) $receipt->billing_count);
    }

    private function assertRowsWithMissingValuesAreEstimatedAndNoted(): void
    {
        // Bagian awal buku tidak mencatat tanggal bayar: memakai awal periode, cara bayar default.
        $receipt = Receipt::where('resident_name', 'Dewi')->where('unit_id', $this->unitId('E4', '19'))->orderBy('transaction_date')->firstOrFail();
        $this->assertSame('2010-01-01', $receipt->transaction_date->toDateString());
        $this->assertStringContainsString('Tanggal bayar kosong/tidak terbaca, memakai awal periode.', $receipt->notes);
        $this->assertSame(['C', 'L'], [$receipt->payment_method_id, $receipt->payment_channel_id]);

        // "400" di kolom NOMINAL ditulis dalam ribuan: Lilis, AL2/7, "Juni 2015-Juli 2015".
        $receipt = Receipt::where('notes', 'like', '%NN.2015.07.04.%')->where('resident_name', 'Lilis')->firstOrFail();
        $this->assertEquals(400000, $receipt->grand_total);
        $this->assertStringContainsString('Nominal tertulis "400", dibaca dalam ribuan.', $receipt->notes);

        // "2026-01-12" di antara baris 2012 tahunnya tergeser saat konversi (periode "Januari'2012").
        $this->assertFalse(Receipt::where('notes', 'like', '%NN.2026.01.12.%')->whereYear('transaction_date', 2026)->exists());
        $this->assertSame('2012-01-01', Receipt::where('notes', 'like', '%NN.2026.01.12.%')->firstOrFail()->transaction_date->toDateString());

        // Periode tidak terbaca: uangnya tetap tercatat, ke bulan bayar.
        $this->assertTrue(Receipt::where('notes', 'like', '%Periode tertulis: Cicilan 1.%Periode tidak terbaca, dicatat ke bulan bayar.%')->exists());
    }

    private function assertBookkeepingDuplicatesAreLeftOut(): void
    {
        // Baris 7304: Jupilon G2/96, hanya NML DBL "100", catatan "Uang Sudah Masuk Bulan Juni";
        // Juni 2015 sudah dibayar lewat "Mei 2015-Juni 2015" Rp200.000.
        $billing = Billing::where('unit_id', $this->unitId('G2', '96'))->where('year', 2015)->where('month', 6)->firstOrFail();
        $this->assertEquals(100000, $billing->amount);
        $this->assertFalse(Receipt::where('notes', 'like', '%GD.2015.7.571.%')->exists());

        $skipped = implode("\n", IplData::skipped());
        $this->assertStringContainsString('nominal hanya di kolom NML DBL (kuitansi ganda): 249 baris', $skipped);
        $this->assertStringContainsString('kuitansi batal/salah: 230 baris', $skipped);
        $this->assertStringContainsString('baris ganda (semua kolom sama): 34 baris', $skipped);
        $this->assertStringContainsString('nominal kosong/nol: 43 baris', $skipped);
        $this->assertStringContainsString('blok/nomor unit tidak terdaftar: 27 baris', $skipped);
    }

    private function assertRecentPaymentsAndArrearsAreIntact(): void
    {
        // Riwayat lama tidak menambah tunggakan: buku tidak mencatat bulan yang tidak dibayar.
        $this->assertSame(2024, (int) Billing::where('status_id', Billing::STATUS_UNPAID)->min('year'));

        $billings = Billing::where('unit_id', '09005')->get()
            ->keyBy(fn (Billing $billing) => sprintf('%04d-%02d', $billing->year, $billing->month));

        // "Mart-25" yang dibayar 2025-04-08.
        $this->assertSame(Billing::STATUS_PAID, $billings['2025-03']->status_id);
        $this->assertEquals(225000, $billings['2025-03']->amount);
        $this->assertSame(Billing::STATUS_PAID, $billings['2026-09']->status_id);
        $this->assertEquals(250000, $billings['2026-09']->amount);
        $this->assertSame(Billing::STATUS_UNPAID, $billings['2026-10']->status_id);

        $receipt = Receipt::where('number', $billings['2026-09']->receipt_number)->firstOrFail();
        $this->assertSame('2026-09-25', $receipt->transaction_date->toDateString());
        $this->assertEquals(250000, $receipt->grand_total);
        $this->assertSame(1, (int) $receipt->billing_count);
        $this->assertSame(['D', 'M'], [$receipt->payment_method_id, $receipt->payment_channel_id]);
        $this->assertStringContainsString('2375 / KW.2026.09.0217', $receipt->notes);
        $this->assertEquals(250000, PaymentAllocation::where('payment_transaction_id', $receipt->payment_transaction_id)->sum('total_amount'));

        // Satu kuitansi untuk beberapa bulan: nominal dibagi rata ke setiap bulan.
        $billing = Billing::where('unit_id', '07012')->where('year', 2026)->where('month', 1)->firstOrFail();
        $this->assertSame(Billing::STATUS_PAID, $billing->status_id);
        $this->assertEquals(300000, $billing->amount);
        $receipt = Receipt::where('number', $billing->receipt_number)->firstOrFail();
        $this->assertSame('2026-04-22', $receipt->transaction_date->toDateString());
        $this->assertEquals(1800000, $receipt->grand_total);
        $this->assertSame(6, (int) $receipt->billing_count);
        $this->assertSame(6, PaymentAllocation::where('payment_transaction_id', $receipt->payment_transaction_id)->count());
        $this->assertEquals(1800000, PaymentAllocation::where('payment_transaction_id', $receipt->payment_transaction_id)->sum('total_amount'));
        $this->assertSame(Billing::STATUS_UNPAID, Billing::where('unit_id', '07012')->where('year', 2026)->where('month', 5)->value('status_id'));

        // Cluster tanpa pembayaran di buku IPL 2024+ tidak dibuatkan tunggakan.
        $this->assertFalse(Billing::whereHas('unit', fn ($q) => $q->where('cluster_id', 'D'))->where('status_id', Billing::STATUS_UNPAID)->exists());

        $this->assertEquals(250000, ClusterRateSchedule::where('cluster_id', 'C')->whereDate('effective_date', '2025-11-01')->value('rate'));
    }

    public function test_seeding_again_keeps_the_same_totals_and_links(): void
    {
        config(['grandduta.ipl_seed_from_period' => '2024-01']);
        $this->seed();
        $partial = Receipt::count();

        // Dari sebagian buku ke seluruh buku: nomor kuitansi per bulan bergeser, tautan lama harus ikut hilang.
        config(['grandduta.ipl_seed_from_period' => null]);
        $this->seed();

        $this->assertGreaterThan($partial, Receipt::count());
        $this->assertEquals(14102067321, Receipt::sum('grand_total'));
        $this->assertEquals(14102067321, PaymentAllocation::sum('total_amount'));
        $this->assertSame(PaymentAllocation::count(), \DB::table('billing_payment_transaction')->whereIn('payment_transaction_id', PaymentAllocation::query()->select('payment_transaction_id'))->count());
    }

    /** @param list<string> $expected */
    #[DataProvider('periodTexts')]
    public function test_billing_month_text_is_read_from_every_era_of_the_book(string $text, string $reference, array $expected): void
    {
        $this->assertSame($expected, IplData::periods($text, $reference));
    }

    public static function periodTexts(): array
    {
        return [
            'rentang 2010' => ['Januari - Maret 2010', '2010-01-01', ['2010-01', '2010-02', '2010-03']],
            'tahun mengikuti bulan sesudahnya' => ['Desember - Februari 2011', '2011-01-01', ['2010-12', '2011-01', '2011-02']],
            'tanggal hasil konversi, hari = tahun' => ['2026-03-10', '2010-02-01', ['2010-03']],
            'tanggal hasil konversi, hari bukan tahun' => ['2026-09-01', '2018-09-28', ['2018-09']],
            'catatan jumlah bulan' => ['Mei 2012-Desember 2012(8bln)', '2012-09-05', ['2012-05', '2012-06', '2012-07', '2012-08', '2012-09', '2012-10', '2012-11', '2012-12']],
            'bulan free tidak ditagih' => ['April 2015(Free) Mei 2015-Juli 2015', '2015-03-25', ['2015-05', '2015-06', '2015-07']],
            'rentang berlanjut setelah catatan' => ['Desember 2013 (Belum Huni )-January 2014 ( Huni )', '2014-01-20', ['2013-12', '2014-01']],
            'dua rentang dipisah catatan' => ["Nov11-des11(blm huni)jan12-feb12", '2012-04-30', ['2011-11', '2011-12', '2012-01', '2012-02']],
            'cicilan menyebut periode di kurung' => ["Cicilan 2 ( Des'15 - Mei'16 )", '2017-12-21', ['2015-12', '2016-01', '2016-02', '2016-03', '2016-04', '2016-05']],
            'periode lain di kurung' => ["Feb'20 ( Jan'19 - Mar'19)", '2020-04-28', ['2019-01', '2019-02', '2019-03', '2020-02']],
            's/d' => ['April2012 s/dJuni 2012 (3 Bulan )', '2012-07-28', ['2012-04', '2012-05', '2012-06']],
            'nominal di teks' => ['Agustus 2012, cicilan tunggakan 50.000', '2012-08-10', ['2012-08']],
            'salah ketik bulan' => ['Mai 2014-Juni 2014', '2014-05-09', ['2014-05', '2014-06']],
            'tanpa tahun' => ['Mart-Mei', '2023-06-16', ['2023-03', '2023-04', '2023-05']],
            'tahun satu digit' => ["Apr'2", '2021-04-14', ['2021-04']],
            'tahun salah pada bulan akhir' => ["Des'18 - Jan'18", '2019-01-03', ['2018-12', '2019-01']],
            'tahun salah pada bulan awal' => ['Desember 2014-January 2014', '2014-01-11', ['2013-12', '2014-01']],
            'dimuka 4 tahun' => ["Jan'20-Des'23", '2020-01-18', array_map(fn (int $i) => sprintf('%04d-%02d', 2020 + intdiv($i, 12), $i % 12 + 1), range(0, 47))],
            'bukan periode' => ['Cicilan Ke 5', '2022-08-08', []],
            'tidak terbaca' => ['Cek pembayaran', '2019-08-01', []],
            'kosong' => ['', '2019-08-01', []],
        ];
    }

    public function test_unusable_payment_dates_are_not_trusted(): void
    {
        $this->assertSame('', PenghuniData::date('14 Feb 0202'));
        $this->assertSame('', PenghuniData::date('1944-02-17'));
        $this->assertSame('', PenghuniData::date('07 April 20108'));
        $this->assertSame('2013-05-02', PenghuniData::date('2 Mai 2013'));
        $this->assertSame('2022-11-03', PenghuniData::date('03=Nov-22'));
        $this->assertSame('2023-02-02', PenghuniData::date('02--02-23'));

        $dates = PenghuniData::paidDates();
        // Baris 1212 "2026-01-12" ada di antara baris 2012; tanggal 2026 yang asli tetap dipakai.
        $this->assertSame('', $dates[1212]);
        $this->assertSame('2026-10-07', max($dates));
    }

    public function test_memory_limit_is_raised_but_never_lowered(): void
    {
        $original = ini_get('memory_limit');

        try {
            ini_set('memory_limit', '4G');
            IplData::ensureMemoryLimit();
            $this->assertSame('4G', ini_get('memory_limit'));

            ini_set('memory_limit', '-1');
            IplData::ensureMemoryLimit();
            $this->assertSame('-1', ini_get('memory_limit'));

            ini_set('memory_limit', '512M');
            IplData::ensureMemoryLimit();
            $this->assertSame('1G', ini_get('memory_limit'));
        } finally {
            ini_set('memory_limit', $original);
        }
    }
}
