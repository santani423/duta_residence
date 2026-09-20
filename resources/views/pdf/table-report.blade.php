@extends('pdf.layout')

@section('content')
    <style>th.right, td.right { white-space: nowrap; }</style>
    <h1>{{ $title }}</h1>
    @foreach ($meta as $line)
        <div class="muted">{{ $line }}</div>
    @endforeach

    <table style="font-size: 10px;">
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th class="{{ ($column['align'] ?? 'left') === 'right' ? 'right' : '' }}">{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $index => $cell)
                        <td class="{{ ($columns[$index]['align'] ?? 'left') === 'right' ? 'right' : '' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}" class="muted">Tidak ada data untuk filter ini.</td></tr>
            @endforelse
        </tbody>
        @if (! empty($footer))
            <tfoot>
                @foreach ($footer as $footerRow)
                    <tr>
                        @foreach ($footerRow as $cell)
                            <th colspan="{{ $cell['colspan'] ?? 1 }}" class="{{ ($cell['align'] ?? 'left') === 'right' ? 'right' : '' }}">{{ $cell['text'] }}</th>
                        @endforeach
                    </tr>
                @endforeach
            </tfoot>
        @endif
    </table>
@endsection
