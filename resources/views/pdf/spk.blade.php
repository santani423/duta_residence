@extends('pdf.layout')

@section('content')
    <h1>Surat Pemberitahuan Kredit</h1>
    <div class="muted">Billing ID: {{ $billing->id }}</div>
    <table>
        <tr><th>Penghuni</th><td>{{ $billing->unit->resident->name }}</td></tr>
        <tr><th>Klaster</th><td>{{ $billing->unit->cluster->name }}</td></tr>
        <tr><th>Periode</th><td>{{ sprintf('%04d-%02d', $billing->year, $billing->month) }}</td></tr>
        <tr><th>Pokok</th><td>Rp {{ number_format($penaltyDetail['principal_amount'], 0, ',', '.') }}</td></tr>
        <tr><th>Umur Tunggakan</th><td>{{ $penaltyDetail['overdue_months'] }} bulan</td></tr>
        <tr><th>Denda</th><td>Rp {{ number_format($penaltyDetail['penalty_amount'], 0, ',', '.') }}</td></tr>
        <tr><th>Total</th><td>Rp {{ number_format($penaltyDetail['total_amount'], 0, ',', '.') }}</td></tr>
        <tr><th>Status</th><td>{{ $billing->status->name ?? '-' }}</td></tr>
    </table>
@endsection
