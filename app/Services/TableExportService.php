<?php

namespace App\Services;

use App\Http\Controllers\Api\V1\BalanceController;
use App\Models\Billing;
use App\Models\Cluster;
use App\Models\Installment;
use App\Models\PaymentScheme;
use App\Models\Receipt;
use App\Models\Reversal;
use App\Models\Unit;
use App\Models\UnitDeposit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the printable version of the payment/billing tables that have no PDF of their own. Each
 * dataset returns the same shape - title, filter lines, columns, rows of plain strings and optional
 * footer rows - so one Blade view renders them all. Filters are the ones the on-screen list uses, so
 * the PDF shows exactly the rows the user was looking at (not just the page they were on).
 */
class TableExportService
{
    /** DomPDF needs roughly 0.2MB per table row; the same cap the other list PDFs use. */
    public const MAX_ROWS = 500;

    private const BILLING_STATUS = ['01' => 'Belum Bayar', '02' => 'Lunas', '03' => 'Sebagian', '04' => 'Dibatalkan'];

    private const SCHEME_STATUS = ['pending' => 'Menunggu Admin', 'approved' => 'Disetujui', 'rejected' => 'Ditolak', 'cancelled' => 'Dibatalkan'];

    private const PAYMENT_STATUS = ['unpaid' => 'Belum dibayar', 'partial' => 'Dibayar sebagian', 'paid' => 'Lunas'];

    /** dataset => extra permission needed on top of documents.generate. */
    private const PERMISSIONS = [
        'payment-schemes' => 'payment-schemes.view',
        'reversals' => 'reversals.view',
        'installments' => 'installments.view',
        'receivables' => 'reports.view',
        'balance-reconciliation' => 'balances.view',
        'balance-ledger' => 'balances.view',
        'unit-outstanding' => 'billings.view',
        'unit-billing-history' => 'billings.view',
        'report-monthly' => 'reports.view',
        'report-daily' => 'reports.view',
        'report-cashier' => 'reports.view',
    ];

    public function __construct(
        private readonly PenaltyService $penaltyService,
        private readonly PaymentSchemeService $schemeService,
        private readonly BalanceController $balanceController,
    ) {}

    public function permissionFor(string $dataset): ?string
    {
        return self::PERMISSIONS[$dataset] ?? null;
    }

    public function build(string $dataset, Request $request): array
    {
        $table = match ($dataset) {
            'payment-schemes' => $this->paymentSchemes($request),
            'reversals' => $this->reversals($request),
            'installments' => $this->installments($request),
            'receivables' => $this->receivables($request),
            'balance-reconciliation' => $this->balanceReconciliation($request),
            'balance-ledger' => $this->balanceLedger($request),
            'unit-outstanding' => $this->unitOutstanding($request),
            'unit-billing-history' => $this->unitBillingHistory($request),
            'report-monthly' => $this->reportMonthly($request),
            'report-daily' => $this->reportDaily($request),
            'report-cashier' => $this->reportCashier($request),
        };

        $table['meta'][] = 'Dicetak '.now()->format('d-m-Y H:i').' oleh '.($request->user()?->name ?? '-');

        return $table;
    }

    // ---------------------------------------------------------------- datasets

