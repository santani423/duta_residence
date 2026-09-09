<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectorVisitSignatureTest extends TestCase
{
    use RefreshDatabase;

    private function makeCollector(): User
    {
        $collector = User::factory()->create(['is_active' => true]);
        $collector->assignRole('collector');

        return $collector;
    }

    private function createResidentAndUnit(string $lotSeed): array
    {
        Sanctum::actingAs(User::where('username', 'root')->first());

        $residentId = $this->postJson('/api/v1/residents', ['name' => 'Signature Test Resident'])
            ->assertCreated()
            ->json('data.resident.id');

        $unitId = $this->postJson('/api/v1/units', [
            'resident_id' => $residentId,
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => substr($lotSeed, -2),
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
        ])->assertCreated()->json('data.id');

        return ['resident_id' => $residentId, 'unit_id' => $unitId];
    }

    private function assignCollectorToUnit(User $collector, string $unitId): void
    {
        Sanctum::actingAs(User::where('username', 'root')->first());
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id,
            'scope_type' => 'unit',
            'unit_id' => $unitId,
        ])->assertCreated();
    }

    private function updatePayload(string $status): array
    {
        return [
            'visit_date' => now()->toDateTimeString(),
            'purpose' => 'Penagihan',
            'status' => $status,
        ];
    }

    public function test_visit_cannot_be_marked_completed_without_a_resident_signature(): void
    {
        $this->seed();

        $scope = $this->createResidentAndUnit('SG901');
        $collector = $this->makeCollector();
        $this->assignCollectorToUnit($collector, $scope['unit_id']);

        Sanctum::actingAs($collector);

        $visitId = $this->postJson("/api/v1/units/{$scope['unit_id']}/visits", [
            'visit_date' => now()->toDateTimeString(),
            'purpose' => 'Penagihan',
            'status' => 'no_answer',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/visits/{$visitId}", $this->updatePayload('completed'))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Tanda tangan penghuni diperlukan sebelum kunjungan dapat diselesaikan.']);

        $this->assertDatabaseHas('collector_visits', [
            'id' => $visitId,
            'status' => 'no_answer',
        ]);
    }

    public function test_visit_can_be_completed_after_resident_signature_is_uploaded(): void
    {
        Storage::fake('public');
        $this->seed();

        $scope = $this->createResidentAndUnit('SG902');
        $collector = $this->makeCollector();
        $this->assignCollectorToUnit($collector, $scope['unit_id']);

        Sanctum::actingAs($collector);

        $visitId = $this->postJson("/api/v1/units/{$scope['unit_id']}/visits", [
            'visit_date' => now()->toDateTimeString(),
            'purpose' => 'Penagihan',
            'status' => 'no_answer',
        ])->assertCreated()->json('data.id');

        $this->post("/api/v1/visits/{$visitId}/evidence", [
            'type' => 'signature',
            'file' => UploadedFile::fake()->image('signature.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->putJson("/api/v1/visits/{$visitId}", $this->updatePayload('completed'))
            ->assertOk()
            ->assertJsonFragment(['status' => 'completed']);

        $this->assertDatabaseHas('collector_visits', [
            'id' => $visitId,
            'status' => 'completed',
        ]);

        $listed = $this->getJson("/api/v1/residents/{$scope['resident_id']}/visits?unit_id={$scope['unit_id']}")
            ->assertOk()
            ->json('data');
        $this->assertTrue(collect($listed)->firstWhere('id', $visitId)['has_signature']);
    }

    public function test_visit_created_directly_as_completed_still_requires_signature_before_it_can_be_reconfirmed(): void
    {
        $this->seed();

        $scope = $this->createResidentAndUnit('SG903');
        $collector = $this->makeCollector();
        $this->assignCollectorToUnit($collector, $scope['unit_id']);

        Sanctum::actingAs($collector);

        // Creation itself is unrestricted (existing behaviour, unchanged) - the
        // gate only applies when explicitly transitioning to completed via update.
        $visitId = $this->postJson("/api/v1/units/{$scope['unit_id']}/visits", [
            'visit_date' => now()->toDateTimeString(),
            'purpose' => 'Penagihan',
            'status' => 'completed',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/visits/{$visitId}", $this->updatePayload('completed'))
            ->assertStatus(422);
    }
}
