<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use DOMElement;
use Dompdf\Frame;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Struk kuitansi untuk printer thermal (kertas roll 58 mm / 80 mm).
 *
 * Data struk sama persis dengan kuitansi A4 (array dari PaymentPrintService); kelas ini hanya mengatur
 * tata letak cetaknya lewat template tersendiri `pdf.kwitansi-thermal`, dengan dua keluaran:
 * - HTML untuk preview dan cetak langsung dari browser. Ukuran kertas diatur CSS `@page`; panjangnya
 *   diukur di browser dari tinggi isi struk.
 * - PDF yang panjang halamannya diukur dari render percobaan, jadi tidak pernah terpotong ke halaman kedua.
 */
class ThermalReceiptPrinter
{
    /**
     * Pengaturan bawaan per lebar kertas. Kepala printer 58 mm umumnya mencetak selebar 48 mm, 80 mm selebar 72 mm.
     * Disalin juga di frontend (utils/thermalPrint.js) - ubah keduanya bersamaan.
     */
    public const PAPERS = [
        58 => ['width' => 48, 'margin' => 1, 'font_size' => 8],
        80 => ['width' => 72, 'margin' => 2, 'font_size' => 9],
    ];

    /** Lebar teks minimum (lebar cetak dikurangi margin kiri-kanan) agar label dan nominal tetap terbaca. */
    public const MIN_TEXT_WIDTH = 30;

    private const PT_PER_MM = 72 / 25.4;

    /** Tinggi halaman render percobaan: batas ukuran halaman PDF, jauh di atas struk terpanjang. */
    private const PROBE_HEIGHT_PT = 14400;

    /**
     * @return array{paper: int, width: float, margin: float, font_size: float}
     */
    public function settings(Request $request): array
    {
        $input = $request->validate([
            'paper' => ['nullable', 'integer', 'in:58,80'],
            'width' => ['nullable', 'numeric', 'min:'.self::MIN_TEXT_WIDTH, 'max:80'],
            'margin' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'font_size' => ['nullable', 'numeric', 'min:6', 'max:14'],
        ], [], [
            'paper' => 'ukuran kertas',
            'width' => 'lebar cetak',
            'margin' => 'margin',
            'font_size' => 'ukuran font',
        ]);

        $paper = (int) ($input['paper'] ?? 80);
        $settings = [
            'paper' => $paper,
            'width' => (float) ($input['width'] ?? self::PAPERS[$paper]['width']),
            'margin' => (float) ($input['margin'] ?? self::PAPERS[$paper]['margin']),
            'font_size' => (float) ($input['font_size'] ?? self::PAPERS[$paper]['font_size']),
        ];

        if ($settings['width'] > $paper) {
            throw ValidationException::withMessages(['width' => "Lebar cetak maksimal {$paper} mm untuk kertas {$paper} mm."]);
        }

        if ($settings['width'] - 2 * $settings['margin'] < self::MIN_TEXT_WIDTH) {
            throw ValidationException::withMessages(['margin' => 'Margin terlalu besar untuk lebar cetak ini (sisa lebar teks minimal '.self::MIN_TEXT_WIDTH.' mm).']);
        }

        return $settings;
    }

    public function html(array $data, array $settings): string
    {
        return $this->render($data, $settings, false);
    }

    public function pdf(array $data, array $settings): PdfDocument
    {
        $html = $this->render($data, $settings, true);
        $width = $settings['paper'] * self::PT_PER_MM;

        return Pdf::loadHTML($html)->setPaper([0, 0, $width, $this->contentHeight($html, $width)]);
    }

    private function render(array $data, array $settings, bool $forPdf): string
    {
        return view('pdf.kwitansi-thermal', ['data' => $data, 'settings' => $settings, 'forPdf' => $forPdf])->render();
    }

    /**
     * DomPDF tidak mengenal tinggi halaman "auto": render dulu di halaman sangat panjang, ambil tepi bawah
     * blok struk (#receipt), lalu PDF akhirnya memakai tinggi itu.
     */
    private function contentHeight(string $html, float $width): float
    {
        $bottom = 0.0;
        $probe = Pdf::loadHTML($html)->setPaper([0, 0, $width, self::PROBE_HEIGHT_PT]);
        $probe->setCallbacks([[
            'event' => 'end_frame',
            'f' => function (Frame $frame) use (&$bottom) {
                $node = $frame->get_node();
                if ($node instanceof DOMElement && $node->getAttribute('id') === 'receipt') {
                    $bottom = max($bottom, (float) $frame->get_position('y') + $frame->get_margin_height());
                }
            },
        ]]);
        $probe->render();

        // Cadangan 2pt untuk pembulatan; tanpa hasil ukur (tidak semestinya terjadi) pakai tinggi A4.
        return $bottom > 0 ? ceil($bottom) + 2 : 842;
    }
}