    private function paymentSchemes(Request $request): array
    {
        $query = PaymentScheme::query()
            ->with(['unit.cluster', 'unit.resident', 'submitter', 'decider', 'items.billing.unit'])
            ->filter($request->only(['unit_id', 'status', 'search']))
            ->latest();
        $this->guardRows($query, 'skema pembayaran');
        $schemes = $query->get();
        $this->schemeService->annotate($schemes, $request->user());

        $approved = $schemes->where('status', PaymentScheme::STATUS_APPROVED);
        $rows = $schemes->values()->map(function (PaymentScheme $scheme, int $i) {
            $active = $scheme->items->where('status', 'included')->count();
            $rejected = $scheme->items->count() - $active;
            $status = self::SCHEME_STATUS[$scheme->status] ?? $scheme->status;

            if ($scheme->status === PaymentScheme::STATUS_PENDING && $scheme->exceeds_admin_limit) {
                $status .= ' (di atas batas Admin)';
            }

            if ($scheme->adjusted_at) {
                $status .= ' - diubah Admin';
            }

            return [
                $i + 1,
                $scheme->unit_id,
                $scheme->unit?->resident?->name ?? '-',
                $active.' bulan'.($rejected ? " ({$rejected} ditolak)" : ''),
                $this->money($scheme->original_principal),
                $this->money($scheme->principal_discount).' ('.$this->percent($scheme->principal_discount, $scheme->original_principal).')',
                $this->money($scheme->penalty_reduction),
                $this->money($scheme->final_amount),
                $status,
                $scheme->payment_status ? (self::PAYMENT_STATUS[$scheme->payment_status] ?? '-').($scheme->payment_status === 'paid' ? '' : ' (sisa '.$this->money($scheme->outstanding_amount).')') : '-',
                ($scheme->submitter?->name ?? '-').' - '.$this->dateTime($scheme->submitted_at),
                $scheme->decided_at ? ($scheme->decider?->name ?? '-').' - '.$this->dateTime($scheme->decided_at) : '-',
            ];
        })->all();

        return [
            'title' => 'Skema Pembayaran',
            'filename' => 'skema-pembayaran.pdf',
            'meta' => $this->filterLines([
                'Status' => $request->query('status') ? (self::SCHEME_STATUS[$request->query('status')] ?? $request->query('status')) : null,
                'Unit' => $request->query('unit_id'),
                'Pencarian' => $request->query('search'),
            ], $schemes->count(), 'skema'),
            'columns' => $this->columns([
                'No.', 'Unit', 'Penghuni', 'Tagihan', ['Pokok Awal', 'right'], ['Diskon', 'right'], ['Keringanan Denda', 'right'],
                ['Total Dibayar', 'right'], 'Status', 'Pembayaran', 'Diajukan', 'Diputuskan',
            ]),
            'rows' => $rows,
            'footer' => [[
                ['text' => 'Total skema disetujui ('.$approved->count().' skema)', 'colspan' => 4],
                ['text' => $this->money($approved->sum('original_principal')), 'align' => 'right'],
                ['text' => $this->money($approved->sum('principal_discount')), 'align' => 'right'],
                ['text' => $this->money($approved->sum('penalty_reduction')), 'align' => 'right'],
                ['text' => $this->money($approved->sum('final_amount')), 'align' => 'right'],
                ['text' => '', 'colspan' => 4],
            ]],
        ];
    }

    private function reversals(Request $request): array
    {
        $query = Reversal::query()->with('receipt.unit.resident')
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->latest();
        $this->guardRows($query, 'reversal');
        $reversals = $query->get();

        return [
            'title' => 'Reversal Pembayaran',
            'filename' => 'reversal-pembayaran.pdf',
            'meta' => $this->filterLines(['Status' => $request->query('status')], $reversals->count(), 'reversal'),
            'columns' => $this->columns(['No.', 'Nomor Kuitansi', 'Penghuni', 'Unit', 'Alasan', 'Status', 'Diajukan', 'Diputuskan', 'Catatan Peninjau']),
            'rows' => $reversals->values()->map(fn (Reversal $reversal, int $i) => [
                $i + 1,
                $reversal->receipt_number,
                $reversal->receipt?->unit?->resident?->name ?? $reversal->receipt?->resident_name ?? '-',
                $reversal->receipt?->unit_id ?? '-',
                $reversal->reason,
                $reversal->status,
                $this->dateTime($reversal->submitted_at),
                $reversal->reviewed_at ? $this->dateTime($reversal->reviewed_at) : '-',
                $reversal->review_notes ?: '-',
            ])->all(),
            'footer' => [],
        ];
    }

    private function installments(Request $request): array
    {
        $query = Installment::query()->with(['unit.cluster', 'unit.resident'])
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->latest('payment_date');
        $this->guardRows($query, 'cicilan');
        $installments = $query->get();

        return [
            'title' => 'Cicilan',
            'filename' => 'cicilan.pdf',
            'meta' => $this->filterLines(['Unit' => $request->query('unit_id')], $installments->count(), 'cicilan'),
            'columns' => $this->columns(['No.', 'Tanggal', 'Unit', 'Penghuni', 'Cluster', ['Nominal', 'right'], 'Alokasi', 'Catatan']),
            'rows' => $installments->values()->map(fn (Installment $installment, int $i) => [
                $i + 1,
                $installment->payment_date?->format('d-m-Y') ?? '-',
                $installment->unit_id,
                $installment->unit?->resident?->name ?? '-',
                $installment->unit?->cluster?->name ?? '-',
                $this->money($installment->amount),
                $installment->allocated_to ?: '-',
                $installment->notes ?: '-',
            ])->all(),
            'footer' => [[
                ['text' => 'Total ('.$installments->count().' cicilan)', 'colspan' => 5],
                ['text' => $this->money($installments->sum('amount')), 'align' => 'right'],
                ['text' => '', 'colspan' => 2],
            ]],
        ];
    }

