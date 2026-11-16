
<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use DOMElement;
use Dompdf\Frame;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContinuousFormReceiptPrinter
{
    /**
     * Ukuran kertas dalam milimeter.
     *
     * 9.5 x 5.5 inch = 241.3 x 139.7 mm
     * 9.5 x 11 inch  = 241.3 x 279.4 mm
     */
    public const PAPERS = [
        '9.5x5.5' => [
            'width' => 241.3,
            'height' => 139.7,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 10,
            'margin_right' => 10,
            'font_size' => 10,
        ],
        '9.5x11' => [
            'width' => 241.3,
            'height' => 279.4,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'margin_right' => 10,
            'font_size' => 10,
        ],
        'custom' => [
            'width' => 241.3,
            'height' => 139.7,
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 10,
            'margin_right' => 10,
            'font_size' => 10,
        ],
    ];

    private const PT_PER_MM = 72 / 25.4;

    private const PROBE_HEIGHT_PT = 20000;

    /**
     * Validasi dan pengambilan pengaturan cetak.
     */
    public function settings(Request $request): array
    {
        $input = $request->validate([
            'paper' => [
                'nullable',
                'string',
                'in:9.5x5.5,9.5x11,custom',
            ],
            'width' => [
                'nullable',
                'numeric',
                'min:100',
                'max:400',
            ],
            'height' => [
                'nullable',
                'numeric',
                'min:80',
                'max:600',
            ],
            'margin_top' => [
                'nullable',
                'numeric',
                'min:0',
                'max:50',
            ],
            'margin_bottom' => [
                'nullable',
                'numeric',
                'min:0',
                'max:50',
            ],
            'margin_left' => [
                'nullable',
                'numeric',
                'min:0',
                'max:50',
            ],
            'margin_right' => [
                'nullable',
                'numeric',
                'min:0',
                'max:50',
            ],
            'font_size' => [
                'nullable',
                'numeric',
                'min:6',
                'max:20',
            ],
            'copies' => [
                'nullable',
                'integer',
                'in:1,2,3',
            ],
        ], [], [
            'paper' => 'ukuran kertas',
            'width' => 'lebar kertas',
            'height' => 'tinggi kertas',
            'margin_top' => 'margin atas',
            'margin_bottom' => 'margin bawah',
            'margin_left' => 'margin kiri',
            'margin_right' => 'margin kanan',
            'font_size' => 'ukuran font',
            'copies' => 'jumlah rangkap',
        ]);

        $paper = $input['paper'] ?? '9.5x5.5';

        $defaults = self::PAPERS[$paper];

        $settings = [
            'paper' => $paper,
            'width' => (float) ($input['width'] ?? $defaults['width']),
            'height' => (float) ($input['height'] ?? $defaults['height']),
            'margin_top' => (float) ($input['margin_top'] ?? $defaults['margin_top']),
            'margin_bottom' => (float) ($input['margin_bottom'] ?? $defaults['margin_bottom']),
            'margin_left' => (float) ($input['margin_left'] ?? $defaults['margin_left']),
            'margin_right' => (float) ($input['margin_right'] ?? $defaults['margin_right']),
            'font_size' => (float) ($input['font_size'] ?? $defaults['font_size']),
            'copies' => (int) ($input['copies'] ?? 1),
        ];

        if ($settings['margin_left'] + $settings['margin_right'] >= $settings['width']) {
            throw ValidationException::withMessages([
                'margin_left' => 'Total margin horizontal tidak boleh melebihi lebar kertas.',
            ]);
        }

        if ($settings['margin_top'] + $settings['margin_bottom'] >= $settings['height']) {
            throw ValidationException::withMessages([
                'margin_top' => 'Total margin vertikal tidak boleh melebihi tinggi kertas.',
            ]);
        }

        if ($settings['paper'] !== 'custom') {
            if (
                $settings['width'] != $defaults['width'] ||
                $settings['height'] != $defaults['height']
            ) {
                throw ValidationException::withMessages([
                    'paper' => 'Ukuran lebar dan tinggi hanya dapat diubah pada mode custom.',
                ]);
            }
        }

        return $settings;
    }

    /**
     * Preview HTML.
     */
    public function html(array $data, array $settings): string
    {
        return $this->render($data, $settings, false);
    }

    /**
     * Generate PDF.
     */
    public function pdf(array $data, array $settings): PdfDocument
    {
        $html = $this->render($data, $settings, true);

        $width = $settings['width'] * self::PT_PER_MM;
        $height = $settings['height'] * self::PT_PER_MM;

        return Pdf::loadHTML($html)
            ->setPaper([0, 0, $width, $height]);
    }

    /**
     * Render template.
     */
    private function render(
        array $data,
        array $settings,
        bool $forPdf
    ): string {
        return view('pdf.kwitansi-form', [
            'data' => $data,
            'settings' => $settings,
            'forPdf' => $forPdf,
        ])->render();
    }

    /**
     * Mengukur tinggi konten untuk mengetahui apakah kwitansi
     * melebihi kapasitas formulir.
     */
    public function contentHeight(array $data, array $settings): float
    {
        $html = $this->render($data, $settings, true);

        $width = $settings['width'] * self::PT_PER_MM;

        $bottom = 0.0;

        $probe = Pdf::loadHTML($html)
            ->setPaper([
                0,
                0,
                $width,
                self::PROBE_HEIGHT_PT,
            ]);

        $probe->setCallbacks([[
            'event' => 'end_frame',
            'f' => function (Frame $frame) use (&$bottom) {
                $node = $frame->get_node();

                if (
                    $node instanceof DOMElement &&
                    $node->getAttribute('id') === 'receipt'
                ) {
                    $bottom = max(
                        $bottom,
                        (float) $frame->get_position('y')
                        + $frame->get_margin_height()
                    );
                }
            },
        ]]);

        $probe->render();

        return $bottom > 0
            ? ceil($bottom) + 2
            : $settings['height'] * self::PT_PER_MM;
    }
}
