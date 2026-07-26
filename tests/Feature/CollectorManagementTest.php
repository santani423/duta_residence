<?php

namespace Tests\Feature;

use App\Models\CollectorAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectorManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeCollector(string $username = 'test.collector'): User
    {
        $collector = User::factory()->create(['username' => $username, 'is_active' => true]);
        $collector->assignRole('collector');

        return $collector;
    }

    private function createUnit(string $unitId, string $residentName): array
    {
        Sanctum::actingAs(User::where('username', 'root')->first());

        $residentId = $this->postJson('/api/v1/residents', ['name' => $residentName])
            ->assertCreated()->json('data.resident.id');

        $this->postJson('/api/v1/units', [
            'id' => $unitId, 'resident_id' => $residentId, 'cluster_id' => 'GA', 'block' => 'Z',
            'lot_number' => substr($unitId, -2), 'property_type_id' => 'B', 'occupancy_id' => '1', 'status_id' => 'AK',
        ])->assertCreated();

        return ['resident_id' => $residentId, 'unit_id' => $unitId];
    }

    public function test_admin_can_create_a_collector_with_profile_and_it_gets_the_collector_role(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $response = $this->postJson('/api/v1/collectors', [
            'name' => 'Kolektor Baru',
            'username' => 'kolektor.baru.test',
            'password' => 'password123',
            'account_status' => 'active',
            'employment_status' => 'tetap',
            'whatsapp_number' => '081200000001',
        ])->assertCreated();

        $userId = $response->json('data.id');
        $user = User::find($userId);
        $this->assertTrue($user->hasRole('collector'));
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->collectorProfile);
        $this->assertStringStartsWith('COL-', $user->collectorProfile->collector_code);
    }

    public function test_setting_account_status_to_a_non_active_state_blocks_login(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $collectorId = $this->postJson('/api/v1/collectors', [
            'name' => 'Kolektor Cuti',
            'username' => 'kolektor.cuti.test',
            'password' => 'password123',
            'account_status' => 'active',
        ])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/collectors/{$collectorId}/status", [
            'account_status' => 'leave',
            'reason' => 'Cuti tahunan',
        ])->assertOk();

        $this->assertFalse(User::find($collectorId)->is_active);
        $this->assertSame('leave', User::find($collectorId)->collectorProfile->account_status);

        $this->postJson('/api/v1/auth/login', ['username' => 'kolektor.cuti.test', 'password' => 'password123'])
            ->assertStatus(403);
    }

    public function test_collector_can_only_view_their_own_detail_not_another_collectors(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $selfId = $this->postJson('/api/v1/collectors', [
            'name' => 'Kolektor Diri Sendiri', 'username' => 'kolektor.diri.test', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');
        $otherId = $this->postJson('/api/v1/collectors', [
            'name' => 'Kolektor Lain', 'username' => 'kolektor.lain.test', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs(User::find($selfId));
        $this->getJson("/api/v1/collectors/{$selfId}")->assertOk();
        $this->getJson("/api/v1/collectors/{$otherId}")->assertForbidden();
    }

    public function test_reassign_deactivates_old_assignment_and_creates_active_one_for_new_collector(): void
    {
        $this->seed();
        $unit = $this->createUnit('ZZ991', 'Reassign Test Resident');
        $collectorA = $this->makeCollector('collector.a');
        $collectorB = $this->makeCollector('collector.b');

        Sanctum::actingAs(User::where('username', 'root')->first());
        $assignmentId = $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collectorA->id, 'scope_type' => 'unit', 'unit_id' => $unit['unit_id'],
        ])->assertCreated()->json('data.id');

        $response = $this->postJson("/api/v1/collector-assignments/{$assignmentId}/reassign", [
            'new_collector_id' => $collectorB->id,
            'notes' => 'Collector A resigned.',
        ])->assertCreated();

        $newAssignmentId = $response->json('data.id');
        $this->assertNotSame($assignmentId, $newAssignmentId);

        $old = CollectorAssignment::find($assignmentId);
        $this->assertFalse($old->is_active);
        $this->assertSame('transferred', $old->status);

        $new = CollectorAssignment::find($newAssignmentId);
        $this->assertTrue($new->is_active);
        $this->assertSame('active', $new->status);
        $this->assertSame($collectorB->id, $new->collector_id);
        $this->assertSame($unit['unit_id'], $new->unit_id);

        // Scoping reflects the transfer immediately.
        Sanctum::actingAs($collectorA);
        $this->getJson("/api/v1/units/{$unit['unit_id']}")->assertForbidden();

        Sanctum::actingAs($collectorB);
        $this->getJson("/api/v1/units/{$unit['unit_id']}")->assertOk();
    }

    public function test_reassign_rejects_moving_to_the_same_collector(): void
    {
        $this->seed();
        $unit = $this->createUnit('ZZ992', 'Reassign Self Test');
        $collector = $this->makeCollector('collector.self.reassign');

        Sanctum::actingAs(User::where('username', 'root')->first());
        $assignmentId = $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id, 'scope_type' => 'unit', 'unit_id' => $unit['unit_id'],
        ])->assertCreated()->json('data.id');

        $this->postJson("/api/v1/collector-assignments/{$assignmentId}/reassign", [
            'new_collector_id' => $collector->id,
        ])->assertStatus(422);
    }

    public function test_assignment_with_future_start_date_does_not_grant_access_yet(): void
    {
        $this->seed();
        $unit = $this->createUnit('ZZ993', 'Future Assignment Test');
        $collector = $this->makeCollector('collector.future');

        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id, 'scope_type' => 'unit', 'unit_id' => $unit['unit_id'],
            'start_date' => now()->addWeek()->toDateString(),
        ])->assertCreated();

        Sanctum::actingAs($collector);
        $this->getJson("/api/v1/units/{$unit['unit_id']}")->assertForbidden();
    }

    public function test_assignment_with_past_end_date_no_longer_grants_access(): void
    {
        $this->seed();
        $unit = $this->createUnit('ZZ994', 'Expired Assignment Test');
        $collector = $this->makeCollector('collector.expired');

        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id, 'scope_type' => 'unit', 'unit_id' => $unit['unit_id'],
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subWeek()->toDateString(),
        ])->assertCreated();

        Sanctum::actingAs($collector);
        $this->getJson("/api/v1/units/{$unit['unit_id']}")->assertForbidden();
    }

    public function test_same_scope_can_be_reassigned_across_non_overlapping_date_ranges(): void
    {
        $this->seed();
        $unit = $this->createUnit('ZZ995', 'Non Overlap Test');
        $collector = $this->makeCollector('collector.nonoverlap');

        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id, 'scope_type' => 'unit', 'unit_id' => $unit['unit_id'],
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->subMonth()->toDateString(),
        ])->assertCreated();

        // A brand new assignment for the same collector/unit starting after the first one ended must be allowed.
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id, 'scope_type' => 'unit', 'unit_id' => $unit['unit_id'],
            'start_date' => now()->toDateString(),
        ])->assertCreated();
    }

    // ---- User <-> Collector synchronization & authentication ----

    public function test_collector_can_log_in_with_username_and_password(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->postJson('/api/v1/collectors', [
            'name' => 'Login Test Collector', 'username' => 'login.test.collector', 'email' => 'login.test@example.com',
            'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated();

        $response = $this->postJson('/api/v1/auth/login', ['username' => 'login.test.collector', 'password' => 'password123']);
        $response->assertOk();
        $this->assertSame('collector', $response->json('data.user.role'));
        $this->assertNotNull($response->json('data.user.collector_profile.collector_code'));
    }

    public function test_collector_can_log_in_with_email_and_password(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->postJson('/api/v1/collectors', [
            'name' => 'Email Login Collector', 'username' => 'email.login.collector', 'email' => 'email.login@example.com',
            'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated();

        // The login field accepts either identifier - matches what the Android login
        // screen has always advertised ("Email, nomor telepon, atau username").
        $this->postJson('/api/v1/auth/login', ['username' => 'email.login@example.com', 'password' => 'password123'])
            ->assertOk();
    }

    public function test_collector_login_rejects_wrong_password(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->postJson('/api/v1/collectors', [
            'name' => 'Wrong Password Test', 'username' => 'wrongpw.collector', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/login', ['username' => 'wrongpw.collector', 'password' => 'not-the-password'])
            ->assertStatus(422);
    }

    public function test_collector_login_blocked_for_inactive_and_suspended_status(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        foreach (['inactive', 'suspended'] as $status) {
            $username = "status.{$status}.collector";
            $this->postJson('/api/v1/collectors', [
                'name' => "Status {$status}", 'username' => $username, 'password' => 'password123', 'account_status' => $status,
            ])->assertCreated();

            $this->postJson('/api/v1/auth/login', ['username' => $username, 'password' => 'password123'])
                ->assertStatus(403);
        }
    }

    public function test_updating_collector_password_takes_effect_immediately(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());
        $collectorId = $this->postJson('/api/v1/collectors', [
            'name' => 'Password Change Test', 'username' => 'pwchange.collector', 'password' => 'oldpassword1', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/collectors/{$collectorId}", [
            'name' => 'Password Change Test', 'username' => 'pwchange.collector', 'password' => 'newpassword1', 'account_status' => 'active',
        ])->assertOk();

        $this->postJson('/api/v1/auth/login', ['username' => 'pwchange.collector', 'password' => 'oldpassword1'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['username' => 'pwchange.collector', 'password' => 'newpassword1'])->assertOk();
    }

    public function test_updating_collector_email_and_phone_syncs_to_user_record(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());
        $collectorId = $this->postJson('/api/v1/collectors', [
            'name' => 'Sync Test', 'username' => 'sync.collector', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/collectors/{$collectorId}", [
            'name' => 'Sync Test Updated', 'username' => 'sync.collector', 'email' => 'synced@example.com',
            'phone' => '081299999999', 'account_status' => 'active',
        ])->assertOk();

        $user = User::find($collectorId);
        $this->assertSame('Sync Test Updated', $user->name);
        $this->assertSame('synced@example.com', $user->email);
        $this->assertSame('081299999999', $user->phone);

        // The new email is immediately usable to log in - proves it's the same `users`
        // row, not a separate credential store.
        $this->postJson('/api/v1/auth/login', ['username' => 'synced@example.com', 'password' => 'password123'])->assertOk();
    }

    public function test_generic_user_management_cannot_assign_the_collector_role(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        // Creating a brand-new user with role=collector through the generic Users module
        // must be rejected - it would produce a User with the role but no collector_profiles
        // row, breaking every collector_profile-dependent screen.
        $this->postJson('/api/v1/users', [
            'name' => 'Orphan Attempt', 'username' => 'orphan.attempt', 'password' => 'password123', 'role' => 'collector',
        ])->assertStatus(422);

        // Promoting an existing non-collector user to collector through the generic
        // module must be rejected the same way.
        $csUser = User::factory()->create(['username' => 'promote.attempt']);
        $csUser->assignRole('cs');
        $this->putJson("/api/v1/users/{$csUser->id}", [
            'name' => $csUser->name, 'username' => $csUser->username, 'role' => 'collector',
        ])->assertStatus(422);

        $this->assertSame(0, \App\Models\CollectorProfile::whereIn('user_id', [
            User::where('username', 'orphan.attempt')->value('id') ?? 0,
        ])->count());
    }

    public function test_editing_an_existing_collector_via_generic_user_endpoint_keeps_their_role(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());
        $collectorId = $this->postJson('/api/v1/collectors', [
            'name' => 'Existing Collector', 'username' => 'existing.collector', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');

        // The generic Users endpoint may still edit basic fields for an *existing*
        // collector (e.g. fixing a typo) without being rejected for keeping their role.
        $this->putJson("/api/v1/users/{$collectorId}", [
            'name' => 'Existing Collector Fixed', 'username' => 'existing.collector', 'role' => 'collector',
        ])->assertOk();

        $this->assertTrue(User::find($collectorId)->hasRole('collector'));
    }

    public function test_deactivating_a_collector_does_not_orphan_their_profile(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());
        $collectorId = $this->postJson('/api/v1/collectors', [
            'name' => 'Deactivate Test', 'username' => 'deactivate.collector', 'password' => 'password123', 'account_status' => 'active',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/collectors/{$collectorId}")->assertOk();

        $this->assertSoftDeleted('users', ['id' => $collectorId]);
        $this->assertDatabaseHas('collector_profiles', ['user_id' => $collectorId]);
        $this->postJson('/api/v1/auth/login', ['username' => 'deactivate.collector', 'password' => 'password123'])
            ->assertStatus(422);
    }
}
