@extends('pdf.layout')

@section('content')
    <h1>{{ $letterTitle }}</h1>
    <div class="muted">Nomor Surat: CL-{{ $letter->id }} — {{ $letter->generated_at->format('d-m-Y') }}</div>
    <table>
        <tr><th>Penghuni</th><td>{{ $letter->resident->name }}</td></tr>
        <tr><th>Unit</th><td>{{ $letter->unit->cluster->name ?? '' }} / {{ $letter->unit->block }}-{{ $letter->unit->lot_number }}</td></tr>
        @if($letter->billing)
            <tr><th>Periode Tagihan</th><td>{{ sprintf('%04d-%02d', $letter->billing->year, $letter->billing->month) }}</td></tr>
        @endif
    </table>
    <div style="margin-top: 16px; white-space: pre-line;">{{ $letter->content }}</div>
@endsection
