<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SupervisorAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupervisorManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeCollector(string $username): User
    {
        $collector = User::factory()->create(['is_active' => true, 'username' => $username]);
        $collector->assignRole('collector');

        return $collector;
    }

    public function test_admin_can_create_a_supervisor_with_profile_and_it_gets_the_supervisor_role(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $response = $this->postJson('/api/v1/supervisors', [
            'name' => 'Supervisor Baru',
            'username' => 'supervisor.baru.test',
            'password' => 'password123',
            'account_status' => 'active',
            'employment_status' => 'tetap',
            'whatsapp_number' => '081200000099',
        ])->assertCreated();

        $userId = $response->json('data.id');
        $user = User::find($userId);
        $this->assertTrue($user->hasRole('supervisor'));
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->supervisorProfile);
        $this->assertStringStartsWith('SPV-', $user->supervisorProfile->supervisor_code);
    }

    public function test_setting_account_status_to_a_non_active_state_blocks_supervisor_login(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $supervisorId = $this->postJson('/api/v1/supervisors', [
            'name' => 'Supervisor Cuti', 'username' => 'supervisor.cuti.test', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/supervisors/{$supervisorId}/status", [
            'account_status' => 'suspended',
            'reason' => 'Pelanggaran SOP',
        ])->assertOk();

        $this->assertFalse(User::find($supervisorId)->is_active);
        $this->assertSame('suspended', User::find($supervisorId)->supervisorProfile->account_status);

        $this->postJson('/api/v1/auth/login', ['username' => 'supervisor.cuti.test', 'password' => 'password123'])
            ->assertStatus(403);
    }

    public function test_supervisor_can_only_view_their_own_detail_not_another_supervisors(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $selfId = $this->postJson('/api/v1/supervisors', [
            'name' => 'Supervisor Diri', 'username' => 'supervisor.diri.test', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');
        $otherId = $this->postJson('/api/v1/supervisors', [
            'name' => 'Supervisor Lain', 'username' => 'supervisor.lain.test', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs(User::find($selfId));
        $this->getJson("/api/v1/supervisors/{$selfId}")->assertOk();
        $this->getJson("/api/v1/supervisors/{$otherId}")->assertForbidden();
    }

    public function test_generic_user_management_cannot_assign_the_supervisor_role(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $this->postJson('/api/v1/users', [
            'name' => 'Percobaan Supervisor', 'username' => 'percobaan.supervisor.test',
            'password' => 'password123', 'role' => 'supervisor',
        ])->assertUnprocessable();
    }

    public function test_supervisor_assignment_scopes_collectors_by_cluster_overlap(): void
    {
        $this->seed();
        $root = User::where('username', 'root')->first();
        Sanctum::actingAs($root);

        $supervisorId = $this->postJson('/api/v1/supervisors', [
            'name' => 'Supervisor Wilayah GA', 'username' => 'supervisor.ga.test', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/supervisor-assignments', [
            'supervisor_id' => $supervisorId,
            'cluster_id' => 'GA',
        ])->assertCreated();

        $inClusterCollector = $this->makeCollector('collector.ga.test');
        $outOfClusterCollector = $this->makeCollector('collector.other.test');

        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $inClusterCollector->id,
            'scope_type' => 'cluster',
            'cluster_id' => 'GA',
        ])->assertCreated();

        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $outOfClusterCollector->id,
            'scope_type' => 'cluster',
            'cluster_id' => 'AL',
        ])->assertCreated();

        $supervisor = User::find($supervisorId);
        $collectorIds = app(SupervisorAssignmentService::class)->collectorIdsFor($supervisor);

        $this->assertContains($inClusterCollector->id, $collectorIds);
        $this->assertNotContains($outOfClusterCollector->id, $collectorIds);
    }

    public function test_reassign_deactivates_old_supervisor_assignment_and_creates_active_one_for_new_supervisor(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $supervisorA = $this->postJson('/api/v1/supervisors', [
            'name' => 'Supervisor A', 'username' => 'supervisor.a.test', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');
        $supervisorB = $this->postJson('/api/v1/supervisors', [
            'name' => 'Supervisor B', 'username' => 'supervisor.b.test', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');

        $assignmentId = $this->postJson('/api/v1/supervisor-assignments', [
            'supervisor_id' => $supervisorA,
            'cluster_id' => 'AL',
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/supervisor-assignments/{$assignmentId}/reassign", [
            'new_supervisor_id' => $supervisorB,
        ])->assertCreated();

        $this->assertDatabaseHas('supervisor_assignments', [
            'id' => $assignmentId, 'is_active' => false, 'status' => 'transferred',
        ]);
        $this->assertDatabaseHas('supervisor_assignments', [
            'supervisor_id' => $supervisorB, 'cluster_id' => 'AL', 'is_active' => true, 'status' => 'active',
        ]);
    }

    public function test_root_and_admin_estate_see_all_collectors_without_a_supervisor_assignment(): void
    {
        $this->seed();
        $root = User::where('username', 'root')->first();

        $collector = $this->makeCollector('collector.unscoped.test');
        Sanctum::actingAs($root);
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id,
            'scope_type' => 'cluster',
            'cluster_id' => 'GA',
        ])->assertCreated();

        $collectorIds = app(SupervisorAssignmentService::class)->collectorIdsFor($root);
        $this->assertContains($collector->id, $collectorIds);
    }
}
