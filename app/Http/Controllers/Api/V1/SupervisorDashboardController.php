<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ApprovalRequest;
use App\Models\CollectorLocation;
use App\Models\CollectorProfile;
use App\Models\CollectorVisit;
use App\Models\EmergencyAlert;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\ResidentComplaint;
use App\Models\SupervisorNotification;
use App\Services\PenaltyService;
use App\Services\SupervisorAssignmentService;
use Illuminate\Http\Request;

/**
 * KPI aggregation for the Supervisor dashboard (spec B) - reuses the same
 * "one payload feeds every widget" shape as ReportController::dashboard(), scoped through
 * SupervisorAssignmentService so every figure only reflects this supervisor's own clusters.
 */
class SupervisorDashboardController extends Controller
{
    use ApiResponse;

    public function summary(Request $request, SupervisorAssignmentService $scopeService, PenaltyService $penaltyService)
    {
        $user = $request->user();
        $collectorIds = $scopeService->collectorIdsFor($user);
        $unitIds = $scopeService->unitIdsFor($user);

        $profiles = CollectorProfile::query()->whereIn('user_id', $collectorIds)->get();
        $activeCollectorIds = $profiles->where('account_status', 'active')->pluck('user_id');

        $collectorsOnDuty = CollectorLocation::query()->whereIn('collector_id', $activeCollectorIds)
            ->whereDate('recorded_at', now()->toDateString())->distinct('collector_id')->count('collector_id');

        $totalOutstanding = 0.0;
        $unpaidUnitCount = 0;
        foreach ($unitIds as $unitId) {
            $summary = $penaltyService->calculateUnitOutstanding($unitId);
            if ($summary['total_outstanding'] > 0.01) {
                $unpaidUnitCount++;
                $totalOutstanding += $summary['total_outstanding'];
            }
        }

        return $this->success([
            'collectors' => [
                'active' => $profiles->where('account_status', 'active')->count(),
                'inactive' => $profiles->whereIn('account_status', ['inactive', 'leave', 'suspended'])->count(),
                'on_duty_today' => $collectorsOnDuty,
                'not_sending_location' => max(0, $activeCollectorIds->count() - $collectorsOnDuty),
            ],
            'billing' => [
                'target_residents' => count($unitIds),
                'unpaid_units' => $unpaidUnitCount,
                'total_receivables' => round($totalOutstanding, 2),
            ],
            'payments' => [
                'successful_today' => PaymentTransaction::query()->whereIn('created_by', $collectorIds)->where('status', 'paid')->whereDate('paid_at', now()->toDateString())->count(),
                'on_site_today' => PaymentTransaction::query()->whereIn('created_by', $collectorIds)->where('payment_provider', 'loket')->where('status', 'paid')->whereDate('paid_at', now()->toDateString())->count(),
            ],
            'promises' => [
                'active_ptp' => PaymentPromise::query()->whereIn('unit_id', $unitIds)->where('status', 'pending')->count(),
                'broken_promise' => PaymentPromise::query()->whereIn('unit_id', $unitIds)->where('status', 'broken')->count(),
            ],
            'complaints' => ResidentComplaint::query()->whereIn('collector_id', $collectorIds)->where('status', '!=', 'closed')->count(),
            'active_emergencies' => EmergencyAlert::query()->whereIn('unit_id', $unitIds)->where('status', 'active')->count(),
            'visits_today' => CollectorVisit::query()->whereIn('collector_id', $collectorIds)->whereDate('visit_date', now()->toDateString())->count(),
            'pending_approvals' => ApprovalRequest::query()->pending()->where(fn ($q) => $q->whereNull('related_collector_id')->orWhereIn('related_collector_id', $collectorIds))->count(),
            'unhandled_notifications' => SupervisorNotification::query()->unhandled()->where(fn ($q) => $q->whereNull('related_collector_id')->orWhereIn('related_collector_id', $collectorIds))->count(),
        ]);
    }
}
