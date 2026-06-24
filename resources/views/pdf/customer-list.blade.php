@extends('pdf.layout')

@section('content')
    <h1>Daftar Pelanggan</h1>
    <table>
        <thead><tr><th>ID</th><th>Nama</th><th>Klaster</th><th>Blok</th><th>Kavling</th><th>Telepon</th></tr></thead>
        <tbody>
            @foreach ($customers as $customer)
                <tr>
                    <td>{{ $customer->id }}</td>
                    <td>{{ $customer->name }}</td>
                    <td>{{ $customer->cluster->name }}</td>
                    <td>{{ $customer->block }}</td>
                    <td>{{ $customer->lot_number }}</td>
                    <td>{{ $customer->phone }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
