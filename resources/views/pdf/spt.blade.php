@extends('pdf.layout')

@section('content')
    @php
        $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
        $methodLabels = ['C' => 'Cash', 'D' => 'Debit/Transfer'];
        $channelLabels = ['L' => 'Loket', 'M' => 'Bank Transfer', 'Q' => 'QRIS'];
        $balanceUsed = (float) $receipt->balance_used;
        $depositAmount = (float) $receipt->deposit_amount;
        // Uang yang benar-benar diterima = tagihan yang dilunasi, dikurangi yang dibayar dari
        // saldo unit, ditambah kelebihan bayar yang masuk ke saldo unit.
        $cashReceived = (float) $receipt->grand_total - $balanceUsed + $depositAmount;
        $allocations = $receipt->paymentTransaction?->allocations ?? collect();
    @endphp

    <h1>Kuitansi Pembayaran</h1>
    <div class="muted">No. Kuitansi: {{ $receipt->number }}</div>
    <h2>Informasi Penghuni</h2>
    <table>
        <tr><th>Nama</th><td>{{ $receipt->resident_name }}</td></tr>
        <tr><th>Klaster</th><td>{{ $receipt->cluster_name }}</td></tr>
        <tr><th>Blok/Kavling</th><td>{{ $receipt->block }}/{{ $receipt->lot_number }}</td></tr>
        <tr><th>Tanggal</th><td>{{ $receipt->transaction_date?->format('d/m/Y H:i') ?? '-' }}</td></tr>
        <tr><th>Metode</th><td>{{ $methodLabels[$receipt->payment_method_id] ?? $receipt->payment_method_id }}@if ($receipt->payment_channel_id) ({{ $channelLabels[$receipt->payment_channel_id] ?? $receipt->payment_channel_id }})@endif</td></tr>
        @if ($receipt->loket_code)
            <tr><th>Kode Loket</th><td>{{ $receipt->loket_code }}</td></tr>
        @endif
        @if ($receipt->cashier_name)
            <tr><th>Kasir</th><td>{{ $receipt->cashier_name }}</td></tr>
        @endif
    </table>
    <h2>Rincian</h2>
    <table>
        <thead><tr><th>Periode</th><th class="right">Umur Tunggakan</th><th class="right">Pokok Dibayar</th><th class="right">Denda Dibayar</th></tr></thead>
        <tbody>
            @if ($allocations->isNotEmpty())
                @foreach ($allocations as $allocation)
                    <tr>
                        <td>{{ $allocation->billing ? sprintf('%04d-%02d', $allocation->billing->year, $allocation->billing->month) : '-' }}</td>
                        <td class="right">{{ $allocation->overdue_months }} bulan</td>
                        <td class="right">{{ $rupiah($allocation->principal_amount) }}</td>
                        <td class="right">{{ $rupiah($allocation->penalty_amount) }}</td>
                    </tr>
                @endforeach
            @else
                @foreach ($receipt->billings as $billing)
                    <tr>
                        <td>{{ sprintf('%04d-%02d', $billing->year, $billing->month) }}</td>
                        <td class="right">-</td>
                        <td class="right">{{ $rupiah($billing->amount) }}</td>
                        <td class="right">{{ $rupiah($billing->penalty) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr><th colspan="3">Total Dilunasi</th><th class="right">{{ $rupiah($receipt->grand_total) }}</th></tr>
            @if ($balanceUsed > 0)
                <tr><td colspan="3">Dibayar dari saldo unit</td><td class="right">- {{ $rupiah($balanceUsed) }}</td></tr>
            @endif
            @if ($depositAmount > 0)
                <tr><td colspan="3">Kelebihan bayar (masuk saldo unit)</td><td class="right">+ {{ $rupiah($depositAmount) }}</td></tr>
            @endif
            @if ($balanceUsed > 0 || $depositAmount > 0)
                <tr><th colspan="3">Uang Diterima</th><th class="right">{{ $rupiah($cashReceived) }}</th></tr>
            @endif
        </tfoot>
    </table>
    @if ($receipt->notes)
        <p class="muted">Catatan: {{ $receipt->notes }}</p>
    @endif
@endsection