    private function receivables(Request $request): array
    {
        $query = Billing::query()->with(['unit.cluster', 'unit.resident'])
            ->when($request->query('unit_id'), fn ($q, $value) => $q->where('unit_id', $value))
            ->when($request->query('cluster_id'), fn ($q, $value) => $q->whereHas('unit', fn ($inner) => $inner->where('cluster_id', $value)))
            ->when($request->query('status_id'), fn ($q, $value) => $q->where('status_id', $value), fn ($q) => $q->outstanding())
            ->orderBy('year')->orderBy('month');
        $this->guardRows($query, 'piutang');
        $billings = $query->get();
        $now = now();
        $details = $billings->map(fn (Billing $billing) => $this->penaltyService->calculateInvoiceTotal($billing, $now));

        return [
            'title' => 'Piutang',
            'filename' => 'piutang.pdf',
            'meta' => $this->filterLines([
                'Unit' => $request->query('unit_id'),
                'Cluster' => $request->query('cluster_id') ? (Cluster::query()->find($request->query('cluster_id'))?->name ?? $request->query('cluster_id')) : null,
                'Status' => $request->query('status_id') ? (self::BILLING_STATUS[$request->query('status_id')] ?? null) : 'Belum lunas',
            ], $billings->count(), 'tagihan'),
            'columns' => $this->columns([
                'No.', 'Penghuni', 'Unit', 'Cluster', 'Periode', ['Pokok', 'right'], 'Umur', ['Denda', 'right'], ['Total', 'right'], ['Sisa Tagihan', 'right'], 'Status',
            ]),
            'rows' => $billings->values()->map(fn (Billing $billing, int $i) => [
                $i + 1,
                $billing->unit?->resident?->name ?? '-',
                $billing->unit_id,
                $billing->unit?->cluster?->name ?? '-',
                $this->period($billing->year, $billing->month),
                $this->money($details[$i]['principal_amount']),
                $details[$i]['overdue_months'].' bulan',
                $this->money($details[$i]['penalty_amount']),
                $this->money($details[$i]['total_amount']),
                $this->money($details[$i]['total_outstanding']),
                self::BILLING_STATUS[$billing->status_id] ?? $billing->status_id,
            ])->all(),
            'footer' => [[
                ['text' => 'Total ('.$billings->count().' tagihan)', 'colspan' => 5],
                ['text' => $this->money($details->sum('principal_amount')), 'align' => 'right'],
                ['text' => ''],
                ['text' => $this->money($details->sum('penalty_amount')), 'align' => 'right'],
                ['text' => $this->money($details->sum('total_amount')), 'align' => 'right'],
                ['text' => $this->money($details->sum('total_outstanding')), 'align' => 'right'],
                ['text' => ''],
            ]],
        ];
    }

    private function balanceReconciliation(Request $request): array
    {
        $rows = $this->balanceController->reconciliationRows($request);
        $this->guardCount($rows->count(), 'unit');
        $status = $request->query('status', 'all');

        return [
            'title' => 'Rekonsiliasi Saldo Unit',
            'filename' => 'rekonsiliasi-saldo.pdf',
            'meta' => $this->filterLines([
                'Status' => $status !== 'all' ? ['balanced' => 'Sesuai', 'mismatch' => 'Selisih', 'negative' => 'Saldo negatif'][$status] ?? $status : null,
                'Pencarian' => $request->query('search'),
            ], $rows->count(), 'unit'),
            'columns' => $this->columns(['No.', 'Unit', 'Penghuni', 'Alamat', ['Saldo Tercatat', 'right'], ['Saldo Terhitung', 'right'], ['Selisih', 'right'], 'Status', 'Transaksi Terakhir']),
            'rows' => $rows->values()->map(fn (array $row, int $i) => [
                $i + 1,
                $row['unit_id'],
                $row['resident_name'] ?? '-',
                trim(($row['block'] ?? '').'/'.($row['lot_number'] ?? ''), '/'),
                $this->money($row['stored_balance']),
                $this->money($row['calculated_balance']),
                $this->money($row['difference']),
                $row['status'] === 'balanced' ? 'Sesuai' : 'Selisih',
                $row['last_transaction_at'] ? $this->dateTime($row['last_transaction_at']) : '-',
            ])->all(),
            'footer' => [[
                ['text' => 'Total ('.$rows->count().' unit, '.$rows->where('status', 'mismatch')->count().' selisih)', 'colspan' => 4],
                ['text' => $this->money($rows->sum('stored_balance')), 'align' => 'right'],
                ['text' => $this->money($rows->sum('calculated_balance')), 'align' => 'right'],
                ['text' => $this->money($rows->sum('difference')), 'align' => 'right'],
                ['text' => '', 'colspan' => 2],
            ]],
        ];
    }

