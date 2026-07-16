@extends('pdf.layout')

@section('content')
    <h1>Rekap Tagihan</h1>
    <table>
        <thead><tr><th>Penghuni</th><th>Klaster</th><th>Periode</th><th>Umur Tunggakan</th><th>Status</th><th class="right">Pokok</th><th class="right">Denda</th><th class="right">Total</th></tr></thead>
        <tbody>
            @foreach ($billings as $billing)
                @php($detail = $penaltyDetails[$billing->id])
                <tr>
                    <td>{{ $billing->unit->resident->name }}</td>
                    <td>{{ $billing->unit->cluster->name }}</td>
                    <td>{{ sprintf('%04d-%02d', $billing->year, $billing->month) }}</td>
                    <td>{{ $detail['overdue_months'] }} bulan</td>
                    <td>{{ $billing->status->name ?? '-' }}</td>
                    <td class="right">Rp {{ number_format($detail['principal_amount'], 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($detail['penalty_amount'], 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($detail['total_amount'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
