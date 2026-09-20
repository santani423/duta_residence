<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResidentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_resident_auto_creates_customer_login_account(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $response = $this->postJson('/api/v1/residents', [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi.santoso@example.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => [
                'resident' => ['id', 'name'],
                'login_account' => ['user_id', 'username', 'temporary_password'],
            ]]);

        $residentId = $response->json('data.resident.id');
        $username = $response->json('data.login_account.username');

        $this->assertSame('customer.'.strtolower($residentId), $username);
        $this->assertSame('password', $response->json('data.login_account.temporary_password'));

        $user = User::where('username', $username)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->unit_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->hasRole('customer'));
    }

    public function test_resident_can_be_deactivated_and_reactivated_with_login_account(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $residentId = $this->postJson('/api/v1/residents', [
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi.santoso@example.com',
        ])->assertCreated()->json('data.resident.id');

        $this->patchJson("/api/v1/residents/{$residentId}/active", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->assertFalse(User::where('resident_id', $residentId)->first()->is_active);

        $this->patchJson("/api/v1/residents/{$residentId}/active", ['is_active' => true])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);
        $this->assertTrue(User::where('resident_id', $residentId)->first()->is_active);
    }

    public function test_units_index_can_filter_to_unassigned_units_by_cluster_and_block(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $emptyUnitId = $this->postJson('/api/v1/units', [
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => '01',
            'property_type_id' => 'B',
            'occupancy_id' => '2',
            'status_id' => 'RK',
        ])->assertCreated()->json('data.id');

        $occupiedResidentId = $this->postJson('/api/v1/residents', ['name' => 'Sudah Ada Unit'])
            ->assertCreated()->json('data.resident.id');

        $this->postJson('/api/v1/units', [
            'resident_id' => $occupiedResidentId,
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => '02',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/units?unassigned=1&cluster_id=GA&block=Z');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($emptyUnitId));
        $this->assertCount(1, $ids);
    }

    public function test_creating_resident_can_link_to_a_selected_empty_unit(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $unitId = $this->postJson('/api/v1/units', [
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => '03',
            'property_type_id' => 'B',
            'occupancy_id' => '2',
            'status_id' => 'RK',
        ])->assertCreated()->json('data.id');

        $response = $this->postJson('/api/v1/residents', [
            'name' => 'Penghuni Baru',
            'phone' => '081234567891',
            'email' => 'penghuni.baru@example.com',
            'unit_id' => $unitId,
            'va_suffix' => '123456789',
        ]);

        $response->assertCreated();
        $residentId = $response->json('data.resident.id');

        $unit = Unit::query()->find($unitId);
        $this->assertSame($residentId, $unit->resident_id);
        $this->assertSame(PaymentGatewaySetting::current()->vaPrefix().'123456789', $unit->va_number);
        $this->assertSame('AK', $unit->status_id);
        $this->assertSame(Unit::OCCUPANCY_OCCUPIED_ID, $unit->occupancy_id);
        $this->assertSame(now()->toDateString(), $unit->handover_date->toDateString());
        $this->assertSame('occupied', $unit->occupancy_status);

        $user = User::where('resident_id', $residentId)->first();
        $this->assertSame($unitId, $user->unit_id);
    }

    private function createEmptyUnit(string $lot): string
    {
        return $this->postJson('/api/v1/units', [
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => $lot,
            'property_type_id' => 'B',
            'occupancy_id' => '2',
            'status_id' => 'RK',
        ])->assertCreated()->json('data.id');
    }

    public function test_linking_resident_to_unit_requires_va_phone_and_email(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $this->postJson('/api/v1/residents', [
            'name' => 'Tanpa Kontak',
            'unit_id' => $this->createEmptyUnit('11'),
        ])->assertStatus(422)->assertJsonValidationErrors(['va_suffix', 'phone', 'email']);

        $this->assertDatabaseMissing('residents', ['name' => 'Tanpa Kontak']);
    }

    public function test_linking_resident_to_unit_rejects_va_already_used_by_another_unit(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $this->postJson('/api/v1/residents', [
            'name' => 'Pertama',
            'phone' => '081234567893',
            'email' => 'pertama@example.com',
            'unit_id' => $this->createEmptyUnit('12'),
            'va_suffix' => '555555555',
        ])->assertCreated();

        $this->postJson('/api/v1/residents', [
            'name' => 'Kedua',
            'phone' => '081234567894',
            'email' => 'kedua@example.com',
            'unit_id' => $this->createEmptyUnit('13'),
            'va_suffix' => '555555555',
        ])->assertStatus(422)->assertJsonValidationErrors(['va_suffix']);

        $this->assertDatabaseMissing('residents', ['name' => 'Kedua']);
    }

    public function test_check_availability_reports_taken_va_suffix(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $unitId = $this->createEmptyUnit('14');
        $this->postJson('/api/v1/residents', [
            'name' => 'Pemilik VA',
            'phone' => '081234567895',
            'email' => 'pemilikva@example.com',
            'unit_id' => $unitId,
            'va_suffix' => '777777777',
        ])->assertCreated();

        $this->getJson('/api/v1/residents/check-availability?field=va_suffix&value=777777777')
            ->assertOk()->assertJsonPath('data.taken', true);
        $this->getJson('/api/v1/residents/check-availability?field=va_suffix&value=777777777&exclude_id='.$unitId)
            ->assertOk()->assertJsonPath('data.taken', false);
        $this->getJson('/api/v1/residents/check-availability?field=va_suffix&value=888888888')
            ->assertOk()->assertJsonPath('data.taken', false);
    }

    public function test_existing_resident_can_be_assigned_to_a_unit_with_unique_va(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'root')->first());

        $residentId = $this->postJson('/api/v1/residents', ['name' => 'Sudah Ada'])->assertCreated()->json('data.resident.id');
        $unitId = $this->createEmptyUnit('15');
        $otherUnitId = $this->createEmptyUnit('16');

        $this->postJson("/api/v1/units/{$unitId}/assign-resident", ['resident_id' => $residentId])
            ->assertStatus(422)->assertJsonValidationErrors(['va_suffix']);

        $this->postJson("/api/v1/units/{$unitId}/assign-resident", ['resident_id' => $residentId, 'va_suffix' => '246813579'])
            ->assertOk();

        $unit = Unit::query()->find($unitId);
        $this->assertSame($residentId, $unit->resident_id);
        $this->assertSame('AK', $unit->status_id);
        $this->assertSame(Unit::OCCUPANCY_OCCUPIED_ID, $unit->occupancy_id);
        $this->assertSame(now()->toDateString(), $unit->handover_date->toDateString());
        $this->assertSame('occupied', $unit->occupancy_status);
        $this->assertSame(PaymentGatewaySetting::current()->vaPrefix().'246813579', $unit->va_number);

        $this->postJson("/api/v1/units/{$otherUnitId}/assign-resident", ['resident_id' => $residentId, 'va_suffix' => '246813579'])
            ->assertStatus(422)->assertJsonValidationErrors(['va_suffix']);

        $this->postJson("/api/v1/units/{$unitId}/assign-resident", ['resident_id' => $residentId, 'va_suffix' => '111111111'])
            ->assertStatus(422)->assertJsonValidationErrors(['resident_id']);
    }

    public function test_creating_resident_rejects_unit_that_already_has_a_resident(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $existingResidentId = $this->postJson('/api/v1/residents', ['name' => 'Pemilik Lama'])
            ->assertCreated()->json('data.resident.id');

        $unitId = $this->postJson('/api/v1/units', [
            'resident_id' => $existingResidentId,
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => '04',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated()->json('data.id');

        $this->postJson('/api/v1/residents', [
            'name' => 'Penghuni Gagal',
            'phone' => '081234567892',
            'email' => 'gagal@example.com',
            'va_suffix' => '123456789',
            'unit_id' => $unitId,
        ])->assertStatus(422)->assertJsonValidationErrors(['unit_id']);
    }

    public function test_resident_becomes_without_unit_after_unit_link_is_cancelled(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $residentId = $this->postJson('/api/v1/residents', ['name' => 'Lepas Unit'])
            ->assertCreated()->json('data.resident.id');
        $neverLinkedId = $this->postJson('/api/v1/residents', ['name' => 'Belum Pernah'])
            ->assertCreated()->json('data.resident.id');

        $unitPayload = [
            'cluster_id' => 'GA',
            'block' => 'Y',
            'lot_number' => '1',
            'property_type_id' => 'B',
            'status_id' => 'RK',
        ];
        $unit = $this->postJson('/api/v1/units', $unitPayload + ['resident_id' => $residentId])
            ->assertCreated()->json('data');

        $this->getJson("/api/v1/residents/{$residentId}")->assertJsonPath('data.unit_status', 'with_unit');
        $this->getJson("/api/v1/residents/{$neverLinkedId}")->assertJsonPath('data.unit_status', 'never_linked');

        $this->putJson("/api/v1/units/{$unit['id']}", $unitPayload + ['resident_id' => null, 'occupancy_id' => '4'])->assertOk();

        $this->getJson("/api/v1/residents/{$residentId}")
            ->assertJsonPath('data.unit_status', 'without_unit')
            ->assertJsonPath('data.unit_unlinked_at', fn ($value) => $value !== null);
        $this->assertNull(Unit::find($unit['id'])->occupancy_id);

        $ids = collect($this->getJson('/api/v1/residents?unit_status=without_unit')->assertOk()->json('data'))->pluck('id');
        $this->assertSame([$residentId], $ids->all());

        // Terhubung lagi ke unit -> bukan lagi Penghuni Tanpa Unit.
        $this->putJson("/api/v1/units/{$unit['id']}", $unitPayload + ['resident_id' => $residentId])->assertOk();
        $this->getJson("/api/v1/residents/{$residentId}")
            ->assertJsonPath('data.unit_status', 'with_unit')
            ->assertJsonPath('data.unit_unlinked_at', null);
    }

    public function test_resident_with_another_unit_is_not_marked_without_unit_when_one_link_is_cancelled(): void
    {
        $this->seed();

        Sanctum::actingAs(User::where('username', 'root')->first());

        $residentId = $this->postJson('/api/v1/residents', ['name' => 'Dua Unit'])
            ->assertCreated()->json('data.resident.id');

        $base = ['cluster_id' => 'GA', 'block' => 'X', 'property_type_id' => 'B', 'status_id' => 'RK'];
        $first = $this->postJson('/api/v1/units', $base + ['lot_number' => '1', 'resident_id' => $residentId])->json('data.id');
        $this->postJson('/api/v1/units', $base + ['lot_number' => '2', 'resident_id' => $residentId])->assertCreated();

        $this->deleteJson("/api/v1/units/{$first}")->assertOk();

        $this->getJson("/api/v1/residents/{$residentId}")->assertJsonPath('data.unit_status', 'with_unit');
    }
}
