@extends('pdf.layout')

@section('content')
    <h1>Surat Pembayaran Tunai</h1>
    <div class="muted">No. Kuitansi: {{ $receipt->number }}</div>
    <h2>Informasi Penghuni</h2>
    <table>
        <tr><th>Nama</th><td>{{ $receipt->resident_name }}</td></tr>
        <tr><th>Klaster</th><td>{{ $receipt->cluster_name }}</td></tr>
        <tr><th>Blok/Kavling</th><td>{{ $receipt->block }}/{{ $receipt->lot_number }}</td></tr>
        <tr><th>Tanggal</th><td>{{ $receipt->transaction_date->format('d/m/Y H:i') }}</td></tr>
    </table>
    <h2>Rincian</h2>
    <table>
        <thead><tr><th>Periode</th><th class="right">Umur Tunggakan</th><th class="right">Pokok Dibayar</th><th class="right">Denda Dibayar</th></tr></thead>
        <tbody>
            @if ($receipt->paymentTransaction && $receipt->paymentTransaction->allocations->isNotEmpty())
                @foreach ($receipt->paymentTransaction->allocations as $allocation)
                    <tr>
                        <td>{{ sprintf('%04d-%02d', $allocation->billing->year, $allocation->billing->month) }}</td>
                        <td class="right">{{ $allocation->overdue_months }} bulan</td>
                        <td class="right">Rp {{ number_format($allocation->principal_amount, 0, ',', '.') }}</td>
                        <td class="right">Rp {{ number_format($allocation->penalty_amount, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            @else
                @foreach ($receipt->billings as $billing)
                    <tr>
                        <td>{{ sprintf('%04d-%02d', $billing->year, $billing->month) }}</td>
                        <td class="right">-</td>
                        <td class="right">Rp {{ number_format($billing->amount, 0, ',', '.') }}</td>
                        <td class="right">Rp {{ number_format($billing->penalty, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            <tr><th colspan="3">Total</th><th class="right">Rp {{ number_format($receipt->grand_total, 0, ',', '.') }}</th></tr>
        </tfoot>
    </table>
@endsection
