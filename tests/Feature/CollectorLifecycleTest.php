<?php

namespace Tests\Feature;

use App\Models\CollectionAccountState;
use App\Models\CollectorAssignment;
use App\Models\CollectorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Stage B1 — CollectorController: siklus hidup (nonaktif/hapus dengan serah terima penugasan),
 * pencabutan token, PUT parsial, jam tugas, scope supervisor, dan stats daftar/detail.
 */
class CollectorLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private int $lotSeq = 10;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function root(): User
    {
        return User::where('username', 'root')->first();
    }

    private function actAsRoot(): User
    {
        $root = $this->root();
        Sanctum::actingAs($root);

        return $root;
    }

    /** Collector lengkap dengan profil (lewat API). */
    private function createCollector(string $username, array $overrides = []): User
    {
        $this->actAsRoot();
        $id = $this->postJson('/api/v1/collectors', [
            'name' => 'Kolektor '.$username,
            'username' => $username,
            'password' => 'password123',
            'account_status' => 'active',
            ...$overrides,
        ])->assertCreated()->json('data.id');

        return User::find($id);
    }

    private function createUnit(string $cluster = 'GA'): string
    {
        $this->actAsRoot();
        $this->lotSeq++;

        $residentId = $this->postJson('/api/v1/residents', ['name' => 'Penghuni Lifecycle '.$this->lotSeq])
            ->assertCreated()->json('data.resident.id');

        return $this->postJson('/api/v1/units', [
            'resident_id' => $residentId, 'cluster_id' => $cluster, 'block' => 'Y',
            'lot_number' => (string) $this->lotSeq, 'property_type_id' => 'B', 'occupancy_id' => '1', 'status_id' => 'AK',
        ])->assertCreated()->json('data.id');
    }

    private function assignUnit(User $collector, string $unitId): int
    {
        $this->actAsRoot();

        return $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id, 'scope_type' => 'unit', 'unit_id' => $unitId,
        ])->assertCreated()->json('data.id');
    }

    private function freshAuth(): void
    {
        $this->app['auth']->forgetGuards();
    }

    // ---- Lifecycle guard ----

    public function test_delete_is_blocked_while_collector_has_active_assignments(): void
    {
        $collector = $this->createCollector('lc.delete.blocked');
        $this->assignUnit($collector, $this->createUnit());

        $this->actAsRoot();
        $this->deleteJson("/api/v1/collectors/{$collector->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.active_assignment_count', 1);

        $this->assertNotSoftDeleted('users', ['id' => $collector->id]);
        $this->assertSame(1, CollectorAssignment::where('collector_id', $collector->id)->where('is_active', true)->count());
    }

    public function test_deactivation_is_blocked_while_collector_has_active_assignments(): void
    {
        $collector = $this->createCollector('lc.status.blocked');
        $this->assignUnit($collector, $this->createUnit());

        $this->actAsRoot();
        $this->patchJson("/api/v1/collectors/{$collector->id}/status", ['account_status' => 'suspended', 'reason' => 'Pelanggaran SOP'])
            ->assertStatus(422)
            ->assertJsonPath('errors.active_assignment_count', 1);

        $this->assertTrue($collector->fresh()->is_active);
        $this->assertSame('active', $collector->fresh()->collectorProfile->account_status);
    }

    public function test_put_to_non_active_status_follows_the_same_guard(): void
    {
        $collector = $this->createCollector('lc.put.blocked');
        $this->assignUnit($collector, $this->createUnit());

        $this->actAsRoot();
        $this->putJson("/api/v1/collectors/{$collector->id}", ['account_status' => 'inactive'])
            ->assertStatus(422)
            ->assertJsonPath('errors.active_assignment_count', 1);

        $this->assertTrue($collector->fresh()->is_active);
    }

    public function test_reassign_handover_moves_every_active_assignment_and_refreshes_states(): void
    {
        $collector = $this->createCollector('lc.handover.from');
        $target = $this->createCollector('lc.handover.to');
        $unitA = $this->createUnit();
        $unitB = $this->createUnit();
        $first = $this->assignUnit($collector, $unitA);
        $second = $this->assignUnit($collector, $unitB);

        $this->assertSame($collector->id, (int) CollectionAccountState::find($unitA)?->collector_id);

        $this->actAsRoot();
        $this->patchJson("/api/v1/collectors/{$collector->id}/status", [
            'account_status' => 'leave',
            'assignment_action' => 'reassign',
            'reassign_to_collector_id' => $target->id,
            'reason' => 'Cuti melahirkan tiga bulan',
        ])->assertOk();

        foreach ([$first, $second] as $oldId) {
            $old = CollectorAssignment::find($oldId);
            $this->assertFalse($old->is_active);
            $this->assertSame('transferred', $old->status);

            $new = CollectorAssignment::where('reassigned_from_id', $oldId)->first();
            $this->assertNotNull($new);
            $this->assertSame($target->id, $new->collector_id);
            $this->assertTrue($new->is_active);
            $this->assertSame('Cuti melahirkan tiga bulan', $new->reassign_reason);
            $this->assertSame($old->unit_id, $new->unit_id);
        }

        $this->assertSame(0, CollectorAssignment::where('collector_id', $collector->id)->where('is_active', true)->count());
        $this->assertSame($target->id, (int) CollectionAccountState::find($unitA)->collector_id);
        $this->assertSame($target->id, (int) CollectionAccountState::find($unitB)->collector_id);

        $fresh = $collector->fresh();
        $this->assertFalse($fresh->is_active);
        $this->assertSame('leave', $fresh->collectorProfile->account_status);
    }

    public function test_end_handover_on_delete_cancels_assignments_and_deletes_collector(): void
    {
        $collector = $this->createCollector('lc.handover.end');
        $unit = $this->createUnit();
        $assignmentId = $this->assignUnit($collector, $unit);

        $this->actAsRoot();
        $this->deleteJson("/api/v1/collectors/{$collector->id}", [
            'assignment_action' => 'end',
            'reason' => 'Kontrak kerja berakhir',
        ])->assertOk();

        $assignment = CollectorAssignment::find($assignmentId);
        $this->assertFalse($assignment->is_active);
        $this->assertSame('cancelled', $assignment->status);
        $this->assertSame(now()->toDateString(), $assignment->end_date->toDateString());
        $this->assertSoftDeleted('users', ['id' => $collector->id]);
        $this->assertNull(CollectionAccountState::find($unit)?->collector_id);
    }

    public function test_handover_payload_is_validated(): void
    {
        $collector = $this->createCollector('lc.handover.invalid');
        $inactive = $this->createCollector('lc.handover.inactive', ['account_status' => 'inactive']);
        $this->assignUnit($collector, $this->createUnit());

        $this->actAsRoot();
        $url = "/api/v1/collectors/{$collector->id}/status";

        $this->patchJson($url, ['account_status' => 'inactive', 'assignment_action' => 'reassign'])
            ->assertStatus(422)->assertJsonValidationErrors(['reassign_to_collector_id', 'reason']);

        $this->patchJson($url, ['account_status' => 'inactive', 'assignment_action' => 'end', 'reason' => 'abc'])
            ->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $this->patchJson($url, [
            'account_status' => 'inactive', 'assignment_action' => 'reassign',
            'reassign_to_collector_id' => $collector->id, 'reason' => 'Pindah ke diri sendiri',
        ])->assertStatus(422)->assertJsonValidationErrors(['reassign_to_collector_id']);

        $this->patchJson($url, [
            'account_status' => 'inactive', 'assignment_action' => 'reassign',
            'reassign_to_collector_id' => $inactive->id, 'reason' => 'Tujuan tidak aktif',
        ])->assertStatus(422)->assertJsonValidationErrors(['reassign_to_collector_id']);

        $this->assertTrue($collector->fresh()->is_active);
    }

    // ---- Token revocation ----

    public function test_deactivation_revokes_tokens_so_the_next_request_is_401(): void
    {
        $collector = $this->createCollector('lc.token.status');
        $collectorToken = $collector->createToken('device', ['*'])->plainTextToken;
        $adminToken = $this->root()->createToken('admin', ['*'])->plainTextToken;

        $this->freshAuth();
        $this->withToken($collectorToken)->getJson("/api/v1/collectors/{$collector->id}")->assertOk();

        $this->freshAuth();
        $this->withToken($adminToken)->patchJson("/api/v1/collectors/{$collector->id}/status", ['account_status' => 'suspended'])->assertOk();

        $this->assertSame(0, $collector->tokens()->count());
        $this->assertNull($collector->fresh()->active_token_id);

        $this->freshAuth();
        $this->withToken($collectorToken)->getJson("/api/v1/collectors/{$collector->id}")->assertUnauthorized();
    }

    public function test_delete_revokes_tokens(): void
    {
        $collector = $this->createCollector('lc.token.delete');
        $collector->createToken('device', ['*']);

        $this->actAsRoot();
        $this->deleteJson("/api/v1/collectors/{$collector->id}")->assertOk();

        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $collector->id)->where('tokenable_type', User::class)->count());
    }

    public function test_username_change_is_saved_revokes_tokens_and_new_username_logs_in(): void
    {
        $collector = $this->createCollector('lc.username.old');
        $collectorToken = $collector->createToken('device', ['*'])->plainTextToken;
        $adminToken = $this->root()->createToken('admin', ['*'])->plainTextToken;

        $this->freshAuth();
        $this->withToken($adminToken)->putJson("/api/v1/collectors/{$collector->id}", ['username' => 'lc.username.new'])->assertOk();

        $this->assertSame('lc.username.new', $collector->fresh()->username);

        $this->freshAuth();
        $this->withToken($collectorToken)->getJson("/api/v1/collectors/{$collector->id}")->assertUnauthorized();

        $this->freshAuth();
        $this->postJson('/api/v1/auth/login', ['username' => 'lc.username.old', 'password' => 'password123'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['username' => 'lc.username.new', 'password' => 'password123'])->assertOk();
    }

    public function test_supervisor_username_update_is_saved_and_revokes_tokens(): void
    {
        $this->actAsRoot();
        $supervisorId = $this->postJson('/api/v1/supervisors', [
            'name' => 'Supervisor Username', 'username' => 'spv.username.old', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');
        $supervisor = User::find($supervisorId);
        $supervisor->createToken('device', ['*']);

        $this->putJson("/api/v1/supervisors/{$supervisorId}", [
            'name' => 'Supervisor Username', 'username' => 'spv.username.new', 'account_status' => 'active',
        ])->assertOk();

        $this->assertSame('spv.username.new', $supervisor->fresh()->username);
        $this->assertSame(0, $supervisor->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['username' => 'spv.username.new', 'password' => 'password123'])->assertOk();

        $supervisor->createToken('device-2', ['*']);
        $this->actAsRoot();
        $this->patchJson("/api/v1/supervisors/{$supervisorId}/status", ['account_status' => 'inactive'])->assertOk();
        $this->assertSame(0, $supervisor->tokens()->count());
    }

    // ---- PUT parsial & jam tugas ----

    public function test_partial_put_only_writes_sent_fields(): void
    {
        $collector = $this->createCollector('lc.partial', [
            'email' => 'lc.partial@example.com',
            'phone' => '0811111111',
            'whatsapp_number' => '0822222222',
            'address' => 'Jl. Melati 1',
            'employment_status' => 'kontrak',
            'working_area_notes' => 'Cluster GA',
            'admin_notes' => 'Catatan internal',
            'joined_at' => '2026-01-05',
            'duty_start_time' => '08:00',
            'duty_end_time' => '17:00',
        ]);

        $this->actAsRoot();
        $this->putJson("/api/v1/collectors/{$collector->id}", ['name' => 'Nama Baru'])->assertOk();

        $fresh = $collector->fresh();
        $profile = $fresh->collectorProfile;
        $this->assertSame('Nama Baru', $fresh->name);
        $this->assertSame('lc.partial@example.com', $fresh->email);
        $this->assertSame('0811111111', $fresh->phone);
        $this->assertTrue($fresh->is_active);
        $this->assertSame('0822222222', $profile->whatsapp_number);
        $this->assertSame('Jl. Melati 1', $profile->address);
        $this->assertSame('kontrak', $profile->employment_status);
        $this->assertSame('Cluster GA', $profile->working_area_notes);
        $this->assertSame('Catatan internal', $profile->admin_notes);
        $this->assertSame('2026-01-05', $profile->joined_at->toDateString());
        $this->assertSame('active', $profile->account_status);
        $this->assertStringStartsWith('08:00', $profile->duty_start_time);
        $this->assertStringStartsWith('17:00', $profile->duty_end_time);

        // Mengirim null secara eksplisit tetap boleh mengosongkan field.
        $this->putJson("/api/v1/collectors/{$collector->id}", ['address' => null])->assertOk();
        $this->assertNull($collector->fresh()->collectorProfile->address);
        $this->assertSame('0822222222', $collector->fresh()->collectorProfile->whatsapp_number);
    }

    public function test_edit_form_payload_without_account_status_is_accepted(): void
    {
        $collector = $this->createCollector('lc.form.edit');

        $this->actAsRoot();
        $this->putJson("/api/v1/collectors/{$collector->id}", [
            'name' => 'Edit Form', 'username' => 'lc.form.edit', 'email' => null, 'phone' => null,
            'collector_code' => $collector->collectorProfile->collector_code, 'whatsapp_number' => '0899',
            'address' => null, 'employment_status' => 'harian', 'working_area_notes' => null, 'admin_notes' => null,
            'joined_at' => null, 'duty_start_time' => '07:30', 'duty_end_time' => '16:00',
        ])->assertOk()
            ->assertJsonPath('data.collector_profile.employment_status', 'harian');

        $profile = $collector->fresh()->collectorProfile;
        $this->assertStringStartsWith('07:30', $profile->duty_start_time);
        $this->assertStringStartsWith('16:00', $profile->duty_end_time);
        $this->assertSame('active', $profile->account_status);
    }

    public function test_duty_hours_are_validated(): void
    {
        $this->actAsRoot();
        $base = ['name' => 'Jam Tugas', 'password' => 'password123', 'account_status' => 'active'];

        $this->postJson('/api/v1/collectors', [...$base, 'username' => 'lc.duty.a', 'duty_start_time' => '08:00'])
            ->assertStatus(422)->assertJsonValidationErrors(['duty_end_time']);

        $this->postJson('/api/v1/collectors', [...$base, 'username' => 'lc.duty.b', 'duty_start_time' => '17:00', 'duty_end_time' => '08:00'])
            ->assertStatus(422)->assertJsonValidationErrors(['duty_end_time']);

        $this->postJson('/api/v1/collectors', [...$base, 'username' => 'lc.duty.c', 'duty_start_time' => '8 pagi', 'duty_end_time' => '17:00'])
            ->assertStatus(422)->assertJsonValidationErrors(['duty_start_time']);

        $id = $this->postJson('/api/v1/collectors', [...$base, 'username' => 'lc.duty.d', 'duty_start_time' => '08:00', 'duty_end_time' => '17:00'])
            ->assertCreated()->json('data.id');

        // PUT parsial dibandingkan dengan jam mulai yang tersimpan.
        $this->putJson("/api/v1/collectors/{$id}", ['duty_end_time' => '07:00'])
            ->assertStatus(422)->assertJsonValidationErrors(['duty_end_time']);
        $this->putJson("/api/v1/collectors/{$id}", ['duty_end_time' => '18:00'])->assertOk();
    }

    public function test_collector_without_profile_can_be_updated_status_changed_and_photographed(): void
    {
        $collector = User::factory()->create(['username' => 'lc.noprofile', 'is_active' => true]);
        $collector->assignRole('collector');
        Storage::fake('public');

        $this->actAsRoot();
        $this->putJson("/api/v1/collectors/{$collector->id}", ['whatsapp_number' => '0855'])->assertOk();
        $profile = CollectorProfile::where('user_id', $collector->id)->first();
        $this->assertNotNull($profile);
        $this->assertSame('0855', $profile->whatsapp_number);
        $this->assertStringStartsWith('COL-', $profile->collector_code);

        $other = User::factory()->create(['username' => 'lc.noprofile.2', 'is_active' => true]);
        $other->assignRole('collector');
        $this->patchJson("/api/v1/collectors/{$other->id}/status", ['account_status' => 'leave'])->assertOk();
        $this->assertSame('leave', $other->fresh()->collectorProfile->account_status);

        $third = User::factory()->create(['username' => 'lc.noprofile.3', 'is_active' => true]);
        $third->assignRole('collector');
        $this->post("/api/v1/collectors/{$third->id}/photo", ['photo' => UploadedFile::fake()->image('foto.jpg')], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->getJson('/api/v1/collectors?search=lc.noprofile.3')
            ->assertOk()
            ->assertJsonPath('data.0.collector_profile.photo_url', fn ($url) => is_string($url) && str_contains($url, '/storage/collector-photos/'));
    }

    // ---- Scope, admin_notes, stats ----

    public function test_supervisor_only_lists_and_views_collectors_in_their_clusters(): void
    {
        $inScope = $this->createCollector('lc.scope.in');
        $outScope = $this->createCollector('lc.scope.out');
        // Cluster penuh sudah dipegang collector seeder (guard duplikat), jadi pakai scope unit.
        $this->assignUnit($inScope, $this->createUnit('GA'));
        $this->assignUnit($outScope, $this->createUnit('AL'));

        $this->actAsRoot();
        $supervisorId = $this->postJson('/api/v1/supervisors', [
            'name' => 'Supervisor Scope', 'username' => 'lc.scope.spv', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/supervisor-assignments', ['supervisor_id' => $supervisorId, 'cluster_id' => 'GA'])->assertCreated();

        Sanctum::actingAs(User::find($supervisorId));
        $ids = collect($this->getJson('/api/v1/collectors?per_page=100')->assertOk()->json('data'))->pluck('id');
        $this->assertContains($inScope->id, $ids);
        $this->assertNotContains($outScope->id, $ids);

        $this->getJson("/api/v1/collectors/{$inScope->id}")->assertOk();
        $this->getJson("/api/v1/collectors/{$outScope->id}")->assertForbidden();
        $this->getJson("/api/v1/collectors/{$outScope->id}/assignment-history")->assertForbidden();
    }

    public function test_cluster_filter_and_per_page_clamp_on_index(): void
    {
        $inGa = $this->createCollector('lc.filter.ga');
        $inAl = $this->createCollector('lc.filter.al');
        $this->assignUnit($inGa, $this->createUnit('GA'));
        $this->assignUnit($inAl, $this->createUnit('AL'));

        $this->actAsRoot();
        $ids = collect($this->getJson('/api/v1/collectors?cluster_id=GA&per_page=100')->assertOk()->json('data'))->pluck('id');
        $this->assertContains($inGa->id, $ids);
        $this->assertNotContains($inAl->id, $ids);

        $this->getJson('/api/v1/collectors?per_page=100000')->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    public function test_collector_viewer_does_not_receive_admin_notes(): void
    {
        $collector = $this->createCollector('lc.notes', ['admin_notes' => 'Rahasia admin']);

        $this->actAsRoot();
        $this->getJson("/api/v1/collectors/{$collector->id}")
            ->assertOk()->assertJsonPath('data.collector.collector_profile.admin_notes', 'Rahasia admin');

        Sanctum::actingAs($collector);
        $response = $this->getJson("/api/v1/collectors/{$collector->id}")->assertOk();
        $this->assertArrayNotHasKey('admin_notes', $response->json('data.collector.collector_profile'));
    }

    public function test_index_returns_stats_and_photo_url_for_every_row(): void
    {
        $collector = $this->createCollector('lc.stats');
        $this->assignUnit($collector, $this->createUnit());

        $this->actAsRoot();
        $row = collect($this->getJson('/api/v1/collectors?search=lc.stats')->assertOk()->json('data'))
            ->firstWhere('id', $collector->id);

        $this->assertNotNull($row);
        foreach (['assigned_accounts', 'outstanding_total', 'overdue_accounts', 'critical_accounts', 'active_assignment_count', 'collected_this_month', 'target_this_month', 'achievement_percent_raw'] as $key) {
            $this->assertArrayHasKey($key, $row['stats'], "stats.{$key} hilang");
        }
        $this->assertSame(1, $row['stats']['active_assignment_count']);
        $this->assertSame(1, $row['stats']['assigned_accounts']);
        $this->assertArrayHasKey('photo_url', $row['collector_profile']);
    }

    public function test_show_summary_contains_state_and_month_metrics(): void
    {
        $collector = $this->createCollector('lc.summary', ['initial_monthly_target' => 5000000]);
        $this->assignUnit($collector, $this->createUnit());

        $this->actAsRoot();
        $summary = $this->getJson("/api/v1/collectors/{$collector->id}")->assertOk()->json('data.summary');

        foreach ([
            'assigned_accounts', 'outstanding_total', 'overdue_accounts', 'critical_accounts', 'active_assignment_count',
            'collected_this_month', 'target_this_month', 'achievement_percent_raw', 'visit_count', 'successful_visit_rate',
            'ptp_created', 'ptp_fulfilled', 'ptp_broken', 'total_units', 'total_outstanding', 'total_visits',
        ] as $key) {
            $this->assertArrayHasKey($key, $summary, "summary.{$key} hilang");
        }
        $this->assertSame(1, $summary['assigned_accounts']);
        $this->assertSame(1, $summary['total_units']);
        $this->assertEquals(5000000, $summary['target_this_month']);
    }
}
