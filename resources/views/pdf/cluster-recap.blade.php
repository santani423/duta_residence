@extends('pdf.layout')

@section('content')
    <h1>Rekap Klaster</h1>
    <table>
        <thead><tr><th>Kode</th><th>Nama</th><th class="right">Tarif Bulanan</th><th class="right">Jumlah Penghuni</th></tr></thead>
        <tbody>
            @foreach ($clusters as $cluster)
                <tr>
                    <td>{{ $cluster->id }}</td>
                    <td>{{ $cluster->name }}</td>
                    <td class="right">Rp {{ number_format($cluster->monthly_rate, 0, ',', '.') }}</td>
                    <td class="right">{{ $cluster->units_count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