    private function balanceLedger(Request $request): array
    {
        $unit = $this->requiredUnit($request);
        $query = UnitDeposit::query()->where('unit_id', $unit->id)->with('creator')->latest('id');
        $this->guardRows($query, 'mutasi saldo');
        $entries = $query->get();

        return [
            'title' => 'Ledger Saldo Unit '.$unit->id,
            'filename' => "ledger-saldo-{$unit->id}.pdf",
            'meta' => [
                'Unit '.$unit->id.' - '.($unit->resident?->name ?? '-').' - Saldo saat ini '.$this->money($unit->balance),
                $entries->count().' mutasi',
            ],
            'columns' => $this->columns(['Tanggal', 'Tipe', ['Kredit', 'right'], ['Debit', 'right'], ['Saldo Sebelum', 'right'], ['Saldo Sesudah', 'right'], 'Referensi', 'Keterangan', 'Oleh']),
            'rows' => $entries->map(fn (UnitDeposit $entry) => [
                $this->dateTime($entry->created_at),
                $entry->type,
                $entry->direction === 'credit' ? $this->money($entry->amount) : '-',
                $entry->direction === 'debit' ? $this->money($entry->amount) : '-',
                $this->money($entry->balance_before),
                $this->money($entry->balance_after),
                $entry->receipt_number ?: ($entry->reference_id ?: '-'),
                $entry->notes ?: '-',
                $entry->creator?->name ?? '-',
            ])->all(),
            'footer' => [[
                ['text' => 'Total mutasi', 'colspan' => 2],
                ['text' => $this->money($entries->where('direction', 'credit')->sum('amount')), 'align' => 'right'],
                ['text' => $this->money($entries->where('direction', 'debit')->sum('amount')), 'align' => 'right'],
                ['text' => '', 'colspan' => 5],
            ]],
        ];
    }

    /** The unit's open bills as the loket sees them when taking payment (with penalty and scheme). */
    private function unitOutstanding(Request $request): array
    {
        return $this->unitBillingTable($request, outstandingOnly: true);
    }

    /** Every approved bill of the unit - paid, partial, unpaid and cancelled - not just the open ones. */
    private function unitBillingHistory(Request $request): array
    {
        return $this->unitBillingTable($request, outstandingOnly: false);
    }

