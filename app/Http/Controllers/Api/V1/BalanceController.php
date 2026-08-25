<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Unit;
use App\Models\UnitDeposit;
use App\Services\AuditService;
use App\Services\UnitBalanceLedgerService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BalanceController extends Controller
{
    use ApiResponse;

    public function ledger(Request $request, Unit $unit)
    {
        $query = UnitDeposit::query()->where('unit_id', $unit->id)->with('creator')->latest('id');

        return $this->paginated($query->paginate($request->integer('per_page', 15)));
    }

    public function unitReconciliation(Unit $unit, UnitBalanceLedgerService $ledgerService)
    {
        return $this->success($ledgerService->reconcile($unit));
    }

    public function adjust(Request $request, Unit $unit, UnitBalanceLedgerService $ledgerService, AuditService $auditService)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'type' => ['required', Rule::in(['credit', 'debit'])],
            'reason' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
        ]);

        $notes = trim($data['reason'].(! empty($data['notes']) ? ' - '.$data['notes'] : ''));
        $meta = ['notes' => $notes, 'created_by' => $request->user()->id];

        $entry = $data['type'] === 'credit'
            ? $ledgerService->credit($unit, UnitDeposit::TYPE_MANUAL_CREDIT, (float) $data['amount'], $meta)
            : $ledgerService->debit($unit, UnitDeposit::TYPE_MANUAL_DEBIT, (float) $data['amount'], $meta);

        $auditService->log('balance_adjustment_created', 'balances', 'ADJUST', $entry, [], $entry->toArray());

        return $this->success($entry, 'Penyesuaian saldo berhasil dicatat.', 201);
    }

    /**
     * One row per unit comparing the cached units.balance ("stored") against a fresh
     * SUM(credits) - SUM(debits) over unit_deposits ("calculated"). Computed on demand -
     * dataset is small enough (unit count, not transaction count) that pre-aggregating in
     * SQL and filtering/paginating in memory is simpler than building dialect-specific
     * HAVING clauses for a derived column.
     */
    public function reconciliation(Request $request)
    {
        $rows = DB::table('units')
            ->leftJoin('unit_deposits', 'unit_deposits.unit_id', '=', 'units.id')
            ->leftJoin('residents', 'residents.id', '=', 'units.resident_id')
            ->select([
                'units.id as unit_id',
                'units.block',
                'units.lot_number',
                'units.balance as stored_balance',
                'residents.name as resident_name',
                DB::raw("COALESCE(SUM(CASE WHEN unit_deposits.direction = 'credit' THEN unit_deposits.amount ELSE 0 END), 0) as total_credits"),
                DB::raw("COALESCE(SUM(CASE WHEN unit_deposits.direction = 'debit' THEN unit_deposits.amount ELSE 0 END), 0) as total_debits"),
                DB::raw('MAX(unit_deposits.created_at) as last_transaction_at'),
            ])
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('units.id', 'like', "%{$value}%")
                ->orWhere('residents.name', 'like', "%{$value}%")))
            ->groupBy('units.id', 'units.block', 'units.lot_number', 'units.balance', 'residents.name')
            ->get()
            ->map(function ($row) {
                $stored = round((float) $row->stored_balance, 2);
                $calculated = round((float) $row->total_credits - (float) $row->total_debits, 2);
                $difference = round($stored - $calculated, 2);

                return [
                    'unit_id' => $row->unit_id,
                    'resident_name' => $row->resident_name,
                    'block' => $row->block,
                    'lot_number' => $row->lot_number,
                    'stored_balance' => $stored,
                    'calculated_balance' => $calculated,
                    'difference' => $difference,
                    'status' => abs($difference) < 0.01 ? 'balanced' : 'mismatch',
                    'is_negative' => $stored < 0,
                    'last_transaction_at' => $row->last_transaction_at,
                ];
            });

        $status = $request->query('status', 'all');
        $rows = match ($status) {
            'balanced' => $rows->where('status', 'balanced')->values(),
            'mismatch' => $rows->where('status', 'mismatch')->values(),
            'negative' => $rows->where('is_negative', true)->values(),
            default => $rows,
        };

        $perPage = $request->integer('per_page', 15);
        $page = $request->integer('page', 1);
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);

        return $this->paginated($paginator);
    }
}
