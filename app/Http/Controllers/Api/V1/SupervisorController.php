<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\ManagedFile;
use App\Models\SupervisorAssignment;
use App\Models\SupervisorNotification;
use App\Models\SupervisorProfile;
use App\Models\User;
use App\Services\AuditService;
use App\Services\SupervisorAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class SupervisorController extends Controller
{
    use ApiResponse;

    private const ACCOUNT_STATUSES = ['active', 'inactive', 'leave', 'suspended'];

    private const EMPLOYMENT_STATUSES = ['tetap', 'kontrak', 'harian'];

    public function index(Request $request)
    {
        $query = User::query()
            ->role('supervisor')
            ->with(['supervisorProfile'])
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$value}%")
                ->orWhere('username', 'like', "%{$value}%")
                ->orWhere('email', 'like', "%{$value}%")
                ->orWhereHas('supervisorProfile', fn ($p) => $p->where('supervisor_code', 'like', "%{$value}%"))))
            ->when($request->query('account_status'), fn ($q, $value) => $q->whereHas('supervisorProfile', fn ($p) => $p->where('account_status', $value)));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request, AuditService $auditService)
    {
        $data = $this->validateSupervisor($request);

        $supervisor = DB::transaction(function () use ($data, $request) {
            $user = User::query()->create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'is_active' => $data['account_status'] === SupervisorProfile::STATUS_ACTIVE,
            ]);
            $user->assignRole('supervisor');

            $profile = SupervisorProfile::query()->create([
                'user_id' => $user->id,
                'supervisor_code' => ($data['supervisor_code'] ?? null) ?: $this->generateSupervisorCode(),
                'whatsapp_number' => $data['whatsapp_number'] ?? null,
                'address' => $data['address'] ?? null,
                'joined_at' => $data['joined_at'] ?? null,
                'employment_status' => $data['employment_status'] ?? SupervisorProfile::EMPLOYMENT_PERMANENT,
                'account_status' => $data['account_status'],
                'admin_notes' => $data['admin_notes'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return $user->setRelation('supervisorProfile', $profile);
        });

        $auditService->log('supervisor_created', 'supervisors', 'CREATE', $supervisor, [], $supervisor->toArray());

        return $this->success($supervisor->load(['supervisorProfile.photos', 'roles']), 'Supervisor berhasil ditambahkan.', 201);
    }

    public function show(Request $request, User $supervisor, SupervisorAssignmentService $assignmentService)
    {
        $this->assertViewable($request, $supervisor);
        abort_unless($supervisor->hasRole('supervisor'), 404);

        $supervisor->load(['supervisorProfile.photos']);

        $assignments = SupervisorAssignment::query()
            ->forSupervisor($supervisor->id)
            ->with(['cluster'])
            ->currentlyEffective()
            ->active()
            ->latest()
            ->get();

        $collectorIds = $assignmentService->collectorIdsFor($supervisor);

        return $this->success([
            'supervisor' => $supervisor,
            'assignments' => $assignments,
            'summary' => [
                'total_clusters' => $assignments->count(),
                'total_collectors' => count($collectorIds),
                'pending_approvals' => ApprovalRequest::query()->pending()->whereIn('related_collector_id', $collectorIds)->count(),
                'unread_notifications' => SupervisorNotification::query()->unread()->count(),
            ],
            'supervised_collectors' => User::query()->whereIn('id', $collectorIds)->with('collectorProfile')->get(),
        ]);
    }

    public function update(Request $request, User $supervisor, AuditService $auditService)
    {
        abort_unless($supervisor->hasRole('supervisor'), 404);
        $data = $this->validateSupervisor($request, $supervisor);
        $old = $supervisor->load('supervisorProfile')->toArray();

        $supervisor->update([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['account_status'] === SupervisorProfile::STATUS_ACTIVE,
            ...(! empty($data['password']) ? ['password' => Hash::make($data['password'])] : []),
        ]);

        $supervisor->supervisorProfile()->update([
            'supervisor_code' => ($data['supervisor_code'] ?? null) ?: $supervisor->supervisorProfile->supervisor_code,
            'whatsapp_number' => $data['whatsapp_number'] ?? null,
            'address' => $data['address'] ?? null,
            'joined_at' => $data['joined_at'] ?? null,
            'employment_status' => $data['employment_status'] ?? SupervisorProfile::EMPLOYMENT_PERMANENT,
            'account_status' => $data['account_status'],
            'admin_notes' => $data['admin_notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);

        $supervisor->refresh()->load('supervisorProfile');
        $auditService->log('supervisor_updated', 'supervisors', 'UPDATE', $supervisor, $old, $supervisor->toArray());

        return $this->success($supervisor->load(['supervisorProfile.photos', 'roles']), 'Supervisor berhasil diperbarui.');
    }

    public function destroy(Request $request, User $supervisor, AuditService $auditService)
    {
        abort_unless($supervisor->hasRole('supervisor'), 404);
        $old = $supervisor->toArray();
        $supervisor->delete();
        $auditService->log('supervisor_deleted', 'supervisors', 'DELETE', $supervisor, $old, []);

        return $this->success(null, 'Supervisor berhasil dihapus.');
    }

    public function updateStatus(Request $request, User $supervisor, AuditService $auditService)
    {
        abort_unless($supervisor->hasRole('supervisor'), 404);
        $data = $request->validate([
            'account_status' => ['required', Rule::in(self::ACCOUNT_STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $old = $supervisor->load('supervisorProfile')->toArray();

        $supervisor->forceFill(['is_active' => $data['account_status'] === SupervisorProfile::STATUS_ACTIVE])->save();
        $supervisor->supervisorProfile->forceFill([
            'account_status' => $data['account_status'],
            'admin_notes' => $data['reason']
                ? trim(($supervisor->supervisorProfile->admin_notes ?? '')."\n[".now()->toDateTimeString()."] {$data['reason']}")
                : $supervisor->supervisorProfile->admin_notes,
            'updated_by' => $request->user()->id,
        ])->save();

        $supervisor->refresh()->load('supervisorProfile');
        $auditService->log('supervisor_status_changed', 'supervisors', 'UPDATE_STATUS', $supervisor, $old, $supervisor->toArray());

        return $this->success($supervisor, 'Status supervisor berhasil diperbarui.');
    }

    public function activityHistory(Request $request, User $supervisor)
    {
        $this->assertViewable($request, $supervisor);
        abort_unless($supervisor->hasRole('supervisor'), 404);

        $query = AuditLog::query()->where('user_id', $supervisor->id);

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 20)));
    }

    public function uploadPhoto(Request $request, User $supervisor)
    {
        abort_unless($supervisor->hasRole('supervisor'), 404);
        $data = $request->validate(['photo' => ['required', 'file', 'image', 'max:5120']]);

        $stored = $data['photo']->store('supervisor-photos', 'public');
        $file = ManagedFile::query()->create([
            'original_filename' => $data['photo']->getClientOriginalName(),
            'stored_filename' => basename($stored),
            'path' => $stored,
            'disk' => 'public',
            'mime_type' => $data['photo']->getMimeType(),
            'extension' => $data['photo']->getClientOriginalExtension(),
            'size' => $data['photo']->getSize(),
            'uploaded_by' => $request->user()->id,
            'entity_type' => SupervisorProfile::class,
            'entity_id' => $supervisor->supervisorProfile->id,
        ]);

        return $this->success($file, 'Foto profil berhasil diunggah.', 201);
    }

    private function assertViewable(Request $request, User $supervisor): void
    {
        if ($request->user()->hasRole('supervisor')) {
            abort_unless($request->user()->id === $supervisor->id, 403, 'Anda hanya dapat melihat profil Anda sendiri.');
        }
    }

    private function generateSupervisorCode(): string
    {
        $max = SupervisorProfile::query()
            ->where('supervisor_code', 'like', 'SPV-%')
            ->get()
            ->map(fn (SupervisorProfile $profile) => (int) substr($profile->supervisor_code, 4))
            ->max() ?? 0;

        return 'SPV-'.str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    private function validateSupervisor(Request $request, ?User $supervisor = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', Rule::unique('users')->ignore($supervisor?->id)],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('users')->ignore($supervisor?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => [$supervisor ? 'nullable' : 'required', 'string', 'min:8'],
            'supervisor_code' => ['nullable', 'string', 'max:20', Rule::unique('supervisor_profiles')->ignore($supervisor?->supervisorProfile?->id)],
            'whatsapp_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
            'joined_at' => ['nullable', 'date'],
            'employment_status' => ['nullable', Rule::in(self::EMPLOYMENT_STATUSES)],
            'account_status' => ['required', Rule::in(self::ACCOUNT_STATUSES)],
            'admin_notes' => ['nullable', 'string'],
        ]);
    }
}
