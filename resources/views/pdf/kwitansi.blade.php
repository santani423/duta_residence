@php
    $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $company = $data['company'];
    $cancelled = ($data['status'] ?? null) === 'Dibatalkan';
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $data['number'] }}</title>
    <style>
        @if ($thermal)
            @page { margin: 8px 8px; }
            body { font-family: DejaVu Sans Mono, monospace; font-size: 9px; color: #000; margin: 0; }
            .center { text-align: center; }
            .name { font-size: 12px; font-weight: bold; }
            .sep { border-top: 1px dashed #000; margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; }
            td { padding: 1px 0; vertical-align: top; }
            .right { text-align: right; }
            .bold { font-weight: bold; }
            .title { font-size: 11px; font-weight: bold; letter-spacing: 1px; }
        @else
            @page { margin: 32px 40px; }
            body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #000; margin: 0; }
            .frame { border: 2px solid #000; padding: 18px 22px; }
            table { width: 100%; border-collapse: collapse; }
            .head td { vertical-align: middle; }
            .company { font-size: 16px; font-weight: bold; }
            .small { font-size: 10px; }
            .title { font-size: 20px; font-weight: bold; letter-spacing: 3px; text-align: right; }
            .rule { border-top: 2px solid #000; border-bottom: 1px solid #000; height: 2px; margin: 10px 0 14px; }
            .info td { padding: 3px 0; vertical-align: top; }
            .info td.k { width: 130px; }
            .info td.c { width: 12px; }
            .lines { margin-top: 12px; }
            .lines th { border: 1px solid #000; background: #e5e5e5; padding: 5px 6px; font-size: 10px; text-align: left; }
            .lines td { border: 1px solid #000; padding: 4px 6px; }
            .lines tr { page-break-inside: avoid; }
            .right { text-align: right; }
            .bold { font-weight: bold; }
            .sum { margin-top: 10px; width: 55%; margin-left: 45%; }
            .sum td { padding: 3px 6px; }
            .sum .grand td { border-top: 2px solid #000; border-bottom: 2px solid #000; font-size: 13px; font-weight: bold; }
            .words { border: 1px solid #000; padding: 6px 10px; margin-top: 12px; font-style: italic; }
            .sign { margin-top: 26px; page-break-inside: avoid; }
            .sign td { width: 50%; text-align: center; vertical-align: top; }
            .sign .space { height: 64px; }
            .sign .line { display: inline-block; border-top: 1px solid #000; padding-top: 3px; min-width: 170px; }
            .stamp { border: 2px solid #000; padding: 3px 12px; font-weight: bold; font-size: 14px; display: inline-block; }
            .note { margin-top: 10px; font-size: 10px; }
        @endif
    </style>
</head>
<body>
@if ($thermal)
    <div class="center">
        <div class="name">{{ $company['name'] }}</div>
        @if ($company['address'])<div>{{ $company['address'] }}</div>@endif
        @if ($company['phone'])<div>Telp: {{ $company['phone'] }}</div>@endif
    </div>
    <div class="sep"></div>
    <div class="center title">KUITANSI PEMBAYARAN</div>
    @if ($cancelled)<div class="center bold">*** DIBATALKAN ***</div>@endif
    <div class="sep"></div>
    <table>
        <tr><td>No</td><td class="right">{{ $data['number'] }}</td></tr>
        <tr><td>Tanggal</td><td class="right">{{ $data['date']?->format('d/m/Y H:i') ?? '-' }}</td></tr>
        <tr><td>Nama</td><td class="right">{{ $data['resident_name'] }}</td></tr>
        <tr><td>Unit</td><td class="right">{{ $data['unit_id'] }}</td></tr>
        <tr><td>Alamat</td><td class="right">{{ $data['address'] }}</td></tr>
        <tr><td>Metode</td><td class="right">{{ $data['method'] }}</td></tr>
        @if ($data['cashier'])<tr><td>Kasir</td><td class="right">{{ $data['cashier'] }}</td></tr>@endif
    </table>
    <div class="sep"></div>
    @foreach ($data['rows'] as $row)
        <table>
            <tr><td class="bold" colspan="2">{{ $row['period'] }}@if ($row['type']) &middot; {{ $row['type'] }}@endif</td></tr>
            @if ($row['discount'] > 0)
                <tr><td>Tagihan</td><td class="right">{{ $rupiah($row['billed']) }}</td></tr>
                <tr><td>Diskon</td><td class="right">- {{ $rupiah($row['discount']) }}</td></tr>
            @endif
            <tr><td>Pokok</td><td class="right">{{ $rupiah($row['principal']) }}</td></tr>
            @if ($row['penalty'] > 0)<tr><td>Denda</td><td class="right">{{ $rupiah($row['penalty']) }}</td></tr>@endif
            @if ($row['waived'] > 0)<tr><td>Keringanan denda</td><td class="right">- {{ $rupiah($row['waived']) }}</td></tr>@endif
        </table>
    @endforeach
    <div class="sep"></div>
    <table>
        <tr><td>Total Pokok</td><td class="right">{{ $rupiah($data['total_principal']) }}</td></tr>
        <tr><td>Total Denda</td><td class="right">{{ $rupiah($data['total_penalty']) }}</td></tr>
        @if ($data['total_discount'] > 0)<tr><td>Total Diskon</td><td class="right">{{ $rupiah($data['total_discount']) }}</td></tr>@endif
        <tr class="bold"><td>TOTAL DIBAYAR</td><td class="right">{{ $rupiah($data['grand_total']) }}</td></tr>
        @if ($data['balance_used'] > 0)<tr><td>Dari saldo unit</td><td class="right">- {{ $rupiah($data['balance_used']) }}</td></tr>@endif
        @if ($data['deposit'] > 0)<tr><td>Kelebihan (saldo)</td><td class="right">+ {{ $rupiah($data['deposit']) }}</td></tr>@endif
        @if ($data['balance_used'] > 0 || $data['deposit'] > 0)<tr class="bold"><td>Uang Diterima</td><td class="right">{{ $rupiah($data['cash_received']) }}</td></tr>@endif
        <tr><td>Sisa Tagihan</td><td class="right">{{ $rupiah($data['remaining']) }}</td></tr>
    </table>
    <div class="sep"></div>
    <div class="center">{{ \App\Support\Terbilang::rupiah($data['grand_total']) }}</div>
    <div class="sep"></div>
    <div class="center">Terima kasih atas pembayaran Anda.<br>Simpan struk ini sebagai bukti pembayaran.</div>
    <br>
    <div class="center">( {{ $data['cashier'] ?: 'Petugas' }} )</div>
@else
    <div class="frame">
        <table class="head">
            <tr>
                @if ($company['logo'])
                    <td style="width: 60px;"><img src="{{ $company['logo'] }}" style="width: 52px;" alt=""></td>
                @endif
                <td>
                    <div class="company">{{ $company['name'] }}</div>
                    <div class="small">
                        {{ $company['address'] }}
                        @if ($company['phone'])<br>Telp: {{ $company['phone'] }}@endif
                        @if ($company['email']) &middot; {{ $company['email'] }}@endif
                    </div>
                </td>
                <td class="title">KUITANSI<br><span class="small" style="letter-spacing: 0;">No. {{ $data['number'] }}</span></td>
            </tr>
        </table>
        <div class="rule"></div>

        <table class="info">
            <tr><td class="k">Sudah terima dari</td><td class="c">:</td><td><b>{{ $data['resident_name'] }}</b></td></tr>
            <tr><td class="k">Unit / Alamat</td><td class="c">:</td><td>{{ $data['unit_id'] }} &mdash; {{ $data['address'] }}</td></tr>
            <tr><td class="k">Untuk pembayaran</td><td class="c">:</td><td>{{ $data['description'] }}</td></tr>
            <tr><td class="k">Tanggal pembayaran</td><td class="c">:</td><td>{{ $data['date']?->format('d/m/Y H:i') ?? '-' }}</td></tr>
            <tr><td class="k">Metode pembayaran</td><td class="c">:</td><td>{{ $data['method'] }}@if ($data['loket']) &middot; Loket {{ $data['loket'] }}@endif</td></tr>
            <tr><td class="k">Status</td><td class="c">:</td><td>{{ $data['status'] }}</td></tr>
        </table>

        <table class="lines">
            <thead>
                <tr>
                    <th>Periode</th>
                    <th>Jenis</th>
                    <th class="right">Tagihan</th>
                    <th class="right">Diskon</th>
                    <th class="right">Pokok Dibayar</th>
                    <th class="right">Denda Dibayar</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['rows'] as $row)
                    <tr>
                        <td>{{ $row['period'] }}@if ($row['overdue_months']) <span class="small">({{ $row['overdue_months'] }} bln tunggakan)</span>@endif</td>
                        <td>{{ $row['type'] ?: '-' }}</td>
                        <td class="right">{{ $rupiah($row['billed']) }}</td>
                        <td class="right">{{ $row['discount'] > 0 ? '- '.$rupiah($row['discount']) : '-' }}</td>
                        <td class="right">{{ $rupiah($row['principal']) }}</td>
                        <td class="right">{{ $rupiah($row['penalty']) }}@if ($row['waived'] > 0)<br><span class="small">(keringanan - {{ $rupiah($row['waived']) }})</span>@endif</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="sum">
            <tr><td>Total pokok</td><td class="right">{{ $rupiah($data['total_principal']) }}</td></tr>
            <tr><td>Total denda</td><td class="right">{{ $rupiah($data['total_penalty']) }}</td></tr>
            @if ($data['total_discount'] > 0)<tr><td>Total diskon (sudah dipotong)</td><td class="right">{{ $rupiah($data['total_discount']) }}</td></tr>@endif
            <tr class="grand"><td>TOTAL DIBAYAR</td><td class="right">{{ $rupiah($data['grand_total']) }}</td></tr>
            @if ($data['balance_used'] > 0)<tr><td>Dibayar dari saldo unit</td><td class="right">- {{ $rupiah($data['balance_used']) }}</td></tr>@endif
            @if ($data['deposit'] > 0)<tr><td>Kelebihan bayar (masuk saldo unit)</td><td class="right">+ {{ $rupiah($data['deposit']) }}</td></tr>@endif
            @if ($data['balance_used'] > 0 || $data['deposit'] > 0)<tr class="bold"><td>Uang diterima</td><td class="right">{{ $rupiah($data['cash_received']) }}</td></tr>@endif
            <tr><td>Sisa tagihan unit</td><td class="right">{{ $rupiah($data['remaining']) }}@if ($data['remaining'] <= 0) (Lunas)@endif</td></tr>
        </table>

        <div class="words"><b>Terbilang:</b> {{ \App\Support\Terbilang::rupiah($data['grand_total']) }}</div>
        @if ($data['notes'])<div class="note">Catatan: {{ $data['notes'] }}</div>@endif

        <table class="sign">
            <tr>
                <td>Penerima,<div class="space">@if ($cancelled)<br><span class="stamp">DIBATALKAN</span>@endif</div><span class="line">{{ $data['cashier'] ?: '( ........................ )' }}</span></td>
                <td>Pembayar,<div class="space"></div><span class="line">{{ $data['resident_name'] }}</span></td>
            </tr>
        </table>
        <div class="note" style="text-align: center;">Kuitansi ini sah sebagai bukti pembayaran. Dicetak {{ now()->format('d/m/Y H:i') }}.</div>
    </div>
@endif
</body>
</html>
