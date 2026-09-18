@extends('pdf.layout')

@section('content')
    @php
        $unit = $transaction->unit;
        $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
        $providerLabels = ['loket' => 'Loket', 'manual' => 'Transfer', 'xendit' => 'Xendit', 'midtrans' => 'Midtrans'];
        $rows = $transaction->allocations->isNotEmpty() ? $transaction->allocations : null;
    @endphp

    <h1>Bukti Transaksi Pembayaran</h1>
    <div class="muted">Invoice: {{ $transaction->invoice_number }} &middot; Transaksi: {{ $transaction->transaction_number }}</div>

    <h2>Informasi Penghuni</h2>
    <table>
        <tr><th>Nama</th><td>{{ $unit->resident->name ?? '-' }}</td></tr>
        <tr><th>Unit</th><td>{{ $transaction->unit_id }} &mdash; {{ trim(($unit->cluster->name ?? '').' '.($unit->block ?? '').'/'.($unit->lot_number ?? '')) }}</td></tr>
    </table>

    <h2>Informasi Pembayaran</h2>
    <table>
        <tr><th>Via</th><td>{{ $providerLabels[$transaction->payment_provider] ?? $transaction->payment_provider }}</td></tr>
        <tr><th>Metode</th><td>{{ $transaction->payment_method_label ?? '-' }}</td></tr>
        <tr><th>Status</th><td>{{ $transaction->statusLabel() }}</td></tr>
        <tr><th>Tanggal Transaksi</th><td>{{ $transaction->created_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
        <tr><th>Tanggal Bayar</th><td>{{ $transaction->paid_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
        @if ($transaction->manual_amount)
            <tr><th>Nominal Dibayar</th><td>{{ $rupiah($transaction->manual_amount) }}</td></tr>
        @endif
        @if ($transaction->manual_transfer_date)
            <tr><th>Tanggal Transfer</th><td>{{ $transaction->manual_transfer_date->format('d/m/Y') }}</td></tr>
        @endif
        @if ($transaction->verified_at)
            <tr><th>Diverifikasi Oleh</th><td>{{ $transaction->verifier->name ?? '-' }} ({{ $transaction->verified_at->format('d/m/Y H:i') }})</td></tr>
        @endif
        @if ($transaction->verification_notes)
            <tr><th>Catatan Verifikasi</th><td>{{ $transaction->verification_notes }}</td></tr>
        @endif
    </table>

    <h2>Rincian Tagihan</h2>
    <table>
        @if ($rows)
            <thead><tr><th>Periode</th><th class="right">Pokok</th><th class="right">Denda</th><th class="right">Total</th></tr></thead>
            <tbody>
                @foreach ($rows as $allocation)
                    <tr>
                        <td>{{ $allocation->billing ? sprintf('%04d-%02d', $allocation->billing->year, $allocation->billing->month) : '-' }}</td>
                        <td class="right">{{ $rupiah($allocation->principal_amount) }}</td>
                        <td class="right">{{ $rupiah($allocation->penalty_amount) }}</td>
                        <td class="right">{{ $rupiah($allocation->total_amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        @else
            <thead><tr><th>Periode</th><th>Jenis</th></tr></thead>
            <tbody>
                @forelse ($transaction->billings as $billing)
                    <tr>
                        <td>{{ sprintf('%04d-%02d', $billing->year, $billing->month) }}</td>
                        <td>{{ $billing->billing_type }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="muted">Tidak ada tagihan terkait.</td></tr>
                @endforelse
            </tbody>
        @endif
    </table>

    <h2>Ringkasan</h2>
    <table>
        <tr><th>Subtotal</th><td class="right">{{ $rupiah($transaction->subtotal) }}</td></tr>
        <tr><th>Biaya Administrasi</th><td class="right">{{ $rupiah($transaction->admin_fee) }}</td></tr>
        <tr><th>Total</th><th class="right">{{ $rupiah($transaction->total) }}</th></tr>
    </table>
@endsection
