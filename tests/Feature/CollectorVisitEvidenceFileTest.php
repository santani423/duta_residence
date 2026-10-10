<?php

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\CollectorAssignment;
use App\Models\CollectorVisit;
use App\Models\CollectorVisitEvidence;
use App\Models\Resident;
use App\Models\SupervisorAssignment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * GET /visit-evidence/{evidence}/file: berkas bukti kunjungan di-stream inline hanya untuk user
 * yang unit kunjungannya dalam cakupan (CollectionScopeService), dan `file_url` di JSON bukti.
 */
class CollectorVisitEvidenceFileTest extends TestCase
{
    use RefreshDatabase;

    private User $collectorQ;

    private User $collectorR;

    private CollectorVisit $visitQ;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed();

        Cluster::query()->create(['id' => 'ZQ', 'name' => 'Cluster Uji Q', 'monthly_rate' => 100000]);
        Cluster::query()->create(['id' => 'ZR', 'name' => 'Cluster Uji R', 'monthly_rate' => 100000]);

        $unitQ = $this->makeUnit('ZQ001', 'ZQ');
        $unitR = $this->makeUnit('ZR001', 'ZR');

        $this->collectorQ = $this->makeUser('collector');
        $this->collectorR = $this->makeUser('collector');
        $this->assignUnit($this->collectorQ, $unitQ);
        $this->assignUnit($this->collectorR, $unitR);

        $this->visitQ = CollectorVisit::query()->create([
            'unit_id' => $unitQ->id, 'collector_id' => $this->collectorQ->id, 'visit_date' => now(),
            'purpose' => 'Penagihan', 'status' => 'completed', 'lifecycle' => CollectorVisit::LIFECYCLE_IN_PROGRESS,
        ]);
    }

    private function makeUnit(string $id, string $clusterId): Unit
    {
        $resident = Resident::factory()->create();

        return Unit::factory()->create(['id' => $id, 'cluster_id' => $clusterId, 'block' => 'A', 'resident_id' => $resident->id]);
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function makeSupervisor(string $clusterId): User
    {
        $supervisor = $this->makeUser('supervisor');
        SupervisorAssignment::query()->create([
            'supervisor_id' => $supervisor->id, 'cluster_id' => $clusterId, 'is_active' => true, 'status' => 'active',
        ]);

        return $supervisor;
    }

    private function assignUnit(User $collector, Unit $unit): void
    {
        CollectorAssignment::query()->create([
            'collector_id' => $collector->id, 'scope_type' => 'unit', 'unit_id' => $unit->id,
            'is_active' => true, 'status' => 'active',
        ]);
    }

    /** Unggah tanda tangan sebagai collector pemilik unit; mengembalikan JSON bukti. */
    private function uploadSignature(): array
    {
        Sanctum::actingAs($this->collectorQ);

        return $this->post("/api/v1/visits/{$this->visitQ->id}/evidence", [
            'type' => 'signature',
            'file' => UploadedFile::fake()->image('signature.png', 120, 60),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');
    }

    private function getFile(User $user, int $evidenceId): TestResponse
    {
        Sanctum::actingAs($user);

        return $this->get("/api/v1/visit-evidence/{$evidenceId}/file", ['Accept' => 'application/json']);
    }

    public function test_owner_collector_streams_the_signature_inline_with_its_content_type(): void
    {
        $evidence = $this->uploadSignature();
        $this->assertSame("visit-evidence/{$evidence['id']}/file", $evidence['file_url']);

        $listed = collect($this->getJson("/api/v1/visits/{$this->visitQ->id}/evidence")->assertOk()->json('data'))
            ->firstWhere('id', $evidence['id']);
        $this->assertSame($evidence['file_url'], $listed['file_url']);

        $path = CollectorVisitEvidence::query()->findOrFail($evidence['id'])->file_path;
        $response = $this->getFile($this->collectorQ, $evidence['id'])
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('inline', $response->headers->get('Content-Disposition'));
        $this->assertSame(Storage::disk('public')->get($path), $response->streamedContent());
    }

    public function test_file_access_follows_the_unit_scope_of_the_visit(): void
    {
        $evidenceId = $this->uploadSignature()['id'];

        $adminEstate = $this->makeUser('admin_estate');
        $root = User::query()->where('username', 'root')->firstOrFail();

        // Collector lain (unit tidak ditugaskan) & supervisor cluster lain → 403.
        $this->getFile($this->collectorR, $evidenceId)->assertForbidden();
        $this->getFile($this->makeSupervisor('ZR'), $evidenceId)->assertForbidden();

        // Supervisor cluster unit & role full scope → 200.
        $this->getFile($this->makeSupervisor('ZQ'), $evidenceId)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->getFile($adminEstate, $evidenceId)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->getFile($root, $evidenceId)->assertOk();

        // Tanpa permission collector-evidence.view/download → 403 dari middleware.
        $this->getFile($this->makeUser('cs'), $evidenceId)->assertForbidden();
    }

    public function test_gps_rows_and_missing_files_return_404(): void
    {
        $gps = CollectorVisitEvidence::query()->create([
            'visit_id' => $this->visitQ->id, 'type' => 'gps', 'latitude' => -6.2, 'longitude' => 106.8, 'captured_at' => now(),
        ]);
        $missing = CollectorVisitEvidence::query()->create([
            'visit_id' => $this->visitQ->id, 'type' => 'photo', 'file_path' => 'collector-visit-evidence/hilang.jpg', 'captured_at' => now(),
        ]);

        $this->getFile($this->collectorQ, $gps->id)->assertNotFound()->assertJsonPath('message', 'Berkas bukti tidak ditemukan.');
        $this->getFile($this->collectorQ, $missing->id)->assertNotFound()->assertJsonPath('message', 'Berkas bukti tidak ditemukan.');

        $listed = collect($this->getJson("/api/v1/visits/{$this->visitQ->id}/evidence")->assertOk()->json('data'))->keyBy('id');
        $this->assertNull($listed[$gps->id]['file_url']);
        $this->assertSame("visit-evidence/{$missing->id}/file", $listed[$missing->id]['file_url']);
    }

    public function test_deleted_evidence_is_no_longer_served(): void
    {
        $evidenceId = $this->uploadSignature()['id'];
        CollectorVisitEvidence::query()->findOrFail($evidenceId)->delete();

        $this->getFile($this->collectorQ, $evidenceId)->assertNotFound();
    }

    public function test_script_capable_files_are_sent_as_binary_downloads(): void
    {
        Sanctum::actingAs($this->collectorQ);
        $evidenceId = $this->post("/api/v1/visits/{$this->visitQ->id}/evidence", [
            'type' => 'document',
            'file' => UploadedFile::fake()->createWithContent('bukti.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $response = $this->getFile($this->collectorQ, $evidenceId)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('attachment', $response->headers->get('Content-Disposition'));
    }
}
