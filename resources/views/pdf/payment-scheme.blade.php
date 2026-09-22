@extends('pdf.layout')

@section('content')
    @php
        $rupiah = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
        $percent = fn ($part, $whole) => $whole > 0 ? number_format(((float) $part / (float) $whole) * 100, 2, ',', '.').'%' : '0%';
        $unit = $paymentScheme->unit;
        $statusLabels = ['pending' => 'Menunggu Admin', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];
        $paymentStatusLabels = ['unpaid' => 'Belum dibayar', 'partial' => 'Dibayar sebagian', 'paid' => 'Lunas'];
        $bulan = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $period = fn ($year, $month) => $year && $month ? trim(($bulan[(int) $month] ?? '').' '.$year) : '-';
        $requested = $paymentScheme->requested_snapshot;
        $rejectedMonths = $paymentScheme->items->where('status', 'rejected')
            ->map(fn ($item) => $period($item->billing?->year, $item->billing?->month))
            ->implode(', ');
    @endphp

    @include('pdf.partials.company-header', ['company' => $company])

    <h1>Detail Skema Pembayaran</h1>
    <div class="muted">Skema #{{ $paymentScheme->id }}</div>

    <h2>Informasi Unit &amp; Penghuni</h2>
    <table>
        <tr><th style="width: 32%;">ID Unit</th><td>{{ $paymentScheme->unit_id }}</td></tr>
        <tr><th>Klaster / Blok / Kavling</th><td>{{ $unit?->cluster?->name }} {{ $unit?->block }}/{{ $unit?->lot_number }}</td></tr>
        <tr><th>Penghuni</th><td>{{ $unit?->resident?->name ?? '-' }}</td></tr>
    </table>

    <h2>Informasi Pengajuan</h2>
    <table>
        <tr><th style="width: 32%;">Status</th><td>{{ $statusLabels[$paymentScheme->status] ?? $paymentScheme->status }}</td></tr>
        <tr><th>Diajukan Oleh</th><td>{{ $paymentScheme->submitter?->name ?? '-' }} &middot; {{ $paymentScheme->submitted_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
        <tr><th>Diputuskan Oleh</th><td>{{ $paymentScheme->decided_at ? ($paymentScheme->decider?->name ?? '-') : '-' }}{{ $paymentScheme->decided_at ? ' · '.$paymentScheme->decided_at->format('d/m/Y H:i') : '' }}</td></tr>
        <tr><th>Alasan Pengajuan</th><td>{{ $paymentScheme->reason ?: '-' }}</td></tr>
        <tr><th>Catatan Admin</th><td>{{ $paymentScheme->review_notes ?: '-' }}</td></tr>
        @if ($paymentScheme->status === 'cancelled')
            <tr><th>Alasan Dibatalkan</th><td>{{ $paymentScheme->cancellation_reason ?: '-' }}</td></tr>
        @endif
    </table>

    @if ($paymentScheme->adjusted_at && $requested)
        <h2>Perubahan oleh Admin saat Persetujuan</h2>
        <div class="muted" style="margin-bottom: 6px;">Diubah oleh {{ $paymentScheme->adjuster?->name ?? '-' }} pada {{ $paymentScheme->adjusted_at->format('d/m/Y H:i') }}</div>
        <table>
            <thead>
                <tr><th></th><th>Usulan Loket</th><th>Disetujui Admin</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Diskon Pokok</td>
                    <td>{{ $rupiah($requested['principal_discount'] ?? 0) }} ({{ $percent($requested['principal_discount'] ?? 0, $requested['original_principal'] ?? $paymentScheme->original_principal) }})</td>
                    <td>{{ $rupiah($paymentScheme->principal_discount) }} ({{ $percent($paymentScheme->principal_discount, $paymentScheme->original_principal) }})</td>
                </tr>
                <tr>
                    <td>Keringanan Denda</td>
                    <td>{{ $rupiah($requested['penalty_reduction'] ?? 0) }}</td>
                    <td>{{ $rupiah($paymentScheme->penalty_reduction) }}</td>
                </tr>
                <tr>
                    <td>Total Dibayar</td>
                    <td>{{ $rupiah($requested['final_amount'] ?? 0) }}</td>
                    <td>{{ $rupiah($paymentScheme->final_amount) }}</td>
                </tr>
            </tbody>
        </table>
        @if ($rejectedMonths)
            <div class="muted" style="margin-top: 6px;">Bulan ditolak: {{ $rejectedMonths }}</div>
        @endif
    @endif

    <h2>Ringkasan Jumlah</h2>
    <table>
        <tr><th style="width: 32%;">Pokok Awal</th><td>{{ $rupiah($paymentScheme->original_principal) }}</td></tr>
        <tr><th>Diskon Pokok</th><td>{{ $rupiah($paymentScheme->principal_discount) }} ({{ $percent($paymentScheme->principal_discount, $paymentScheme->original_principal) }})</td></tr>
        <tr><th>Denda Awal</th><td>{{ $rupiah($paymentScheme->original_penalty) }}</td></tr>
        <tr><th>Keringanan Denda</th><td>{{ $rupiah($paymentScheme->penalty_reduction) }}</td></tr>
        <tr><th>Total Dibayar</th><th>{{ $rupiah($paymentScheme->final_amount) }}</th></tr>
    </table>

    @if ($paymentScheme->status === 'approved')
        <h2>Status Pembayaran Skema</h2>
        <table>
            <tr><th style="width: 32%;">Status</th><td>{{ $paymentStatusLabels[$paymentScheme->payment_status] ?? '-' }}</td></tr>
            <tr><th>Sudah Dibayar</th><td>{{ $rupiah($paymentScheme->paid_amount) }}</td></tr>
            <tr><th>Sisa</th><td>{{ $rupiah($paymentScheme->outstanding_amount) }}</td></tr>
        </table>
    @endif

    <h2>Rincian Tagihan</h2>
    <table>
        <thead>
            <tr>
                <th>Periode</th><th class="right">Pokok Awal</th><th class="right">Diskon</th><th class="right">Pokok Akhir</th>
                <th class="right">Denda Awal</th><th class="right">Keringanan</th><th class="right">Denda Akhir</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($paymentScheme->items as $item)
                <tr>
                    <td>{{ $period($item->billing?->year, $item->billing?->month) }}</td>
                    <td class="right">{{ $rupiah($item->original_principal) }}</td>
                    <td class="right">{{ $rupiah($item->principal_discount) }}</td>
                    <td class="right">{{ $rupiah($item->final_principal) }}</td>
                    <td class="right">{{ $rupiah($item->original_penalty) }}</td>
                    <td class="right">{{ $rupiah($item->penalty_reduction) }}</td>
                    <td class="right">{{ $rupiah($item->final_penalty) }}</td>
                    <td>{{ $item->status === 'rejected' ? 'Ditolak Admin' : 'Termasuk' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">Tidak ada tagihan.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="muted" style="margin-top: 16px;">Dicetak {{ now()->format('d/m/Y H:i') }}</p>
@endsection
