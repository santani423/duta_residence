@extends('pdf.layout')

@section('content')
    <h1>Invoice Penghuni</h1>
    <div class="muted">Invoice: BIL-{{ $billing->id }}</div>

    <h2>Informasi Penghuni</h2>
    <table>
        <tr><th>Nama</th><td>{{ $billing->unit->resident->name }}</td></tr>
        <tr><th>Estate</th><td>Duta Indah Residence</td></tr>
        <tr><th>Unit</th><td>{{ $billing->unit->cluster->name ?? '-' }} {{ $billing->unit->block }}/{{ $billing->unit->lot_number }}</td></tr>
        <tr><th>Periode</th><td>{{ sprintf('%04d-%02d', $billing->year, $billing->month) }}</td></tr>
        <tr><th>Status</th><td>{{ $billing->status->name ?? '-' }}</td></tr>
    </table>

    <h2>Rincian Tagihan</h2>
    <table>
        <tr><th>Jenis</th><td>{{ $billing->billing_type }}</td></tr>
        <tr><th class="right">Subtotal</th><td class="right">Rp {{ number_format($billing->amount, 0, ',', '.') }}</td></tr>
        <tr><th class="right">Diskon</th><td class="right">Rp {{ number_format($billing->discount, 0, ',', '.') }}</td></tr>
        <tr><th class="right">Umur Tunggakan</th><td class="right">{{ $penaltyDetail['overdue_months'] }} bulan</td></tr>
        <tr><th class="right">Denda</th><td class="right">Rp {{ number_format($penaltyDetail['penalty_amount'], 0, ',', '.') }}</td></tr>
        <tr><th class="right">Total</th><td class="right">Rp {{ number_format($penaltyDetail['total_amount'], 0, ',', '.') }}</td></tr>
        <tr><th class="right">Sudah Dibayar</th><td class="right">Rp {{ number_format($penaltyDetail['total_paid'], 0, ',', '.') }}</td></tr>
        <tr><th class="right">Sisa Tagihan</th><td class="right">Rp {{ number_format($penaltyDetail['total_outstanding'], 0, ',', '.') }}</td></tr>
    </table>

    <p class="muted">
        Aturan denda: tagihan bulan berjalan tidak dikenakan denda; tunggakan 1-2 bulan dikenakan denda Rp15.000;
        tunggakan 3 bulan atau lebih dikenakan denda Rp30.000 per tagihan.
    </p>
@endsection
