@extends('pdf.layout')

@section('content')
    @php
        $unit = $transaction->unit;
        $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
        $company = $print['company'];
        $rows = $print['rows'];
        $paidTotal = $print['grand_total'];
    @endphp

    @include('pdf.partials.company-header', ['company' => $company])

    <h1>Detail Transaksi Pembayaran</h1>
    <div class="muted">Invoice: {{ $transaction->invoice_number }} &middot; Transaksi: {{ $transaction->transaction_number }}@if ($print['receipt_number']) &middot; Kuitansi: {{ $print['receipt_number'] }}@endif</div>

    <h2>Informasi Penghuni &amp; Unit</h2>
    <table>
        <tr><th style="width: 32%;">Nama Penghuni</th><td>{{ $unit->resident->name ?? '-' }}</td></tr>
        <tr><th>ID Unit</th><td>{{ $transaction->unit_id }}</td></tr>
        <tr><th>Alamat Unit</th><td>{{ $print['address'] ?: '-' }}</td></tr>
    </table>

    <h2>Informasi Pembayaran</h2>
    <table>
        <tr><th style="width: 32%;">Status</th><td>{{ $transaction->statusLabel() }}</td></tr>
        <tr><th>Via</th><td>{{ ['loket' => 'Loket', 'manual' => 'Transfer', 'xendit' => 'Xendit', 'midtrans' => 'Midtrans'][$transaction->payment_provider] ?? $transaction->payment_provider }}</td></tr>
        <tr><th>Metode</th><td>{{ $print['method'] ?: '-' }}</td></tr>
        <tr><th>Tanggal Transaksi</th><td>{{ $transaction->created_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
        <tr><th>Tanggal Bayar</th><td>{{ $transaction->paid_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
        @if ($transaction->manual_transfer_date)
            <tr><th>Tanggal Transfer</th><td>{{ $transaction->manual_transfer_date->format('d/m/Y') }}</td></tr>
        @endif
        @if ($transaction->manual_proof_uploaded_at)
            <tr><th>Waktu Upload Bukti</th><td>{{ $transaction->manual_proof_uploaded_at->format('d/m/Y H:i') }}</td></tr>
        @endif
        @if ($transaction->manual_sender_name || $transaction->manual_sender_bank || $transaction->manual_sender_account_number)
            <tr><th>Pengirim</th><td>{{ $transaction->manual_sender_name ?: '-' }} &mdash; {{ $transaction->manual_sender_bank ?: '-' }} {{ $transaction->manual_sender_account_number }}</td></tr>
        @endif
        @if ($transaction->manual_amount)
            <tr><th>Nominal Dibayar (transfer)</th><td>{{ $rupiah($transaction->manual_amount) }}</td></tr>
        @endif
        @if ($transaction->manual_notes)
            <tr><th>Catatan Penghuni</th><td>{{ $transaction->manual_notes }}</td></tr>
        @endif
        @if ($transaction->verified_at)
            <tr><th>Diverifikasi Oleh</th><td>{{ $transaction->verifier->name ?? '-' }} ({{ $transaction->verified_at->format('d/m/Y H:i') }})</td></tr>
        @endif
        @if ($transaction->verification_notes)
            <tr><th>Catatan Verifikasi</th><td>{{ $transaction->verification_notes }}</td></tr>
        @endif
        @if ($transaction->creator)
            <tr><th>Dibuat Oleh</th><td>{{ $transaction->creator->name }}</td></tr>
        @endif
    </table>

    <h2>Rincian Tagihan</h2>
    <table>
        <thead>
            <tr>
                <th>Periode</th><th>Jenis</th><th class="right">Tagihan</th><th class="right">Diskon</th>
                <th class="right">Pokok</th><th class="right">Denda</th><th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['period'] }}@if ($row['overdue_months']) <br><span class="muted">{{ $row['overdue_months'] }} bln tunggakan</span>@endif</td>
                    <td>{{ $row['type'] ?: '-' }}</td>
                    <td class="right">{{ $rupiah($row['billed']) }}</td>
                    <td class="right">{{ $row['discount'] > 0 ? '- '.$rupiah($row['discount']) : '-' }}</td>
                    <td class="right">{{ $rupiah($row['principal']) }}</td>
                    <td class="right">{{ $rupiah($row['penalty']) }}@if ($row['waived'] > 0)<br><span class="muted">keringanan - {{ $rupiah($row['waived']) }}</span>@endif</td>
                    <td class="right">{{ $rupiah($row['principal'] + $row['penalty']) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">Tidak ada tagihan terkait.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Ringkasan</h2>
    <table>
        <tr><th style="width: 60%;">Subtotal</th><td class="right">{{ $rupiah($transaction->subtotal) }}</td></tr>
        @if ($print['total_discount'] > 0)
            <tr><th>Diskon (sudah dipotong dari tagihan)</th><td class="right">{{ $rupiah($print['total_discount']) }}</td></tr>
        @endif
        <tr><th>Denda</th><td class="right">{{ $rupiah($print['total_penalty']) }}</td></tr>
        <tr><th>Biaya Administrasi</th><td class="right">{{ $rupiah($transaction->admin_fee) }}</td></tr>
        <tr><th>Total Tagihan Transaksi</th><td class="right">{{ $rupiah($transaction->total) }}</td></tr>
        <tr><th>Total Dibayar</th><th class="right">{{ $transaction->status === 'paid' ? $rupiah($paidTotal) : '-' }}</th></tr>
        <tr><th>Sisa Tagihan Unit (saat dicetak)</th><td class="right">{{ $rupiah($print['remaining']) }}</td></tr>
    </table>

    <p class="muted" style="margin-top: 16px;">Dicetak {{ now()->format('d/m/Y H:i') }}</p>
@endsection
