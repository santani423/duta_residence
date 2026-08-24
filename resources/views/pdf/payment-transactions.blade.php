@extends('pdf.layout')

@section('content')
    <h1>Transaksi Gateway</h1>
    <table>
        <thead><tr><th>Invoice</th><th>Penghuni</th><th>Alamat Unit</th><th>Provider</th><th class="right">Total</th><th>Status</th><th>Dibuat</th></tr></thead>
        <tbody>
            @foreach ($transactions as $transaction)
                <tr>
                    <td>{{ $transaction->invoice_number }}</td>
                    <td>{{ $transaction->unit->resident->name ?? '-' }}</td>
                    <td>{{ trim(($transaction->unit->cluster->name ?? '').' '.($transaction->unit->block ?? '').'/'.($transaction->unit->lot_number ?? '')) }}</td>
                    <td>{{ $transaction->payment_provider }}</td>
                    <td class="right">Rp {{ number_format($transaction->total, 0, ',', '.') }}</td>
                    <td>{{ $transaction->status }}</td>
                    <td>{{ $transaction->created_at?->format('d-m-Y H:i') ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
