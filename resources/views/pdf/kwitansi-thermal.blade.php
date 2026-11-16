{{--
    Struk kuitansi printer thermal (kertas roll 58 / 80 mm) - template tersendiri, terpisah dari kuitansi A4.
    $data     : array dari PaymentPrintService (sumber yang sama dengan kuitansi A4).
    $settings : ThermalReceiptPrinter::settings() -> paper (lebar kertas), width (lebar area cetak), margin, font_size (pt).
    $forPdf   : true untuk DomPDF, false untuk dicetak langsung dari browser.
    Hanya memakai blok dan tabel biasa (tanpa flex/grid/var()) agar hasil DomPDF dan browser sama.
--}}
@php
    $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $mm = fn (float $value) => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'mm';
    $company = $data['company'];
    $cancelled = ($data['status'] ?? null) === 'Dibatalkan';
    $paper = $settings['paper'];
    $margin = $settings['margin'];
    // Area cetak berada di tengah kertas; margin = jarak isi dari tepi area cetak (kiri-kanan) dan ujung kertas (atas-bawah).
    $offset = ($paper - $settings['width']) / 2;
    $textWidth = $settings['width'] - 2 * $margin;
    // Kolom label blok identitas cukup untuk "No. Kuitansi" pada ukuran font terpilih, maksimal separuh lebar teks.
    $labelWidth = min($textWidth / 2, $settings['font_size'] * 2.5 + 2);
    $showCashFlow = $data['balance_used'] > 0 || $data['deposit'] > 0;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk {{ $data['number'] }}</title>
    {{-- Ukuran kertas (lebar x panjang isi) diisi skrip di bawah; untuk PDF ukuran halaman diatur DomPDF. --}}
    <style id="thermal-page">@page { margin: 0; }</style>
    <style>
        html, body { margin: 0; padding: 0; background: #fff; color: #000; }
        body {
            font-family: {!! $forPdf ? '"DejaVu Sans", sans-serif' : 'Arial, "Helvetica Neue", Helvetica, "Liberation Sans", sans-serif' !!};
            font-size: {{ $settings['font_size'] }}pt;
            font-weight: normal;
            line-height: 1.3;
        }
        #receipt { width: {{ $mm($textWidth) }}; margin: 0 0 0 {{ $mm($offset) }}; padding: {{ $mm($margin) }}; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .company { font-size: 1.15em; font-weight: bold; }
        .title { font-weight: bold; letter-spacing: 0.5px; }
        .small { font-size: 0.9em; }
        .section { font-weight: bold; margin-bottom: 0.5mm; }
        .sep { border-top: 1px dashed #000; height: 0; margin: 1.5mm 0; }
        .sep.solid { border-top-style: solid; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0.25mm 0; vertical-align: top; word-wrap: break-word; overflow-wrap: anywhere; }
        td.k { padding-right: 2mm; }
        td.v { text-align: right; }
        /* Lebar kolom label tetap supaya nilai panjang (nama, alamat) tidak menekan label sampai terpotong di tengah kata. */
        table.info { table-layout: fixed; }
        .info td.k { width: {{ $mm($labelWidth - 2) }}; overflow-wrap: break-word; }
        td.amt { text-align: right; white-space: nowrap; }
        tr { page-break-inside: avoid; break-inside: avoid; }
        tr.item td { padding-top: 1mm; }
        tr.grand td { font-size: 1.15em; font-weight: bold; padding: 0.6mm 0; }
        .signature { margin-top: 6mm; }
        @media print {
            html, body { width: {{ $mm($paper) }}; }
            /* Struk thermal hitam-putih: tanpa warna latar, bayangan, atau warna teks lain. */
            * { background: transparent !important; box-shadow: none !important; text-shadow: none !important; color: #000 !important; }
        }
    </style>
</head>
<body>
<div id="receipt">
    <div class="center">
        <div class="company">{{ $company['name'] }}</div>
        @if ($company['address'])<div class="small">{{ $company['address'] }}</div>@endif
        @if ($company['phone'])<div class="small">Telp. {{ $company['phone'] }}</div>@endif
    </div>
    <div class="sep"></div>
    <div class="center title">KUITANSI PEMBAYARAN</div>
    @if ($cancelled)<div class="center bold">*** DIBATALKAN ***</div>@endif
    <div class="sep"></div>

    <table class="info">
        <tr><td class="k">No. Kuitansi</td><td class="v">{{ $data['number'] }}</td></tr>
        <tr><td class="k">Tanggal</td><td class="v">{{ $data['date']?->format('d/m/Y H:i') ?? '-' }}</td></tr>
        <tr><td class="k">Pelanggan</td><td class="v">{{ $data['resident_name'] }}</td></tr>
        @if ($data['payer_name'] ?? null)<tr><td class="k">Pembayar</td><td class="v">{{ $data['payer_name'] }}</td></tr>@endif
        <tr><td class="k">Unit</td><td class="v">{{ $data['unit_id'] }}</td></tr>
        <tr><td class="k">Cluster</td><td class="v">{{ $data['address'] ?: '-' }}</td></tr>
        <tr><td class="k">Metode</td><td class="v">{{ $data['method'] }}@if ($data['loket']) &middot; Loket {{ $data['loket'] }}@endif</td></tr>
        @if ($data['cashier'])<tr><td class="k">Kasir</td><td class="v">{{ $data['cashier'] }}</td></tr>@endif
    </table>

    <div class="sep"></div>
    <div class="section">RINCIAN PEMBAYARAN</div>
    <table>
        @foreach ($data['rows'] as $row)
            <tr class="item">
                <td class="bold" colspan="2">
                    {{ $row['period'] }}@if ($row['type']) &middot; {{ $row['type'] }}@endif
                    @if ($row['overdue_months'])<span class="small" style="font-weight: normal;">({{ $row['overdue_months'] }} bln tunggakan)</span>@endif
                </td>
            </tr>
            @if ($row['discount'] > 0)
                <tr><td class="k">Tagihan</td><td class="amt">{{ $rupiah($row['billed']) }}</td></tr>
                <tr><td class="k">Diskon</td><td class="amt">- {{ $rupiah($row['discount']) }}</td></tr>
            @endif
            <tr><td class="k">Pokok</td><td class="amt">{{ $rupiah($row['principal']) }}</td></tr>
            @if ($row['penalty'] > 0)<tr><td class="k">Denda</td><td class="amt">{{ $rupiah($row['penalty']) }}</td></tr>@endif
            @if ($row['waived'] > 0)<tr><td class="k">Keringanan denda</td><td class="amt">- {{ $rupiah($row['waived']) }}</td></tr>@endif
        @endforeach
    </table>

    <div class="sep"></div>
    <table>
        <tr><td class="k">Total Pokok</td><td class="amt">{{ $rupiah($data['total_principal']) }}</td></tr>
        <tr><td class="k">Total Denda</td><td class="amt">{{ $rupiah($data['total_penalty']) }}</td></tr>
        @if ($data['total_discount'] > 0)<tr><td class="k">Total Diskon (sudah dipotong)</td><td class="amt">{{ $rupiah($data['total_discount']) }}</td></tr>@endif
    </table>
    <div class="sep solid"></div>
    <table>
        <tr class="grand"><td class="k">TOTAL BAYAR</td><td class="amt">{{ $rupiah($data['grand_total']) }}</td></tr>
    </table>
    <div class="sep solid"></div>
    <table>
        @if ($data['balance_used'] > 0)<tr><td class="k">Dari saldo unit</td><td class="amt">- {{ $rupiah($data['balance_used']) }}</td></tr>@endif
        @if ($data['deposit'] > 0)<tr><td class="k">Kelebihan (masuk saldo)</td><td class="amt">+ {{ $rupiah($data['deposit']) }}</td></tr>@endif
        @if ($showCashFlow)<tr class="bold"><td class="k">Uang Diterima</td><td class="amt">{{ $rupiah($data['cash_received']) }}</td></tr>@endif
        <tr><td class="k">Sisa Tagihan Unit</td><td class="amt">{{ $rupiah($data['remaining']) }}@if ($data['remaining'] <= 0) (Lunas)@endif</td></tr>
    </table>

    <div class="sep"></div>
    <div class="small">Terbilang:</div>
    <div>{{ \App\Support\Terbilang::rupiah($data['grand_total']) }}</div>
    @if ($data['notes'])
        <div class="sep"></div>
        <div class="small">Catatan: {{ $data['notes'] }}</div>
    @endif

    <div class="sep"></div>
    <div class="center small">Terima kasih atas pembayaran Anda.<br>Simpan struk ini sebagai bukti pembayaran yang sah.</div>
    <div class="center signature">( {{ $data['cashier'] ?: 'Petugas' }} )</div>
    <div class="center small">Dicetak {{ now()->format('d/m/Y H:i') }}</div>
</div>
@unless ($forPdf)
    <script>
        // Panjang kertas = tinggi isi struk: printer tidak memecah struk ke halaman berikutnya dan tidak
        // menyisakan kertas kosong. Diukur ulang saat font selesai dimuat dan tepat sebelum mencetak.
        (function () {
            var page = document.getElementById('thermal-page');
            var receipt = document.getElementById('receipt');
            function fit() {
                var heightMm = Math.ceil(receipt.getBoundingClientRect().height * 25.4 / 96) + 2;
                page.textContent = '@page { size: {{ $paper }}mm ' + heightMm + 'mm; margin: 0; }';
            }
            fit();
            window.addEventListener('load', fit);
            window.addEventListener('beforeprint', fit);
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(fit);
            }
        })();
    </script>
@endunless
</body>
</html>
