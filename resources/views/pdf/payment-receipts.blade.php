@extends('pdf.layout')

@section('content')
    <h1>Riwayat Kuitansi</h1>
    <table>
        <thead><tr><th>Nomor</th><th>Penghuni</th><th>Alamat Unit</th><th>Tanggal</th><th>Periode</th><th class="right">Total</th><th>Status</th><th>Kasir</th></tr></thead>
        <tbody>
            @foreach ($receipts as $receipt)
                <tr>
                    <td>{{ $receipt->number }}</td>
                    <td>{{ $receipt->resident_name }}</td>
                    <td>{{ trim(($receipt->cluster_name ?? '').' '.($receipt->block ?? '').'/'.($receipt->lot_number ?? '')) }}</td>
                    <td>{{ $receipt->transaction_date?->format('d-m-Y H:i') ?? '-' }}</td>
                    <td>{{ $receipt->billing_periods }}</td>
                    <td class="right">Rp {{ number_format($receipt->grand_total, 0, ',', '.') }}</td>
                    <td>{{ $receipt->status }}</td>
                    <td>{{ $receipt->cashier_name }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
