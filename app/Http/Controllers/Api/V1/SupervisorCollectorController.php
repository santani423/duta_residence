<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CollectorAssignment;
use App\Models\CollectorLocation;
use App\Models\CollectorVisit;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\ResidentComplaint;
use App\Models\User;
use App\Services\CollectorAssignmentService;
use App\Services\PenaltyService;
use App\Services\SupervisorAssignmentService;
use Illuminate\Http\Request;

/**
 * Read-only Collector monitoring surface for Supervisor - reuses CollectorController::show()'s
 * aggregate-data shape and CollectorAssignmentService, scoped down to only the collectors this
 * supervisor's cluster assignments cover (SupervisorAssignmentService::collectorIdsFor()).
 */
class SupervisorCollectorController extends Controller
{
    use ApiResponse;

    public function index(Request $request, SupervisorAssignmentService $scopeService)
    {
        $collectorIds = $scopeService->collectorIdsFor($request->user());

        $query = User::query()
            ->whereIn('id', $collectorIds)
            ->with(['collectorProfile'])
            ->when($request->query('search'), fn ($q, $value) => $q->where('name', 'like', "%{$value}%"))
            ->when($request->query('account_status'), fn ($q, $value) => $q->whereHas('collectorProfile', fn ($p) => $p->where('account_status', $value)));

        $collectors = $query->latest()->paginate($request->integer('per_page', 15));

        $collectors->getCollection()->transform(function (User $collector) {
            $collector->latest_location = CollectorLocation::query()->where('collector_id', $collector->id)->latest('recorded_at')->first();
            $collector->today_visit_count = CollectorVisit::query()->where('collector_id', $collector->id)->whereDate('visit_date', now()->toDateString())->count();
            $collector->active_ptp_count = PaymentPromise::query()->whereIn('unit_id', app(CollectorAssignmentService::class)->unitIdsFor($collector))->where('status', 'pending')->count();

            return $collector;
        });

        return $this->paginated($collectors);
    }

    public function show(Request $request, User $collector, SupervisorAssignmentService $scopeService, CollectorAssignmentService $assignmentService, PenaltyService $penaltyService)
    {
        $scopeService->assertCollectorAssigned($request->user(), $collector->id);
        abort_unless($collector->hasRole('collector'), 404);

        $collector->load(['collectorProfile.photos']);

        $unitIds = $assignmentService->unitIdsFor($collector);
        $residentIds = $assignmentService->residentIdsFor($collector);

        $outstanding = collect($unitIds)->reduce(function (array $carry, string $unitId) use ($penaltyService) {
            $summary = $penaltyService->calculateUnitOutstanding($unitId);
            $carry['total_outstanding'] += $summary['total_outstanding'];
            $carry['invoice_count'] += $summary['invoice_count'];

            return $carry;
        }, ['total_outstanding' => 0.0, 'invoice_count' => 0]);

        return $this->success([
            'collector' => $collector,
            'assignments' => CollectorAssignment::query()->forCollector($collector->id)->with(['cluster', 'unit.resident', 'resident'])->currentlyEffective()->active()->latest()->get(),
            'summary' => [
                'total_residents' => count($residentIds),
                'total_units' => count($unitIds),
                'total_outstanding' => round($outstanding['total_outstanding'], 2),
                'total_outstanding_invoices' => $outstanding['invoice_count'],
                'total_payments_collected' => (float) PaymentTransaction::query()->where('created_by', $collector->id)->where('payment_provider', 'loket')->where('status', 'paid')->sum('total'),
                'total_payment_promises' => PaymentPromise::query()->whereIn('unit_id', $unitIds)->count(),
                'broken_promises' => PaymentPromise::query()->whereIn('unit_id', $unitIds)->where('status', 'broken')->count(),
                'total_visits' => CollectorVisit::query()->where('collector_id', $collector->id)->count(),
                'total_complaints' => ResidentComplaint::query()->where('collector_id', $collector->id)->count(),
            ],
            'recent_visits' => CollectorVisit::query()->where('collector_id', $collector->id)->with('unit.cluster')->latest('visit_date')->limit(10)->get(),
            'recent_payment_promises' => PaymentPromise::query()->whereIn('unit_id', $unitIds)->with('unit.cluster')->latest('promised_date')->limit(10)->get(),
            'recent_complaints' => ResidentComplaint::query()->where('collector_id', $collector->id)->with('unit.cluster')->latest()->limit(10)->get(),
            'latest_location' => CollectorLocation::query()->where('collector_id', $collector->id)->latest('recorded_at')->first(),
        ]);
    }
}
