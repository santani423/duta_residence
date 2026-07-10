@extends('pdf.layout')

@section('content')
    <h1>Invoice Customer</h1>
    <div class="muted">Invoice: BIL-{{ $billing->id }}</div>

    <h2>Informasi Customer</h2>
    <table>
        <tr><th>Nama</th><td>{{ $billing->unit->customer->name }}</td></tr>
        <tr><th>Estate</th><td>Grand Duta Residence</td></tr>
        <tr><th>Unit</th><td>{{ $billing->unit->cluster->name ?? '-' }} {{ $billing->unit->block }}/{{ $billing->unit->lot_number }}</td></tr>
        <tr><th>Periode</th><td>{{ sprintf('%04d-%02d', $billing->year, $billing->month) }}</td></tr>
        <tr><th>Status</th><td>{{ $billing->status_id === '02' ? 'Lunas' : 'Belum Bayar' }}</td></tr>
    </table>

    <h2>Rincian Tagihan</h2>
    <table>
        <tr><th>Jenis</th><td>{{ $billing->billing_type }}</td></tr>
        <tr><th class="right">Subtotal</th><td class="right">Rp {{ number_format($billing->amount, 0, ',', '.') }}</td></tr>
        <tr><th class="right">Denda</th><td class="right">Rp {{ number_format($billing->penalty, 0, ',', '.') }}</td></tr>
        <tr><th class="right">Diskon</th><td class="right">Rp {{ number_format($billing->discount, 0, ',', '.') }}</td></tr>
        <tr><th class="right">Total</th><td class="right">Rp {{ number_format($billing->amount + $billing->penalty - $billing->discount, 0, ',', '.') }}</td></tr>
    </table>
@endsection
