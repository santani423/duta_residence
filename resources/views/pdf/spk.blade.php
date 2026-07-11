@extends('pdf.layout')

@section('content')
    <h1>Surat Pemberitahuan Kredit</h1>
    <div class="muted">Billing ID: {{ $billing->id }}</div>
    <table>
        <tr><th>Penghuni</th><td>{{ $billing->unit->resident->name }}</td></tr>
        <tr><th>Klaster</th><td>{{ $billing->unit->cluster->name }}</td></tr>
        <tr><th>Periode</th><td>{{ sprintf('%04d-%02d', $billing->year, $billing->month) }}</td></tr>
        <tr><th>Nominal</th><td>Rp {{ number_format($billing->amount, 0, ',', '.') }}</td></tr>
        <tr><th>Status</th><td>{{ $billing->status_id === '02' ? 'Lunas' : 'Belum Bayar' }}</td></tr>
    </table>
@endsection
