<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Billing;
use App\Models\CollectorVisit;
use App\Models\MaintenanceRequest;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\Resident;
use App\Models\ResidentComplaint;
use App\Models\ResidentDocument;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ResidentDetailService
{
    public function unitIds(Resident $resident, ?string $unitId = null): array
    {
        if ($unitId) {
            abort_unless($resident->units()->where('id', $unitId)->exists(), 404, 'Unit tidak ditemukan untuk penghuni ini.');

            return [$unitId];
        }

        return $resident->units()->pluck('id')->all();
    }

    public function summary(array $unitIds): array
    {
        $billings = Billing::query()->whereIn('unit_id', $unitIds)->get();
        $unpaid = $billings->where('status_id', '01');
        $overdue = $unpaid->filter(fn (Billing $billing) => now()->greaterThan($this->dueDate($billing)));
        $dueSoon = $unpaid->filter(function (Billing $billing) {
            $due = $this->dueDate($billing);

            return now()->lessThanOrEqualTo($due) && now()->diffInDays($due) <= 14;
        });

        $activeTotal = $unpaid->sum(fn (Billing $billing) => $this->billingTotal($billing));
        $overdueTotal = $overdue->sum(fn (Billing $billing) => $this->billingTotal($billing));
        $penaltyTotal = $unpaid->sum(fn (Billing $billing) => (float) $billing->penalty);
        $paidTotal = (float) PaymentTransaction::query()->whereIn('unit_id', $unitIds)->where('status', 'paid')->sum('total');
        $waitingVerification = PaymentTransaction::query()->whereIn('unit_id', $unitIds)->where('status', 'waiting_verification')->count();

        return [
            'active_billing_total' => $activeTotal,
            'overdue_total' => $overdueTotal,
            'penalty_total' => $penaltyTotal,
            'paid_total' => $paidTotal,
            'outstanding_total' => $activeTotal,
            'due_soon_count' => $dueSoon->count(),
            'due_soon_total' => $dueSoon->sum(fn (Billing $billing) => $this->billingTotal($billing)),
            'waiting_verification_count' => $waitingVerification,
            'financial_status' => $this->financialStatus($overdue->count(), $unpaid->count()),
        ];
    }

    public function dueDate(Billing $billing)
    {
        $day = min((int) config('grandduta.notification.penalty_day', 20), 28);

        return now()->setDate((int) $billing->year, (int) $billing->month, $day)->startOfDay();
    }

    public function billingTotal(Billing $billing): float
    {
        return (float) $billing->amount + (float) $billing->penalty - (float) $billing->discount;
    }

    private function financialStatus(int $overdueCount, int $unpaidCount): string
    {
        if ($overdueCount > 0) {
            return 'menunggak';
        }

        if ($unpaidCount > 0) {
            return 'ada_tagihan_berjalan';
        }

        return 'lancar';
    }

    public function activity(Resident $resident, array $unitIds): Builder
    {
        $billingIds = Billing::query()->whereIn('unit_id', $unitIds)->pluck('id')->all();
        $paymentIds = PaymentTransaction::query()->whereIn('unit_id', $unitIds)->pluck('id')->all();
        $complaintIds = ResidentComplaint::query()->whereIn('unit_id', $unitIds)->pluck('id')->all();
        $maintenanceIds = MaintenanceRequest::query()->whereIn('unit_id', $unitIds)->pluck('id')->all();
        $visitIds = CollectorVisit::query()->whereIn('unit_id', $unitIds)->pluck('id')->all();
        $promiseIds = PaymentPromise::query()->whereIn('unit_id', $unitIds)->pluck('id')->all();
        $documentIds = ResidentDocument::query()
            ->where(fn (Builder $q) => $q->where('documentable_type', Resident::class)->where('documentable_id', $resident->id))
            ->orWhere(fn (Builder $q) => $q->where('documentable_type', Unit::class)->whereIn('documentable_id', $unitIds))
            ->pluck('id')->all();

        return AuditLog::query()->where(function (Builder $query) use ($resident, $unitIds, $billingIds, $paymentIds, $complaintIds, $maintenanceIds, $visitIds, $promiseIds, $documentIds) {
            $query->where(fn (Builder $q) => $q->where('entity_type', Resident::class)->where('entity_id', $resident->id))
                ->orWhere(fn (Builder $q) => $q->where('entity_type', Unit::class)->whereIn('entity_id', $unitIds))
                ->orWhere(fn (Builder $q) => $q->where('entity_type', Billing::class)->whereIn('entity_id', $billingIds))
                ->orWhere(fn (Builder $q) => $q->where('entity_type', PaymentTransaction::class)->whereIn('entity_id', $paymentIds))
                ->orWhere(fn (Builder $q) => $q->where('entity_type', ResidentComplaint::class)->whereIn('entity_id', $complaintIds))
                ->orWhere(fn (Builder $q) => $q->where('entity_type', MaintenanceRequest::class)->whereIn('entity_id', $maintenanceIds))
                ->orWhere(fn (Builder $q) => $q->where('entity_type', CollectorVisit::class)->whereIn('entity_id', $visitIds))
                ->orWhere(fn (Builder $q) => $q->where('entity_type', PaymentPromise::class)->whereIn('entity_id', $promiseIds))
                ->orWhere(fn (Builder $q) => $q->where('entity_type', ResidentDocument::class)->whereIn('entity_id', $documentIds));
        });
    }
}
