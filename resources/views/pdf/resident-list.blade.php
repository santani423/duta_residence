@extends('pdf.layout')

@section('content')
    <h1>Daftar Penghuni</h1>
    <table>
        <thead><tr><th>ID</th><th>Pemilik</th><th>Klaster</th><th>Blok</th><th>Kavling</th><th>Telepon</th></tr></thead>
        <tbody>
            @foreach ($units as $unit)
                <tr>
                    <td>{{ $unit->id }}</td>
                    <td>{{ $unit->resident->name ?? '-' }}</td>
                    <td>{{ $unit->cluster->name }}</td>
                    <td>{{ $unit->block }}</td>
                    <td>{{ $unit->lot_number }}</td>
                    <td>{{ $unit->resident->phone ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
