{{-- Kuitansi continuous form / NCR. Ukuran dan margin mengikuti ContinuousFormReceiptPrinter. --}}
@php
    $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $mm = fn (float $value) => rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.').'mm';
    $contentWidth = $settings['width'] - $settings['margin_left'] - $settings['margin_right'];
    $company = $data['company'];
    $cancelled = ($data['status'] ?? null) === 'Dibatalkan';
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $data['number'] }}</title>
    <style>
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; color: #000; background: #fff; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: {{ $settings['font_size'] }}pt; line-height: 1.15; }
        #receipt { width: {{ $mm($contentWidth) }}; margin-left: {{ $mm($settings['margin_left']) }}; padding-top: {{ $mm($settings['margin_top']) }}; padding-bottom: {{ $mm($settings['margin_bottom']) }}; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 1px 3px; vertical-align: top; }
        .header { border-bottom: 1px solid #000; }
        .company { font-size: 1.15em; font-weight: bold; }
        .title { font-weight: bold; font-size: 1.3em; text-align: right; }
        .small { font-size: .8em; }
        .info { margin-top: 3px; }
        .info td:first-child { width: 22%; }
        .lines { margin-top: 4px; table-layout: fixed; font-size: .85em; }
        .lines th, .lines td { border: 1px solid #000; }
        .lines th { text-align: left; }
        .right { text-align: right; white-space: nowrap; }
        .lines td:first-child { overflow-wrap: anywhere; }
        .totals-words { margin-top: 4px; table-layout: fixed; }
        .totals-words > tbody > tr > td { width: 50%; vertical-align: top; }
        .total { width: 100%; }
        .total td { padding: 1px 3px; }
        .grand { border-top: 1px solid #000; border-bottom: 1px solid #000; font-weight: bold; }
        .words { border-top: 1px solid #000; padding-top: 3px; overflow-wrap: anywhere; }
        .sign { margin-top: 2px; page-break-inside: avoid; }
        .sign td { width: 50%; text-align: center; }
        .sign-space { height: 8px; }
        .sign-line { border-top: 1px solid #000; display: block; width: 55%; margin: 0 auto; padding-top: 2px; }
        .stamp { font-weight: bold; border: 1px solid #000; padding: 2px 5px; }
        .note { margin-top: 3px; }
        @media print { * { color: #000 !important; background: transparent !important; box-shadow: none !important; } }
    </style>
</head>
<body>
<div id="receipt">
    <table class="header">
        <tr>
            @if ($company['logo'])
                <td style="width:14%; padding-left:0; vertical-align:middle;"><img src="{{ $company['logo'] }}" style="width:12mm; height:12mm;" alt="Logo"></td>
            @endif
            <td style="width:62%">
                <div class="company">{{ $company['name'] }}</div>
                @if ($company['address'])<div>{{ $company['address'] }}</div>@endif
                @if ($company['phone'] || $company['email'])<div class="small">@if ($company['phone'])Telp: {{ $company['phone'] }}@endif @if ($company['email']){{ $company['email'] }}@endif</div>@endif
            </td>
            <td class="title">KUITANSI<br><span class="small">No. {{ $data['number'] }}</span></td>
        </tr>
    </table>
    @if ($cancelled)<p class="stamp">DIBATALKAN</p>@endif

    <table class="info">
        <tr><td>Sudah terima dari</td><td>: <b>{{ $data['payer_name'] ?? $data['resident_name'] }}</b></td></tr>
        @if ($data['payer_name'] ?? null)<tr><td>Atas nama penghuni</td><td>: {{ $data['resident_name'] }}</td></tr>@endif
        <tr><td>Unit / Alamat</td><td>: {{ $data['unit_id'] }} — {{ $data['address'] }}</td></tr>
        <tr><td>Untuk pembayaran</td><td>: {{ $data['description'] }}</td></tr>
        <tr><td>Tanggal pembayaran</td><td>: {{ $data['date']?->format('d/m/Y H:i') ?? '-' }}</td></tr>
        <tr><td>Metode pembayaran</td><td>: {{ $data['method'] }}@if ($data['loket']) · Loket {{ $data['loket'] }}@endif</td></tr>
        <tr><td>Status</td><td>: {{ $data['status'] }}</td></tr>
    </table>

    <table class="lines">
        <colgroup><col style="width:24%"><col style="width:12%"><col style="width:16%"><col style="width:12%"><col style="width:18%"><col style="width:18%"></colgroup>
        <thead><tr><th>Periode</th><th>Jenis</th><th class="right">Tagihan</th><th class="right">Diskon</th><th class="right">Pokok</th><th class="right">Denda</th></tr></thead>
        <tbody>
        @foreach ($data['rows'] as $row)
            <tr>
                <td>{{ $row['period'] }}@if ($row['overdue_months']) <span class="small">({{ $row['overdue_months'] }} bln)</span>@endif</td>
                <td>{{ $row['type'] ?: '-' }}</td>
                <td class="right">{{ $rupiah($row['billed']) }}</td>
                <td class="right">{{ $row['discount'] > 0 ? '- '.$rupiah($row['discount']) : '-' }}</td>
                <td class="right">{{ $rupiah($row['principal']) }}</td>
                <td class="right">{{ $rupiah($row['penalty']) }}@if ($row['waived'] > 0)<br><span class="small">(keringanan - {{ $rupiah($row['waived']) }})</span>@endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <table class="totals-words"><tr><td><table class="total">
        <tr><td>Total pokok</td><td class="right">{{ $rupiah($data['total_principal']) }}</td></tr>
        <tr><td>Total denda</td><td class="right">{{ $rupiah($data['total_penalty']) }}</td></tr>
        @if ($data['total_discount'] > 0)<tr><td>Total diskon</td><td class="right">{{ $rupiah($data['total_discount']) }}</td></tr>@endif
        <tr class="grand"><td>TOTAL DIBAYAR</td><td class="right">{{ $rupiah($data['grand_total']) }}</td></tr>
        @if ($data['balance_used'] > 0)<tr><td>Dari saldo unit</td><td class="right">- {{ $rupiah($data['balance_used']) }}</td></tr>@endif
        @if ($data['deposit'] > 0)<tr><td>Kelebihan bayar</td><td class="right">+ {{ $rupiah($data['deposit']) }}</td></tr>@endif
        @if ($data['balance_used'] > 0 || $data['deposit'] > 0)<tr><td>Uang diterima</td><td class="right">{{ $rupiah($data['cash_received']) }}</td></tr>@endif
        <tr><td>Sisa tagihan unit</td><td class="right">{{ $rupiah($data['remaining']) }}@if ($data['remaining'] <= 0) (Lunas)@endif</td></tr>
    </table></td><td><div class="words"><b>Terbilang:</b> {{ \App\Support\Terbilang::rupiah($data['grand_total']) }}</div>
    @if ($data['notes'])<div class="note">Catatan: {{ $data['notes'] }}</div>@endif</td></tr></table>
    <table class="sign">
        <tr>
            <td>Penerima<div class="sign-space">@if ($cancelled)<br><span class="stamp">DIBATALKAN</span>@endif</div><span class="sign-line">{{ $data['cashier'] ?: '( ........................ )' }}</span></td>
            <td>Pembayar<div class="sign-space"></div><span class="sign-line">{{ $data['payer_name'] ?? $data['resident_name'] }}</span></td>
        </tr>
    </table>
    <div class="note small" style="text-align:center">Kuitansi ini sah sebagai bukti pembayaran. Dicetak {{ now()->format('d/m/Y H:i') }}.</div>
</div>
</body>
</html>
