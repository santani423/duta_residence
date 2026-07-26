<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use App\Models\CollectorAssignment;
use App\Models\CollectorLocation;
use App\Models\CollectorProfile;
use App\Models\CollectorTarget;
use App\Models\CollectorVisit;
use App\Models\ManagedFile;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\ResidentComplaint;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CollectorAssignmentService;
use App\Services\PenaltyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CollectorController extends Controller
{
    use ApiResponse;

    private const ACCOUNT_STATUSES = ['active', 'inactive', 'leave', 'suspended'];

    private const EMPLOYMENT_STATUSES = ['tetap', 'kontrak', 'harian'];

    public function index(Request $request)
    {
        $query = User::query()
            ->role('collector')
            ->with(['collectorProfile'])
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$value}%")
                ->orWhere('username', 'like', "%{$value}%")
                ->orWhere('email', 'like', "%{$value}%")
                ->orWhereHas('collectorProfile', fn ($p) => $p->where('collector_code', 'like', "%{$value}%"))))
            ->when($request->query('account_status'), fn ($q, $value) => $q->whereHas('collectorProfile', fn ($p) => $p->where('account_status', $value)))
            ->when($request->query('employment_status'), fn ($q, $value) => $q->whereHas('collectorProfile', fn ($p) => $p->where('employment_status', $value)));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateCollector($request);

        $collector = DB::transaction(function () use ($data, $request) {
            $user = User::query()->create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => $data['account_status'] === CollectorProfile::STATUS_ACTIVE,
            ]);
            $user->assignRole('collector');

            $profile = CollectorProfile::query()->create([
                'user_id' => $user->id,
                'collector_code' => ($data['collector_code'] ?? null) ?: $this->generateCollectorCode(),
                'whatsapp_number' => $data['whatsapp_number'] ?? null,
                'address' => $data['address'] ?? null,
                'joined_at' => $data['joined_at'] ?? null,
                'employment_status' => $data['employment_status'] ?? CollectorProfile::EMPLOYMENT_PERMANENT,
                'account_status' => $data['account_status'],
                'working_area_notes' => $data['working_area_notes'] ?? null,
                'admin_notes' => $data['admin_notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            if (! empty($data['initial_monthly_target'])) {
                CollectorTarget::query()->create([
                    'collector_id' => $user->id,
                    'period_type' => CollectorTarget::PERIOD_MONTHLY,
                    'period_start' => now()->startOfMonth()->toDateString(),
                    'target_amount' => $data['initial_monthly_target'],
                    'created_by' => $request->user()->id,
                ]);
            }

            return $user->setRelation('collectorProfile', $profile);
        });

        $auditService->log('collector_created', 'collectors', 'CREATE', $collector, [], $collector->toArray());

        return $this->success($collector->load(['collectorProfile.photos', 'roles']), 'Kolektor berhasil ditambahkan.', 201);
    }

    public function show(Request $request, User $collector, CollectorAssignmentService $assignmentService, PenaltyService $penaltyService)
    {
        $this->assertViewable($request, $collector);
        abort_unless($collector->hasRole('collector'), 404);

        $collector->load(['collectorProfile.photos']);

        $assignments = CollectorAssignment::query()
            ->forCollector($collector->id)
            ->with(['cluster', 'unit.resident', 'resident'])
            ->currentlyEffective()
            ->active()
            ->latest()
            ->get();

        $unitIds = $assignmentService->unitIdsFor($collector);
        $residentIds = $assignmentService->residentIdsFor($collector);

        $outstanding = collect($unitIds)->reduce(function (array $carry, string $unitId) use ($penaltyService) {
            $summary = $penaltyService->calculateUnitOutstanding($unitId);
            $carry['total_outstanding'] += $summary['total_outstanding'];
            $carry['invoice_count'] += $summary['invoice_count'];

            return $carry;
        }, ['total_outstanding' => 0.0, 'invoice_count' => 0]);

        $paymentsCollected = PaymentTransaction::query()
            ->where('created_by', $collector->id)
            ->where('payment_provider', 'loket')
            ->where('status', 'paid')
            ->sum('total');

        $assignmentIds = CollectorAssignment::query()->forCollector($collector->id)->pluck('id');

        return $this->success([
            'collector' => $collector,
            'assignments' => $assignments,
            'summary' => [
                'total_residents' => count($residentIds),
                'total_units' => count($unitIds),
                'total_outstanding' => round($outstanding['total_outstanding'], 2),
                'total_outstanding_invoices' => $outstanding['invoice_count'],
                'total_payments_collected' => (float) $paymentsCollected,
                'total_payment_promises' => PaymentPromise::query()->whereIn('unit_id', $unitIds)->count(),
                'total_visits' => CollectorVisit::query()->where('collector_id', $collector->id)->count(),
                'total_complaints' => ResidentComplaint::query()->where('collector_id', $collector->id)->count(),
            ],
            'recent_visits' => CollectorVisit::query()->where('collector_id', $collector->id)->with('unit.cluster')->latest('visit_date')->limit(10)->get(),
            'recent_payment_promises' => PaymentPromise::query()->whereIn('unit_id', $unitIds)->with('unit.cluster')->latest('promised_date')->limit(10)->get(),
            'recent_complaints' => ResidentComplaint::query()->where('collector_id', $collector->id)->with('unit.cluster')->latest()->limit(10)->get(),
            'latest_location' => CollectorLocation::query()->where('collector_id', $collector->id)->latest('recorded_at')->first(),
            'targets' => CollectorTarget::query()->where('collector_id', $collector->id)->latest('period_start')->limit(6)->get(),
            'assignment_history' => AuditLog::query()
                ->where('entity_type', CollectorAssignment::class)
                ->whereIn('entity_id', $assignmentIds)
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }

    public function update(Request $request, User $collector, AuditService $auditService)
    {
        abort_unless($collector->hasRole('collector'), 404);
        $data = $this->validateCollector($request, $collector);
        $old = $collector->load('collectorProfile')->toArray();

        $collector->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['account_status'] === CollectorProfile::STATUS_ACTIVE,
            ...(! empty($data['password']) ? ['password' => Hash::make($data['password'])] : []),
        ]);

        $collector->collectorProfile()->update([
            'collector_code' => ($data['collector_code'] ?? null) ?: $collector->collectorProfile->collector_code,
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'address' => $data['address'] ?? null,
            'joined_at' => $data['joined_at'] ?? null,
            'employment_status' => $data['employment_status'] ?? CollectorProfile::EMPLOYMENT_PERMANENT,
            'account_status' => $data['account_status'],
            'working_area_notes' => $data['working_area_notes'] ?? null,
            'admin_notes' => $data['admin_notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        $collector->refresh()->load('collectorProfile');
        $auditService->log('collector_updated', 'collectors', 'UPDATE', $collector, $old, $collector->toArray());

        return $this->success($collector->load(['collectorProfile.photos', 'roles']), 'Kolektor berhasil diperbarui.');
    }

    public function destroy(Request $request, User $collector, AuditService $auditService)
    {
        abort_unless($collector->hasRole('collector'), 404);
        $old = $collector->toArray();
        $collector->delete();
        $auditService->log('collector_deleted', 'collectors', 'DELETE', $collector, $old, []);

        return $this->success(null, 'Kolektor berhasil dihapus.');
    }

    public function updateStatus(Request $request, User $collector, AuditService $auditService)
    {
        abort_unless($collector->hasRole('collector'), 404);
        $data = $request->validate([
            'account_status' => ['required', Rule::in(self::ACCOUNT_STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $old = $collector->load('collectorProfile')->toArray();

        $collector->forceFill(['is_active' => $data['account_status'] === CollectorProfile::STATUS_ACTIVE])->save();
        $collector->collectorProfile->forceFill([
            'account_status' => $data['account_status'],
            'admin_notes' => $data['reason']
                ? trim(($collector->collectorProfile->admin_notes ?? '')."\n[".now()->toDateTimeString()."] {$data['reason']}")
                : $collector->collectorProfile->admin_notes,
            'updated_by' => $request->user()->id,
        ])->save();

        $collector->refresh()->load('collectorProfile');
        $auditService->log('collector_status_changed', 'collectors', 'UPDATE_STATUS', $collector, $old, $collector->toArray());

        return $this->success($collector, 'Status kolektor berhasil diperbarui.');
    }

    public function assignmentHistory(Request $request, User $collector)
    {
        $this->assertViewable($request, $collector);
        abort_unless($collector->hasRole('collector'), 404);

        $assignmentIds = CollectorAssignment::query()->forCollector($collector->id)->pluck('id');
        $query = AuditLog::query()->where('entity_type', CollectorAssignment::class)->whereIn('entity_id', $assignmentIds);

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 20)));
    }

    public function uploadPhoto(Request $request, User $collector)
    {
        abort_unless($collector->hasRole('collector'), 404);
        $data = $request->validate(['photo' => ['required', 'file', 'image', 'max:5120']]);

        $stored = $data['photo']->store('collector-photos', 'public');
        $file = ManagedFile::query()->create([
            'original_filename' => $data['photo']->getClientOriginalName(),
            'stored_filename' => basename($stored),
            'path' => $stored,
            'disk' => 'public',
            'mime_type' => $data['photo']->getMimeType(),
            'extension' => $data['photo']->getClientOriginalExtension(),
            'size' => $data['photo']->getSize(),
            'uploaded_by' => $request->user()->id,
            'entity_type' => CollectorProfile::class,
            'entity_id' => $collector->collectorProfile->id,
        ]);

        return $this->success($file, 'Foto profil berhasil diunggah.', 201);
    }

    private function assertViewable(Request $request, User $collector): void
    {
        if ($request->user()->hasRole('collector')) {
            abort_unless($request->user()->id === $collector->id, 403, 'Anda hanya dapat melihat profil Anda sendiri.');

            return;
        }
    }

    private function generateCollectorCode(): string
    {
        $max = CollectorProfile::query()
            ->where('collector_code', 'like', 'COL-%')
            ->get()
            ->map(fn (CollectorProfile $profile) => (int) substr($profile->collector_code, 4))
            ->max() ?? 0;

        return 'COL-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function validateCollector(Request $request, ?User $collector = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($collector?->id)],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('users')->ignore($collector?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [$collector ? 'nullable' : 'required', 'string', 'min:8'],
            'collector_code' => ['nullable', 'string', 'max:20', Rule::unique('collector_profiles')->ignore($collector?->collectorProfile?->id)],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'joined_at' => ['nullable', 'date'],
            'employment_status' => ['nullable', Rule::in(self::EMPLOYMENT_STATUSES)],
            'account_status' => ['required', Rule::in(self::ACCOUNT_STATUSES)],
            'working_area_notes' => ['nullable', 'string'],
            'admin_notes' => ['nullable', 'string'],
            'initial_monthly_target' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