    private function unitBillingTable(Request $request, bool $outstandingOnly): array
    {
        $unit = $this->requiredUnit($request);
        $query = Billing::query()->with('unit')->where('unit_id', $unit->id)->approved()
            ->when($outstandingOnly, fn ($q) => $q->outstanding())
            ->when($request->query('date_from'), fn ($q, $value) => $q->whereRaw('(year * 100 + month) >= ?', [$this->yearMonth($value)]))
            ->when($request->query('date_to'), fn ($q, $value) => $q->whereRaw('(year * 100 + month) <= ?', [$this->yearMonth($value)]))
            ->orderBy('year')->orderBy('month');
        $this->guardRows($query, 'tagihan');
        $billings = $query->get();
        $details = $billings->map(fn (Billing $billing) => $this->penaltyService->calculateInvoiceTotal($billing));

        return [
            'title' => ($outstandingOnly ? 'Tagihan Belum Lunas Unit ' : 'Riwayat Tagihan Unit ').$unit->id,
            'filename' => ($outstandingOnly ? 'tagihan-' : 'riwayat-tagihan-').$unit->id.'.pdf',
            'meta' => [
                'Unit '.$unit->id.' - '.($unit->resident?->name ?? '-').' - '.($unit->cluster?->name ?? '').' '.($unit->block ?? '').'/'.($unit->lot_number ?? ''),
                $billings->count().' tagihan',
            ],
            'columns' => $this->columns(['Periode', 'Jatuh Tempo', ['Nominal', 'right'], ['Denda', 'right'], ['Tagihan', 'right'], ['Terbayar', 'right'], ['Sisa Tagihan', 'right'], 'Status', 'Skema']),
            'rows' => $billings->values()->map(fn (Billing $billing, int $i) => [
                $this->period($billing->year, $billing->month),
                $details[$i]['due_date'] ? date('d-m-Y', strtotime($details[$i]['due_date'])) : '-',
                $this->money($details[$i]['principal_amount']),
                $this->money($details[$i]['penalty_amount']),
                $this->money($details[$i]['total_amount']),
                $this->money($details[$i]['total_paid']),
                $this->money($details[$i]['total_outstanding']),
                self::BILLING_STATUS[$billing->status_id] ?? $billing->status_id,
                $billing->payment_scheme_id ? 'Skema #'.$billing->payment_scheme_id : '-',
            ])->all(),
            'footer' => [[
                ['text' => $outstandingOnly ? 'Total sisa tagihan' : 'Total ('.$billings->count().' tagihan)', 'colspan' => 2],
                ['text' => $this->money($details->sum('principal_amount')), 'align' => 'right'],
                ['text' => $this->money($details->sum('penalty_amount')), 'align' => 'right'],
                ['text' => $this->money($details->sum('total_amount')), 'align' => 'right'],
                ['text' => $this->money($details->sum('total_paid')), 'align' => 'right'],
                ['text' => $this->money($details->sum('total_outstanding')), 'align' => 'right'],
                ['text' => '', 'colspan' => 2],
            ]],
        ];
    }

    private function reportMonthly(Request $request): array
    {
        $year = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);
        $data = Billing::query()
            ->select('units.cluster_id', DB::raw('COUNT(*) as billing_count'), DB::raw('SUM(amount) as total_billing'), DB::raw('SUM(billings.principal_paid + billings.penalty_paid) as total_paid'))
            ->join('units', 'units.id', '=', 'billings.unit_id')
            ->where('year', $year)->where('month', $month)
            ->groupBy('units.cluster_id')->get();
        $names = Cluster::query()->whereIn('id', $data->pluck('cluster_id'))->pluck('name', 'id');

