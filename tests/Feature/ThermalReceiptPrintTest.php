<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\User;
use App\Services\PaymentPrintService;
use App\Services\ThermalReceiptPrinter;
use App\Support\Terbilang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ThermalReceiptPrintTest extends TestCase
{
    use RefreshDatabase;

    private function payAtLoket(): Receipt
    {
        $this->seed();
        $this->withToken($this->apiTokenFor(User::where('username', 'loket')->firstOrFail()));

        $number = $this->postJson('/api/v1/payments/process', [
            'unit_id' => '07012',
            'payment_method_id' => 'C',
            'loket_code' => 'L01',
            'cashier_name' => 'Loket Kasir',
        ])->assertCreated()->json('data.number');

        return Receipt::findOrFail($number);
    }

    /** @return array{0: float, 1: float, 2: int} lebar & tinggi halaman pertama (pt) dan jumlah halaman */
    private function pdfPages(string $pdf): array
    {
        preg_match_all('/\/MediaBox\s*\[\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)\s*\]/', $pdf, $boxes);

        return [(float) $boxes[1][0], (float) $boxes[2][0], preg_match_all('/\/Type\s*\/Page(?!s)/', $pdf)];
    }

    public function test_thermal_html_uses_the_same_receipt_data_with_its_own_print_layout(): void
    {
        $receipt = $this->payAtLoket();
        $data = app(PaymentPrintService::class)->forReceipt($receipt);

        $response = $this->get("/api/v1/documents/spt/{$receipt->number}?format=thermal&output=html&paper=58")->assertOk();
        $html = $response->getContent();

        $this->assertStringStartsWith('text/html', $response->headers->get('Content-Type'));
        foreach ([$receipt->number, $receipt->resident_name, $receipt->unit_id, $data['address'], 'Rp '.number_format((float) $receipt->grand_total, 0, ',', '.'), Terbilang::rupiah($receipt->grand_total), 'RINCIAN PEMBAYARAN'] as $expected) {
            $this->assertStringContainsString(e($expected), $html);
        }
        foreach ($data['rows'] as $row) {
            $this->assertStringContainsString($row['period'], $html);
        }

        // Bawaan kertas 58mm: area cetak 48mm di tengah (offset 5mm), margin 1mm -> lebar teks 46mm, font 8pt.
        $this->assertStringContainsString('#receipt { width: 46mm; margin: 0 0 0 5mm; padding: 1mm; }', $html);
        $this->assertStringContainsString('font-size: 8pt', $html);
        $this->assertStringContainsString('@media print', $html);
        $this->assertStringContainsString("'@page { size: 58mm ' + heightMm + 'mm; margin: 0; }'", $html);
        // Tanpa elemen dekoratif A4 (bingkai, logo, latar abu-abu tabel).
        $this->assertStringNotContainsString('class="frame"', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringNotContainsString('#e5e5e5', $html);

        // Pengaturan dari pengguna diteruskan ke layout.
        $custom = $this->get("/api/v1/documents/spt/{$receipt->number}?format=thermal&output=html&paper=80&width=70&margin=3&font_size=10.5")
            ->assertOk()->getContent();
        $this->assertStringContainsString('#receipt { width: 64mm; margin: 0 0 0 5mm; padding: 3mm; }', $custom);
        $this->assertStringContainsString('font-size: 10.5pt', $custom);

        // Kuitansi dari transaksi memakai template yang sama.
        $transaction = PaymentTransaction::where('unit_id', '07012')->where('payment_provider', 'loket')->latest('id')->firstOrFail();
        $fromTransaction = $this->get("/api/v1/documents/payment-transactions/{$transaction->id}/receipt?format=thermal&output=html")
            ->assertOk()->getContent();
        $this->assertStringContainsString($receipt->number, $fromTransaction);
        $this->assertStringContainsString('#receipt { width: 68mm; margin: 0 0 0 4mm; padding: 2mm; }', $fromTransaction);
    }

    public function test_thermal_settings_are_validated(): void
    {
        $receipt = $this->payAtLoket();
        $url = "/api/v1/documents/spt/{$receipt->number}?format=thermal&output=html";

        $this->getJson("{$url}&paper=100")->assertStatus(422)->assertJsonValidationErrors('paper');
        $this->getJson("{$url}&font_size=20")->assertStatus(422)->assertJsonValidationErrors('font_size');
        $this->getJson("{$url}&paper=58&width=70")->assertStatus(422)->assertJsonValidationErrors('width');
        // 40mm - 2 x 6mm = 28mm, di bawah lebar teks minimum.
        $this->getJson("{$url}&width=40&margin=6")->assertStatus(422)->assertJsonValidationErrors('margin');
    }

    public function test_thermal_pdf_page_length_follows_the_receipt_content(): void
    {
        $receipt = $this->payAtLoket();
        $data = app(PaymentPrintService::class)->forReceipt($receipt);
        $printer = app(ThermalReceiptPrinter::class);
        $settings = $printer->settings(Request::create('/', 'GET', ['paper' => 58]));

        [$width, $shortHeight, $shortPages] = $this->pdfPages($printer->pdf($data, $settings)->output());

        $long = $data;
        $long['rows'] = collect(array_fill(0, 40, $data['rows']->first()));
        [, $longHeight, $longPages] = $this->pdfPages($printer->pdf($long, $settings)->output());

        $this->assertEqualsWithDelta(58 * 72 / 25.4, $width, 0.01);
        $this->assertSame(1, $shortPages);
        $this->assertSame(1, $longPages, 'Struk panjang tetap satu halaman, tidak terpotong.');
        $this->assertGreaterThan($shortHeight + 39 * 20, $longHeight);

        // Endpoint PDF (format=thermal tanpa output=html) tetap tersedia; format A4 tetap memakai template A4.
        $thermalPdf = $this->get("/api/v1/documents/spt/{$receipt->number}?format=thermal&paper=58")->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $thermalPdf);
        $this->assertEqualsWithDelta(58 * 72 / 25.4, $this->pdfPages($thermalPdf)[0], 0.01);

        $a4Pdf = $this->get("/api/v1/documents/spt/{$receipt->number}")->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $a4Pdf);
        $this->assertEqualsWithDelta(595.28, $this->pdfPages($a4Pdf)[0], 0.01);
    }
}
