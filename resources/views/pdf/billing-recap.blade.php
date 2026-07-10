@extends('pdf.layout')

@section('content')
    <h1>Rekap Tagihan</h1>
    <table>
        <thead><tr><th>Pelanggan</th><th>Klaster</th><th>Periode</th><th>Status</th><th class="right">Nominal</th></tr></thead>
        <tbody>
            @foreach ($billings as $billing)
                <tr>
                    <td>{{ $billing->unit->customer->name }}</td>
                    <td>{{ $billing->unit->cluster->name }}</td>
                    <td>{{ sprintf('%04d-%02d', $billing->year, $billing->month) }}</td>
                    <td>{{ $billing->status_id === '02' ? 'Lunas' : 'Belum Bayar' }}</td>
                    <td class="right">Rp {{ number_format($billing->amount, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