        return [
            'title' => 'Rekap Bulanan per Cluster',
            'filename' => sprintf('rekap-bulanan-%04d-%02d.pdf', $year, $month),
            'meta' => ['Periode '.$this->period($year, $month)],
            'columns' => $this->columns(['Cluster', ['Jumlah Tagihan', 'right'], ['Total Tagihan', 'right'], ['Terbayar', 'right']]),
            'rows' => $data->map(fn ($row) => [
                $names[$row->cluster_id] ?? $row->cluster_id,
                $row->billing_count,
                $this->money($row->total_billing),
                $this->money($row->total_paid),
            ])->all(),
            'footer' => [[
                ['text' => 'Total'],
                ['text' => (string) $data->sum('billing_count'), 'align' => 'right'],
                ['text' => $this->money($data->sum('total_billing')), 'align' => 'right'],
                ['text' => $this->money($data->sum('total_paid')), 'align' => 'right'],
            ]],
        ];
    }

    private function reportDaily(Request $request): array
    {
        $date = $request->query('date', today()->toDateString());
        $query = Receipt::query()->with('unit.cluster')->whereDate('transaction_date', $date)->latest('transaction_date');
        $this->guardRows($query, 'kuitansi');
        $receipts = $query->get();

        return [
            'title' => 'Penerimaan Harian Loket',
            'filename' => "penerimaan-harian-{$date}.pdf",
            'meta' => ['Tanggal '.date('d-m-Y', strtotime($date)), $receipts->count().' transaksi'],
            'columns' => $this->columns(['No.', 'Nomor Kuitansi', 'Waktu', 'Penghuni', 'Unit', 'Periode', ['Total', 'right'], 'Kasir']),
            'rows' => $receipts->values()->map(fn (Receipt $receipt, int $i) => [
                $i + 1,
                $receipt->number,
                $receipt->transaction_date?->format('H:i') ?? '-',
                $receipt->resident_name,
                $receipt->unit_id,
                $receipt->billing_periods,
                $this->money($receipt->grand_total),
                $receipt->cashier_name ?: '-',
            ])->all(),
            'footer' => [[
                ['text' => 'Total ('.$receipts->count().' transaksi)', 'colspan' => 6],
                ['text' => $this->money($receipts->sum('grand_total')), 'align' => 'right'],
                ['text' => ''],
            ]],
        ];
    }

    private function reportCashier(Request $request): array
    {
        $date = $request->query('date', today()->toDateString());
        $data = Receipt::query()
            ->select('cashier_name', DB::raw('COUNT(*) as transaction_count'), DB::raw('SUM(grand_total) as grand_total'))
            ->whereDate('transaction_date', $date)->groupBy('cashier_name')->get();

        return [
            'title' => 'Rekap Kasir Harian',
            'filename' => "rekap-kasir-{$date}.pdf",
            'meta' => ['Tanggal '.date('d-m-Y', strtotime($date))],
            'columns' => $this->columns(['Kasir', ['Transaksi', 'right'], ['Total', 'right']]),
            'rows' => $data->map(fn ($row) => [$row->cashier_name ?: '-', $row->transaction_count, $this->money($row->grand_total)])->all(),
            'footer' => [[
                ['text' => 'Total'],
                ['text' => (string) $data->sum('transaction_count'), 'align' => 'right'],
                ['text' => $this->money($data->sum('grand_total')), 'align' => 'right'],
            ]],
        ];
    }

    // ---------------------------------------------------------------- helpers

    private function requiredUnit(Request $request): Unit
    {
        $data = $request->validate(['unit_id' => ['required', 'exists:units,id']]);

        return Unit::query()->with(['cluster', 'resident'])->findOrFail($data['unit_id']);
    }

    /** @param  array<int, string|array{0: string, 1: string}>  $labels */
    private function columns(array $labels): array
    {
        return array_map(fn ($label) => is_array($label) ? ['label' => $label[0], 'align' => $label[1]] : ['label' => $label, 'align' => 'left'], $labels);
    }

    /** @param  array<string, ?string>  $filters */
    private function filterLines(array $filters, int $count, string $noun): array
    {
        $active = collect($filters)->filter(fn ($value) => filled($value))->map(fn ($value, $label) => "{$label}: {$value}")->values();

        return [$active->isEmpty() ? 'Semua data' : 'Filter - '.$active->implode(', '), "{$count} {$noun}"];
    }

    private function guardRows($query, string $noun): void
    {
        $this->guardCount((clone $query)->reorder()->count(), $noun);
    }

    private function guardCount(int $total, string $noun): void
    {
        abort_if(
            $total > self::MAX_ROWS,
            422,
            "Terlalu banyak {$noun} untuk dicetak ({$total} baris, maksimal ".self::MAX_ROWS.'). Persempit filter (status, unit, dsb.) lalu coba lagi.'
        );

        // Enough for the row cap above; only ever raises the limit, never lowers it.
        $limit = ini_get('memory_limit');

        if ($limit !== '-1' && (int) $limit > 0 && $this->bytes($limit) < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }
    }

    private function bytes(string $raw): int
    {
        $value = (int) $raw;

        return match (strtolower(substr(trim($raw), -1))) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function money(float|int|string|null $amount): string
    {
        return 'Rp '.number_format((float) $amount, 0, ',', '.');
    }

    private function percent(float|int|string|null $part, float|int|string|null $whole): string
    {
        $base = (float) $whole;

        return rtrim(rtrim(number_format($base > 0 ? ((float) $part / $base) * 100 : 0, 2, ',', ''), '0'), ',').'%';
    }

    private function dateTime($value): string
    {
        return $value ? \Illuminate\Support\Carbon::parse($value)->format('d-m-Y H:i') : '-';
    }

    private function period(int|string $year, int|string $month): string
    {
        return sprintf('%02d-%04d', (int) $month, (int) $year);
    }

    private function yearMonth(string $value): int
    {
        [$year, $month] = array_map('intval', explode('-', $value));

        return $year * 100 + $month;
    }
}
