@extends('pdf.layout')

@section('content')
    <h1>Surat Pembayaran Tunai</h1>
    <div class="muted">No. Kuitansi: {{ $receipt->number }}</div>
    <h2>Informasi Pelanggan</h2>
    <table>
        <tr><th>Nama</th><td>{{ $receipt->customer_name }}</td></tr>
        <tr><th>Klaster</th><td>{{ $receipt->cluster_name }}</td></tr>
        <tr><th>Blok/Kavling</th><td>{{ $receipt->block }}/{{ $receipt->lot_number }}</td></tr>
        <tr><th>Tanggal</th><td>{{ $receipt->transaction_date->format('d/m/Y H:i') }}</td></tr>
    </table>
    <h2>Rincian</h2>
    <table>
        <thead><tr><th>Periode</th><th class="right">Tagihan</th><th class="right">Denda</th></tr></thead>
        <tbody>
            @foreach ($receipt->billings as $billing)
                <tr>
                    <td>{{ sprintf('%04d-%02d', $billing->year, $billing->month) }}</td>
                    <td class="right">Rp {{ number_format($billing->amount, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($billing->penalty, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr><th colspan="2">Total</th><th class="right">Rp {{ number_format($receipt->grand_total, 0, ',', '.') }}</th></tr>
        </tfoot>
    </table>
@endsection
