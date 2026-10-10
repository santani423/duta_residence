<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CollectionActivity;
use App\Models\CollectorVisit;
use App\Models\CollectorVisitEvidence;
use App\Models\SupervisorAssignment;
use App\Models\User;
use App\Services\CollectionAccountService;
use App\Services\CollectorPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use RuntimeException;
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

    private function makeSupervisor(string $clusterId): User
    {
        $supervisor = User::factory()->create(['is_active' => true]);
        $supervisor->assignRole('supervisor');
        SupervisorAssignment::query()->create([
            'supervisor_id' => $supervisor->id, 'cluster_id' => $clusterId, 'is_active' => true, 'status' => 'active',
        ]);

        return $supervisor;
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

    /** Resident + unit baru dengan collector yang ditugaskan; request berikutnya sebagai collector itu. */
    private function collectorWithUnit(string $lotSeed): array
    {
        $scope = $this->createResidentAndUnit($lotSeed);
        $collector = $this->makeCollector();
        $this->assignCollectorToUnit($collector, $scope['unit_id']);

        Sanctum::actingAs($collector);

        return [...$scope, 'collector' => $collector];
    }

    private function updatePayload(string $status): array
    {
        return [
            'visit_date' => now()->toDateTimeString(),
            'purpose' => 'Penagihan',
            'status' => $status,
        ];
    }

    private function createVisit(string $unitId, string $status = 'completed', array $overrides = []): TestResponse
    {
        return $this->postJson("/api/v1/units/{$unitId}/visits", [...$this->updatePayload($status), ...$overrides]);
    }

    private function uploadEvidence(int $visitId, string $type = 'signature'): TestResponse
    {
        return $this->post("/api/v1/visits/{$visitId}/evidence", [
            'type' => $type,
            'file' => UploadedFile::fake()->image("{$type}.png"),
        ], ['Accept' => 'application/json']);
    }

    /** @return array<string, string> event => summary timeline penagihan visit ini */
    private function timeline(int $visitId): array
    {
        return CollectionActivity::query()
            ->where('subject_type', (new CollectorVisit)->getMorphClass())
            ->where('subject_id', $visitId)
            ->orderBy('id')
            ->pluck('summary', 'event')
            ->all();
    }

    /** @return array{0: string, 1: bool, 2: bool} lifecycle, has_signature, awaiting_signature */
    private function signatureState(array $visit): array
    {
        return [$visit['lifecycle'], $visit['has_signature'], $visit['awaiting_signature']];
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

        // Kunjungan "Tidak Ada Jawaban" sudah final sejak dicatat, jadi PUT hanya memperbarui hasilnya.
        $this->putJson("/api/v1/visits/{$visitId}", $this->updatePayload('completed'))
            ->assertOk()
            ->assertJsonPath('message', 'Kunjungan berhasil diperbarui.')
            ->assertJsonFragment(['status' => 'completed']);

        $this->assertDatabaseHas('collector_visits', [
            'id' => $visitId,
            'status' => 'completed',
            'lifecycle' => 'completed',
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

        // Creating a "completed" visit only saves it as awaiting the resident's signature; the
        // PUT gate still refuses to finish it until a signature exists.
        $visitId = $this->postJson("/api/v1/units/{$scope['unit_id']}/visits", [
            'visit_date' => now()->toDateTimeString(),
            'purpose' => 'Penagihan',
            'status' => 'completed',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/visits/{$visitId}", $this->updatePayload('completed'))
            ->assertStatus(422);

        $this->assertDatabaseHas('collector_visits', ['id' => $visitId, 'lifecycle' => 'in_progress', 'finished_at' => null]);
    }

    public function test_completed_visit_is_saved_awaiting_the_resident_signature(): void
    {
        Storage::fake('public');
        $this->seed();
        ['unit_id' => $unitId, 'collector' => $collector] = $this->collectorWithUnit('SG904');
        $visitDate = now()->subMinutes(10)->startOfSecond()->toDateTimeString();

        $visitId = $this->createVisit($unitId, 'completed', ['visit_date' => $visitDate])
            ->assertCreated()
            ->assertJsonPath('message', 'Kunjungan tersimpan. Minta tanda tangan penghuni untuk menyelesaikannya.')
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.lifecycle', 'in_progress')
            ->assertJsonPath('data.awaiting_signature', true)
            ->assertJsonPath('data.has_signature', false)
            ->assertJsonPath('data.finished_at', null)
            ->assertJsonPath('data.unit.cluster.id', 'GA')
            ->assertJsonPath('data.collector.id', $collector->id)
            ->json('data.id');

        $visit = CollectorVisit::query()->findOrFail($visitId);
        $this->assertTrue($visit->isAwaitingSignature());
        $this->assertSame($visitDate, $visit->started_at->toDateTimeString());
        $this->assertNull($visit->finished_at);

        $this->assertSame(
            ['in_progress' => 'Kunjungan dimulai: Penagihan — menunggu tanda tangan penghuni'],
            $this->timeline($visitId),
        );
    }

    public function test_other_outcomes_are_recorded_as_finished_visits(): void
    {
        Storage::fake('public');
        $this->seed();
        ['unit_id' => $unitId] = $this->collectorWithUnit('SG905');
        $visitDate = now()->subHour()->startOfSecond()->toDateTimeString();

        foreach (['no_answer', 'refused', 'rescheduled'] as $status) {
            $visitId = $this->createVisit($unitId, $status, ['visit_date' => $visitDate])
                ->assertCreated()
                ->assertJsonPath('message', 'Kunjungan berhasil dicatat.')
                ->assertJsonPath('data.lifecycle', 'completed')
                ->assertJsonPath('data.awaiting_signature', false)
                ->assertJsonPath('data.has_signature', false)
                ->json('data.id');

            $visit = CollectorVisit::query()->findOrFail($visitId);
            $this->assertSame($visitDate, $visit->finished_at->toDateTimeString());
            $this->assertSame(['created' => "Kunjungan: Penagihan ({$status})"], $this->timeline($visitId));
        }
    }

    public function test_resident_signature_finishes_an_awaiting_visit(): void
    {
        Storage::fake('public');
        $this->seed();
        ['unit_id' => $unitId, 'collector' => $collector] = $this->collectorWithUnit('SG906');
        $visitId = $this->createVisit($unitId)->assertCreated()->json('data.id');

        $response = $this->uploadEvidence($visitId)
            ->assertCreated()
            ->assertJsonPath('message', 'Tanda tangan tersimpan. Kunjungan selesai.')
            ->assertJsonPath('data.type', 'signature')
            ->assertJsonPath('data.uploader.id', $collector->id)
            ->assertJsonPath('data.visit.id', $visitId)
            ->assertJsonPath('data.visit.lifecycle', 'completed')
            ->assertJsonPath('data.visit.awaiting_signature', false)
            ->assertJsonPath('data.visit.has_signature', true)
            ->assertJsonPath('data.visit.unit.cluster.id', 'GA');
        $this->assertNotNull($response->json('data.visit.finished_at'));
        $this->assertSame("visit-evidence/{$response->json('data.id')}/file", $response->json('data.file_url'));

        $visit = CollectorVisit::query()->findOrFail($visitId);
        $this->assertSame(CollectorVisit::LIFECYCLE_COMPLETED, $visit->lifecycle);
        $this->assertNotNull($visit->finished_at);
        $this->assertSame($collector->id, $visit->updated_by);
        $this->assertDatabaseHas('audit_logs', ['activity' => 'collector_visit_completed', 'entity_id' => (string) $visitId]);
        $this->assertSame([
            'in_progress' => 'Kunjungan dimulai: Penagihan — menunggu tanda tangan penghuni',
            'completed' => 'Kunjungan selesai dan ditandatangani penghuni: Penagihan',
        ], $this->timeline($visitId));

        // Tanda tangan ulang hanya menambah baris bukti; waktu selesai & timeline tidak berubah.
        $finishedAt = $visit->finished_at->toDateTimeString();
        $this->travel(5)->minutes();

        $this->uploadEvidence($visitId)
            ->assertCreated()
            ->assertJsonPath('message', 'Tanda tangan tersimpan. Kunjungan selesai.')
            ->assertJsonPath('data.visit.lifecycle', 'completed');

        $this->assertSame(2, CollectorVisitEvidence::query()->where('visit_id', $visitId)->where('type', 'signature')->count());
        $this->assertSame($finishedAt, $visit->refresh()->finished_at->toDateTimeString());
        $this->assertCount(2, $this->timeline($visitId));
        $this->assertSame(1, AuditLog::query()->where('activity', 'collector_visit_completed')->where('entity_id', (string) $visitId)->count());
    }

    public function test_other_evidence_does_not_finish_an_awaiting_visit(): void
    {
        Storage::fake('public');
        $this->seed();
        ['unit_id' => $unitId] = $this->collectorWithUnit('SG907');
        $visitId = $this->createVisit($unitId)->assertCreated()->json('data.id');

        $this->uploadEvidence($visitId, 'photo')
            ->assertCreated()
            ->assertJsonPath('message', 'Bukti kunjungan berhasil diunggah.')
            ->assertJsonPath('data.visit.lifecycle', 'in_progress')
            ->assertJsonPath('data.visit.awaiting_signature', true)
            ->assertJsonPath('data.visit.has_signature', false);

        $this->assertDatabaseHas('collector_visits', ['id' => $visitId, 'lifecycle' => 'in_progress', 'finished_at' => null]);
    }

    public function test_failed_signature_write_keeps_the_visit_awaiting_so_the_phone_can_retry(): void
    {
        Storage::fake('public');
        $this->seed();
        ['unit_id' => $unitId] = $this->collectorWithUnit('SG915');
        $visitId = $this->createVisit($unitId)->assertCreated()->json('data.id');

        // Disk public (throw => false) mengembalikan false bila berkas gagal ditulis.
        $disk = Storage::disk('public');
        $disk->makeDirectory('collector-visit-evidence');
        $directory = $disk->path('collector-visit-evidence');
        chmod($directory, 0555);

        try {
            if (is_writable($directory)) {
                $this->markTestSkipped('Direktori read-only tetap dapat ditulis (mis. dijalankan sebagai root).');
            }

            $this->uploadEvidence($visitId)
                ->assertStatus(500)
                ->assertJsonPath('message', 'Berkas bukti gagal disimpan. Silakan coba lagi.');
        } finally {
            chmod($directory, 0755);
        }

        $this->assertDatabaseMissing('collector_visit_evidence', ['visit_id' => $visitId]);
        $visit = CollectorVisit::query()->findOrFail($visitId);
        $this->assertTrue($visit->isAwaitingSignature());
        $this->assertNull($visit->finished_at);
        $this->assertArrayNotHasKey('completed', $this->timeline($visitId));

        // Setelah penyimpanan pulih, unggah ulang dari HP menyelesaikan kunjungan seperti biasa.
        $this->uploadEvidence($visitId)
            ->assertCreated()
            ->assertJsonPath('message', 'Tanda tangan tersimpan. Kunjungan selesai.')
            ->assertJsonPath('data.visit.lifecycle', 'completed');
    }

    public function test_put_completed_finishes_a_visit_that_already_has_a_signature(): void
    {
        Storage::fake('public');
        $this->seed();
        ['unit_id' => $unitId, 'collector' => $collector] = $this->collectorWithUnit('SG908');
        $visitId = $this->createVisit($unitId)->assertCreated()->json('data.id');

        // Tanda tangan yang tersimpan tanpa lewat endpoint unggah (mis. data lama/sinkronisasi).
        CollectorVisitEvidence::query()->create([
            'visit_id' => $visitId, 'type' => 'signature', 'file_path' => 'collector-visit-evidence/lama.png',
            'captured_at' => now(), 'uploaded_by' => $collector->id,
        ]);

        $this->putJson("/api/v1/visits/{$visitId}", $this->updatePayload('completed'))
            ->assertOk()
            ->assertJsonPath('message', 'Kunjungan berhasil diselesaikan.')
            ->assertJsonPath('data.lifecycle', 'completed')
            ->assertJsonPath('data.awaiting_signature', false)
            ->assertJsonPath('data.has_signature', true)
            ->assertJsonPath('data.unit.cluster.id', 'GA');

        $visit = CollectorVisit::query()->findOrFail($visitId);
        $this->assertNotNull($visit->finished_at);
        $this->assertSame($collector->id, $visit->updated_by);
        $this->assertSame('Kunjungan selesai dan ditandatangani penghuni: Penagihan', $this->timeline($visitId)['completed']);

        // Kunjungan yang sudah selesai hanya diperbarui; waktu selesainya tetap.
        $finishedAt = $visit->finished_at->toDateTimeString();
        $this->travel(5)->minutes();
        $this->putJson("/api/v1/visits/{$visitId}", $this->updatePayload('completed'))
            ->assertOk()
            ->assertJsonPath('message', 'Kunjungan berhasil diperbarui.');
        $this->assertSame($finishedAt, $visit->refresh()->finished_at->toDateTimeString());
    }

    public function test_put_with_another_outcome_finalizes_the_visit_without_a_signature(): void
    {
        Storage::fake('public');
        $this->seed();
        ['unit_id' => $unitId] = $this->collectorWithUnit('SG909');
        $visitId = $this->createVisit($unitId)->assertCreated()->json('data.id');

        $this->putJson("/api/v1/visits/{$visitId}", $this->updatePayload('refused'))
            ->assertOk()
            ->assertJsonPath('message', 'Kunjungan berhasil diselesaikan.')
            ->assertJsonPath('data.status', 'refused')
            ->assertJsonPath('data.lifecycle', 'completed')
            ->assertJsonPath('data.awaiting_signature', false)
            ->assertJsonPath('data.has_signature', false);

        $this->assertNotNull(CollectorVisit::query()->findOrFail($visitId)->finished_at);
        $this->assertSame('Kunjungan: Penagihan (refused)', $this->timeline($visitId)['completed']);
    }

    public function test_legacy_visit_without_signature_is_finished_not_awaiting(): void
    {
        Storage::fake('public');
        $this->seed();
        $scope = $this->collectorWithUnit('SG910');

        // Data lama: dicatat sebelum alur tanda tangan → lifecycle default DB "completed", tanpa tanda tangan.
        $legacy = CollectorVisit::query()->create([
            'unit_id' => $scope['unit_id'], 'collector_id' => $scope['collector']->id,
            'visit_date' => now()->subWeek(), 'purpose' => 'Penagihan', 'status' => 'completed',
        ]);

        $row = collect($this->getJson("/api/v1/residents/{$scope['resident_id']}/visits")->assertOk()->json('data'))
            ->firstWhere('id', $legacy->id);

        $this->assertSame(['completed', false, false], $this->signatureState($row));
        $this->assertSame(0, $row['evidence_count']);
    }

    public function test_visit_index_reports_lifecycle_signature_state_and_evidence_count(): void
    {
        Storage::fake('public');
        $this->seed();
        $scope = $this->collectorWithUnit('SG911');

        $awaitingId = $this->createVisit($scope['unit_id'])->assertCreated()->json('data.id');
        $this->uploadEvidence($awaitingId, 'photo')->assertCreated();

        $signedId = $this->createVisit($scope['unit_id'])->assertCreated()->json('data.id');
        $this->uploadEvidence($signedId, 'photo')->assertCreated();
        $this->uploadEvidence($signedId)->assertCreated();
        // Bukti yang sudah dihapus tidak dihitung.
        CollectorVisitEvidence::query()->findOrFail($this->uploadEvidence($signedId, 'document')->json('data.id'))->delete();

        $rows = collect($this->getJson("/api/v1/residents/{$scope['resident_id']}/visits?unit_id={$scope['unit_id']}")->assertOk()->json('data'))
            ->keyBy('id');

        $this->assertSame(['in_progress', false, true], $this->signatureState($rows[$awaitingId]));
        $this->assertSame(1, $rows[$awaitingId]['evidence_count']);
        $this->assertSame(['completed', true, false], $this->signatureState($rows[$signedId]));
        $this->assertSame(2, $rows[$signedId]['evidence_count']);
    }

    public function test_awaiting_visit_counts_as_visit_and_last_contact_only_after_signing(): void
    {
        Storage::fake('public');
        $this->seed();
        ['unit_id' => $unitId, 'collector' => $collector] = $this->collectorWithUnit('SG912');

        $visitCount = fn (): int => app(CollectorPerformanceService::class)
            ->achievementFor($collector, 'monthly', now()->toDateString())['visit_count'];
        $lastContactAt = fn () => app(CollectionAccountService::class)->refresh($unitId)->last_contact_at;

        $visitId = $this->createVisit($unitId)->assertCreated()->json('data.id');

        $this->assertSame(0, $visitCount());
        $this->assertNull($lastContactAt());

        $this->uploadEvidence($visitId)->assertCreated();

        $this->assertSame(1, $visitCount());
        $this->assertEquals(CollectorVisit::query()->findOrFail($visitId)->finished_at, $lastContactAt());
    }

    public function test_visit_kpis_outside_performance_service_skip_awaiting_visits_until_signed(): void
    {
        Storage::fake('public');
        $this->seed();
        ['unit_id' => $unitId, 'collector' => $collector] = $this->collectorWithUnit('SG916');
        $visitId = $this->createVisit($unitId)->assertCreated()->json('data.id');
        $supervisor = $this->makeSupervisor('GA');

        // metricsFor() gagal → detail collector memakai jalur cadangan metrik bulan berjalan.
        Exceptions::fake([RuntimeException::class]);
        $this->partialMock(CollectorPerformanceService::class, fn (MockInterface $mock) => $mock
            ->shouldReceive('metricsFor')->andThrow(new RuntimeException('Metrik kinerja tidak tersedia.')));

        // [Kunjungan Hari Ini di daftar collector supervisor, kunjungan bulan ini di detail collector,
        //  kartu Kunjungan Hari Ini di dashboard supervisor]
        $kpis = function () use ($supervisor, $collector): array {
            Sanctum::actingAs($supervisor);
            $today = collect($this->getJson('/api/v1/supervisor/collectors?per_page=100')->assertOk()->json('data'))
                ->firstWhere('id', $collector->id)['today_visit_count'];
            $dashboard = $this->getJson('/api/v1/supervisor/dashboard')->assertOk()->json('data.visits_today');

            Sanctum::actingAs(User::where('username', 'root')->first());
            $month = $this->getJson("/api/v1/collectors/{$collector->id}")->assertOk()->json('data.summary.visit_count');

            return [$today, $month, $dashboard];
        };

        $this->assertSame([0, 0, 0], $kpis());

        Sanctum::actingAs($collector);
        $this->uploadEvidence($visitId)->assertCreated();

        $this->assertSame([1, 1, 1], $kpis());
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Metrik kinerja tidak tersedia.');
    }

    public function test_collector_detail_payloads_expose_signature_state_of_recent_visits(): void
    {
        Storage::fake('public');
        $this->seed();
        $scope = $this->collectorWithUnit('SG913');
        $collector = $scope['collector'];

        $awaitingId = $this->createVisit($scope['unit_id'])->assertCreated()->json('data.id');
        $signedId = $this->createVisit($scope['unit_id'], 'completed', ['visit_date' => now()->subDay()->toDateTimeString()])
            ->assertCreated()->json('data.id');
        $this->uploadEvidence($signedId)->assertCreated();
        $supervisor = $this->makeSupervisor('GA');

        $assertRecentVisits = function (array $recentVisits) use ($awaitingId, $signedId, $scope) {
            $visits = collect($recentVisits)->keyBy('id');

            $this->assertSame(['in_progress', false, true], $this->signatureState($visits[$awaitingId]));
            $this->assertSame(['completed', true, false], $this->signatureState($visits[$signedId]));
            $this->assertSame($scope['resident_id'], $visits[$awaitingId]['unit']['resident_id']);
        };

        Sanctum::actingAs(User::where('username', 'root')->first());
        $assertRecentVisits($this->getJson("/api/v1/collectors/{$collector->id}")->assertOk()->json('data.recent_visits'));

        Sanctum::actingAs($supervisor);
        $assertRecentVisits($this->getJson("/api/v1/supervisor/collectors/{$collector->id}")->assertOk()->json('data.recent_visits'));
    }

    public function test_supervisor_collector_detail_only_lists_visits_in_the_supervisor_clusters(): void
    {
        Storage::fake('public');
        $this->seed();
        $scope = $this->collectorWithUnit('SG914');
        $collector = $scope['collector'];
        $insideId = $this->createVisit($scope['unit_id'])->assertCreated()->json('data.id');

        // Kolektor yang sama juga memegang unit di cluster AL, di luar wewenang supervisor GA.
        Sanctum::actingAs(User::where('username', 'root')->first());
        $residentId = $this->postJson('/api/v1/residents', ['name' => 'Outside Cluster Resident'])->assertCreated()->json('data.resident.id');
        $outsideUnitId = $this->postJson('/api/v1/units', [
            'resident_id' => $residentId, 'cluster_id' => 'AL', 'block' => 'Z', 'lot_number' => '14',
            'property_type_id' => 'B', 'occupancy_id' => '1', 'status_id' => 'AK',
        ])->assertCreated()->json('data.id');
        $this->assignCollectorToUnit($collector, $outsideUnitId);
        Sanctum::actingAs($collector);
        $outsideId = $this->createVisit($outsideUnitId)->assertCreated()->json('data.id');

        Sanctum::actingAs($this->makeSupervisor('GA'));
        $data = $this->getJson("/api/v1/supervisor/collectors/{$collector->id}")->assertOk()->json('data');
        $this->assertSame([$insideId], collect($data['recent_visits'])->pluck('id')->all());
        $this->assertSame(1, $data['summary']['total_visits']);

        Sanctum::actingAs(User::where('username', 'root')->first());
        $all = $this->getJson("/api/v1/supervisor/collectors/{$collector->id}")->assertOk()->json('data.recent_visits');
        $this->assertEqualsCanonicalizing([$insideId, $outsideId], collect($all)->pluck('id')->all());
    }
}
