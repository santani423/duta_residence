@extends('pdf.layout')

@section('content')
    <h1>Receipt Pembayaran</h1>
    <div class="muted">Transaksi: {{ $payment->transaction_number }}</div>

    <h2>Informasi Pembayaran</h2>
    <table>
        <tr><th>Invoice Gateway</th><td>{{ $payment->invoice_number }}</td></tr>
        <tr><th>Customer</th><td>{{ $payment->customer->name ?? '-' }}</td></tr>
        <tr><th>Gateway</th><td>{{ $payment->payment_provider }}</td></tr>
        <tr><th>Status</th><td>{{ $payment->status }}</td></tr>
        <tr><th>Tanggal Transaksi</th><td>{{ optional($payment->created_at)->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Tanggal Bayar</th><td>{{ optional($payment->paid_at)->format('d/m/Y H:i') ?: '-' }}</td></tr>
    </table>

    <h2>Rincian Biaya</h2>
    <table>
        <tr><th class="right">Subtotal</th><td class="right">Rp {{ number_format($payment->subtotal, 0, ',', '.') }}</td></tr>
        <tr><th class="right">Pajak</th><td class="right">Rp {{ number_format($payment->tax, 0, ',', '.') }}</td></tr>
        <tr><th class="right">Biaya Administrasi</th><td class="right">Rp {{ number_format($payment->admin_fee, 0, ',', '.') }}</td></tr>
        <tr><th class="right">Total</th><td class="right">Rp {{ number_format($payment->total, 0, ',', '.') }}</td></tr>
    </table>
@endsection
