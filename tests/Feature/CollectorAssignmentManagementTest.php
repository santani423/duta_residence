<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CollectionAccountState;
use App\Models\CollectionActivity;
use App\Models\CollectorAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\CollectionAccountRefreshQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Stage B2 — manajemen penugasan collector: reassign, edit immutable, revoke, guard duplikat
 * lintas collector, scope supervisor, bulk/area assign, preview, unit belum ditugaskan.
 *
 * Data seed yang dipakai (database/data/pelanggan.csv): cluster 07 berisi 07001..07014, satu blok
 * "AL-" (07007 dipegang kolektor.budi via scope unit, 07001 hanya riwayat completed), 01 dipegang
 * budi (cluster), 02 siti, 03001-003 ahmad (unit), 04 blok AR1 dewi. supervisor.rina mengawasi
 * 01+02, supervisor.hendra 03+04. kolektor.rudi tidak aktif.
 */
class CollectorAssignmentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        app(CollectionAccountRefreshQueue::class)->flush();
    }

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function actingAsRoot(): User
    {
        $root = $this->user('root');
        Sanctum::actingAs($root);

        return $root;
    }

    private function makeCollector(string $username): User
    {
        $collector = User::factory()->create(['username' => $username, 'is_active' => true]);
        $collector->assignRole('collector');

        return $collector;
    }

    private function assignUnit(User $collector, string $unitId): int
    {
        return $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id, 'scope_type' => 'unit', 'unit_id' => $unitId,
        ])->assertCreated()->json('data.id');
    }

    // ---- Reassign ----

    public function test_reassign_requires_reason_and_works_without_notes(): void
    {
        $this->actingAsRoot();
        $a = $this->makeCollector('b2.reassign.a');
        $b = $this->makeCollector('b2.reassign.b');
        $assignmentId = $this->assignUnit($a, '07010');

        $this->postJson("/api/v1/collector-assignments/{$assignmentId}/reassign", [
            'new_collector_id' => $b->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');

        // Tanpa `notes` (frontend membuangnya bila kosong) → bukan 500 lagi.
        $newId = $this->postJson("/api/v1/collector-assignments/{$assignmentId}/reassign", [
            'new_collector_id' => $b->id,
            'reason' => 'Kolektor A dipindah wilayah.',
        ])->assertCreated()->json('data.id');

        $old = CollectorAssignment::find($assignmentId);
        $this->assertFalse($old->is_active);
        $this->assertSame('transferred', $old->status);
        $this->assertSame(now()->toDateString(), $old->end_date->toDateString());

        $new = CollectorAssignment::find($newId);
        $this->assertTrue($new->is_active);
        $this->assertSame($b->id, $new->collector_id);
        $this->assertSame($assignmentId, $new->reassigned_from_id);
        $this->assertSame('Kolektor A dipindah wilayah.', $new->reassign_reason);
        $this->assertSame('07010', $new->unit_id);

        $audit = AuditLog::query()->where('activity', 'collector_assignment_transferred')->latest('id')->first();
        $this->assertNotNull($audit);
        $this->assertSame($a->id, $audit->new_data['old_collector']['id']);
        $this->assertSame($b->id, $audit->new_data['new_collector']['id']);
        $this->assertSame('Kolektor A dipindah wilayah.', $audit->new_data['reason']);
        $this->assertArrayHasKey('changed_by', $audit->new_data);
        $this->assertArrayHasKey('changed_at', $audit->new_data);

        // Timeline akun penagihan mencatat penugasan & pemindahan (scope unit).
        $this->assertTrue(CollectionActivity::query()->where('unit_id', '07010')->where('type', CollectionActivity::TYPE_ASSIGNMENT)->where('event', 'assigned')->exists());
        $this->assertTrue(CollectionActivity::query()->where('unit_id', '07010')->where('type', CollectionActivity::TYPE_ASSIGNMENT)->where('event', 'transferred')->where('collector_id', $b->id)->exists());

        // Daftar penugasan menampilkan asal pemindahan & alasan.
        $row = collect($this->getJson("/api/v1/collector-assignments?collector_id={$b->id}")->assertOk()->json('data'))->firstWhere('id', $newId);
        $this->assertSame($a->name, $row['reassigned_from']['collector']['name']);
        $this->assertSame('Kolektor A dipindah wilayah.', $row['reassign_reason']);
        $this->assertSame($b->name, $row['collector']['name']);
        $this->assertNotNull($row['assigned_by']['name']);

        // Akses unit langsung berpindah.
        Sanctum::actingAs($a);
        $this->getJson('/api/v1/units/07010')->assertForbidden();
        Sanctum::actingAs($b);
        $this->getJson('/api/v1/units/07010')->assertOk();
    }

    public function test_reassign_rejects_inactive_source_inactive_target_same_collector_and_duplicate(): void
    {
        $this->actingAsRoot();
        $a = $this->makeCollector('b2.reassign.src');
        $b = $this->makeCollector('b2.reassign.dst');
        $assignmentId = $this->assignUnit($a, '07011');

        // Tujuan tidak aktif (kolektor.rudi: profil inactive).
        $this->postJson("/api/v1/collector-assignments/{$assignmentId}/reassign", [
            'new_collector_id' => $this->user('kolektor.rudi')->id, 'reason' => 'Uji tujuan tidak aktif',
        ])->assertUnprocessable()->assertJsonValidationErrors('new_collector_id');

        // Tujuan sama dengan kolektor saat ini.
        $this->postJson("/api/v1/collector-assignments/{$assignmentId}/reassign", [
            'new_collector_id' => $a->id, 'reason' => 'Uji kolektor yang sama',
        ])->assertUnprocessable()->assertJsonValidationErrors('new_collector_id');

        // Tujuan sudah memegang scope identik (data lama dengan duplikat lintas collector).
        CollectorAssignment::query()->create([
            'collector_id' => $b->id, 'scope_type' => 'unit', 'unit_id' => '07011',
            'is_active' => true, 'status' => 'active', 'start_date' => now()->toDateString(),
        ]);
        $this->postJson("/api/v1/collector-assignments/{$assignmentId}/reassign", [
            'new_collector_id' => $b->id, 'reason' => 'Uji guard duplikat',
        ])->assertUnprocessable()->assertJsonValidationErrors('new_collector_id');
        $this->assertTrue(CollectorAssignment::find($assignmentId)->is_active);

        // Sumber yang sudah tidak aktif tidak bisa dipindahkan.
        $this->deleteJson("/api/v1/collector-assignments/{$assignmentId}")->assertOk();
        $c = $this->makeCollector('b2.reassign.third');
        $this->postJson("/api/v1/collector-assignments/{$assignmentId}/reassign", [
            'new_collector_id' => $c->id, 'reason' => 'Uji sumber tidak aktif',
        ])->assertUnprocessable();
    }

    // ---- Create / update / revoke ----

    public function test_store_rejects_inactive_collector_and_scope_held_by_another_collector(): void
    {
        $this->actingAsRoot();
        $a = $this->makeCollector('b2.store.a');
        $b = $this->makeCollector('b2.store.b');

        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $this->user('kolektor.rudi')->id, 'scope_type' => 'unit', 'unit_id' => '07012',
        ])->assertUnprocessable()->assertJsonValidationErrors('collector_id');

        $id = $this->assignUnit($a, '07012');
        $created = CollectorAssignment::find($id);
        $this->assertSame(now()->toDateString(), $created->start_date->toDateString());
        $this->assertSame('normal', $created->priority);

        $response = $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $b->id, 'scope_type' => 'unit', 'unit_id' => '07012',
        ])->assertUnprocessable()->assertJsonValidationErrors('scope_type');
        $this->assertStringContainsString($a->name, $response->json('errors.scope_type.0'));

        // Overlap lintas level (cluster vs unit) tetap diizinkan.
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $b->id, 'scope_type' => 'cluster', 'cluster_id' => '07',
        ])->assertCreated();
    }

    public function test_update_only_accepts_schedule_fields_and_scope_is_immutable(): void
    {
        $this->actingAsRoot();
        $a = $this->makeCollector('b2.update.a');
        $b = $this->makeCollector('b2.update.b');
        $id = $this->assignUnit($a, '07013');

        $this->putJson("/api/v1/collector-assignments/{$id}", ['collector_id' => $b->id])
            ->assertUnprocessable()->assertJsonValidationErrors('collector_id');
        $this->putJson("/api/v1/collector-assignments/{$id}", ['scope_type' => 'unit', 'unit_id' => '07014'])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_id');

        // Payload persis dari EditAssignmentModal.
        $this->putJson("/api/v1/collector-assignments/{$id}", [
            'status' => 'active', 'priority' => 'urgent', 'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(), 'notes' => 'Prioritas naik',
        ])->assertOk()->assertJsonPath('data.priority', 'urgent');

        // Mengirim nilai cakupan yang sama tidak dianggap perubahan.
        $this->putJson("/api/v1/collector-assignments/{$id}", ['collector_id' => $a->id, 'scope_type' => 'unit', 'unit_id' => '07013', 'notes' => null])
            ->assertOk();

        $this->putJson("/api/v1/collector-assignments/{$id}", ['end_date' => now()->subYear()->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');

        $this->putJson("/api/v1/collector-assignments/{$id}", ['status' => 'completed'])->assertOk();
        $this->assertFalse(CollectorAssignment::find($id)->is_active);

        $this->putJson("/api/v1/collector-assignments/{$id}", ['status' => 'active'])->assertOk();
        $this->assertTrue(CollectorAssignment::find($id)->is_active);

        $this->putJson("/api/v1/collector-assignments/{$id}", ['status' => 'cancelled'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_dates_are_serialized_as_plain_dates_so_edit_round_trips_do_not_shift(): void
    {
        $this->actingAsRoot();
        $collector = $this->makeCollector('b2.dates');
        $start = now()->toDateString();
        $end = now()->addMonth()->toDateString();

        $id = $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $collector->id, 'scope_type' => 'unit', 'unit_id' => '07013',
            'start_date' => $start, 'end_date' => $end,
        ])->assertCreated()->assertJsonPath('data.start_date', $start)->assertJsonPath('data.end_date', $end)->json('data.id');

        $row = collect($this->getJson('/api/v1/collector-assignments?per_page=100')->assertOk()->json('data'))->firstWhere('id', $id);
        $this->assertSame($start, $row['start_date']);

        // Form Edit di web mengambil 10 karakter pertama lalu mengirimnya kembali.
        $this->putJson("/api/v1/collector-assignments/{$id}", [
            'priority' => 'urgent', 'start_date' => substr($row['start_date'], 0, 10), 'end_date' => substr($row['end_date'], 0, 10),
        ])->assertOk()->assertJsonPath('data.start_date', $start)->assertJsonPath('data.end_date', $end);
    }

    public function test_reactivating_a_completed_assignment_runs_the_duplicate_guard(): void
    {
        $this->actingAsRoot();
        $a = $this->makeCollector('b2.reactivate.a');
        $b = $this->makeCollector('b2.reactivate.b');
        $id = $this->assignUnit($a, '07002');

        $this->putJson("/api/v1/collector-assignments/{$id}", ['status' => 'completed'])->assertOk();
        $this->assignUnit($b, '07002');

        $this->putJson("/api/v1/collector-assignments/{$id}", ['status' => 'active'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_revoke_rejects_inactive_rows_and_cancelled_rows_are_not_editable(): void
    {
        $this->actingAsRoot();
        $a = $this->makeCollector('b2.revoke.a');
        $id = $this->assignUnit($a, '07003');

        $this->deleteJson("/api/v1/collector-assignments/{$id}")->assertOk();
        $row = CollectorAssignment::find($id);
        $this->assertSame('cancelled', $row->status);
        $this->assertFalse($row->is_active);

        $this->deleteJson("/api/v1/collector-assignments/{$id}")->assertUnprocessable();
        $this->putJson("/api/v1/collector-assignments/{$id}", ['priority' => 'high'])->assertUnprocessable();
    }

    // ---- Supervisor scope ----

    public function test_supervisor_only_sees_and_manages_assignments_in_their_clusters(): void
    {
        $rina = $this->user('supervisor.rina'); // 01 + 02
        $budi = $this->user('kolektor.budi');
        $ahmad = $this->user('kolektor.ahmad');
        $budiCluster = CollectorAssignment::query()->where('collector_id', $budi->id)->where('scope_type', 'cluster')->firstOrFail();
        $ahmadUnit = CollectorAssignment::query()->where('collector_id', $ahmad->id)->where('unit_id', '03001')->firstOrFail();

        Sanctum::actingAs($rina);
        $ids = collect($this->getJson('/api/v1/collector-assignments?per_page=100')->assertOk()->json('data'))->pluck('id');
        $this->assertContains($budiCluster->id, $ids);
        $this->assertNotContains($ahmadUnit->id, $ids);

        $newCollector = $this->makeCollector('b2.spv.collector');

        // Di luar cakupan → 403.
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $newCollector->id, 'scope_type' => 'unit', 'unit_id' => '03001',
        ])->assertForbidden();
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $newCollector->id, 'scope_type' => 'cluster', 'cluster_id' => '03',
        ])->assertForbidden();
        $this->putJson("/api/v1/collector-assignments/{$ahmadUnit->id}", ['priority' => 'high'])->assertForbidden();
        $this->deleteJson("/api/v1/collector-assignments/{$ahmadUnit->id}")->assertForbidden();
        $this->postJson("/api/v1/collector-assignments/{$ahmadUnit->id}/reassign", [
            'new_collector_id' => $newCollector->id, 'reason' => 'Uji di luar cakupan',
        ])->assertForbidden();
        $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'units', 'collector_id' => $newCollector->id, 'unit_ids' => ['01001', '03002'],
        ])->assertForbidden();
        $this->postJson('/api/v1/collection/assignments/preview', ['scope_type' => 'cluster', 'cluster_id' => '07'])->assertForbidden();

        // Di dalam cakupan → boleh.
        $this->postJson('/api/v1/collector-assignments', [
            'collector_id' => $newCollector->id, 'scope_type' => 'unit', 'unit_id' => '01002',
        ])->assertCreated();
        $this->putJson("/api/v1/collector-assignments/{$budiCluster->id}", ['priority' => 'urgent'])->assertOk();
    }

    // ---- Index filters ----

    public function test_index_filters_by_unit_cluster_currently_effective_and_search(): void
    {
        $this->actingAsRoot();
        $budi = $this->user('kolektor.budi');

        // unit_id → semua assignment yang mencakup unit (scope cluster 01 milik budi).
        $rows = collect($this->getJson('/api/v1/collector-assignments?unit_id=01003')->assertOk()->json('data'));
        $this->assertTrue($rows->contains(fn ($row) => $row['scope_type'] === 'cluster' && $row['collector_id'] === $budi->id));

        // cluster_id=07 → 07001 (riwayat rudi) & 07007 tercakup; 01 tidak.
        $rows = collect($this->getJson('/api/v1/collector-assignments?cluster_id=07&per_page=100')->assertOk()->json('data'));
        $this->assertEqualsCanonicalizing(['07001', '07007', '07007'], $rows->pluck('unit_id')->all());

        $rows = collect($this->getJson('/api/v1/collector-assignments?cluster_id=07&currently_effective=1&per_page=100')->assertOk()->json('data'));
        $this->assertSame(['07007'], $rows->pluck('unit_id')->all());

        $rows = collect($this->getJson('/api/v1/collector-assignments?search=Ahmad&per_page=100')->assertOk()->json('data'));
        $this->assertNotEmpty($rows);
        $this->assertTrue($rows->every(fn ($row) => $row['collector']['name'] === 'Ahmad Fauzi'));

        $this->getJson('/api/v1/collector-assignments?per_page=1000')->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    // ---- Bulk / area ----

    public function test_bulk_units_creates_skips_reports_conflicts_and_transfers_with_reason(): void
    {
        $this->actingAsRoot();
        $target = $this->makeCollector('b2.bulk.target');
        $other = $this->makeCollector('b2.bulk.other');
        $this->assignUnit($target, '07005');
        $otherId = $this->assignUnit($other, '07006');

        $response = $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'units', 'collector_id' => $target->id, 'unit_ids' => ['07004', '07005', '07006'],
            'priority' => 'high',
        ])->assertCreated();

        $this->assertCount(1, $response->json('data.created'));
        $this->assertSame(['07005'], $response->json('data.skipped'));
        $this->assertSame([], $response->json('data.transferred'));
        $this->assertSame([[
            'unit_id' => '07006', 'collector_id' => $other->id, 'collector_name' => $other->name, 'assignment_id' => $otherId,
        ]], $response->json('data.conflicts'));

        $created = CollectorAssignment::find($response->json('data.created.0'));
        $this->assertSame('07004', $created->unit_id);
        $this->assertSame('high', $created->priority);
        $this->assertSame('unit', $created->scope_type);
        $this->assertTrue(AuditLog::query()->where('activity', 'collector_assignment_bulk')->exists());

        // State cache ikut diperbarui oleh observer setelah antrean di-flush.
        app(CollectionAccountRefreshQueue::class)->flush();
        $this->assertSame($target->id, (int) CollectionAccountState::query()->find('07004')?->collector_id);

        // Tidak ada yang baru → 200.
        $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'units', 'collector_id' => $target->id, 'unit_ids' => ['07004'],
        ])->assertOk()->assertJsonPath('data.skipped', ['07004']);

        // Pindahkan konflik: wajib alasan.
        $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'units', 'collector_id' => $target->id, 'unit_ids' => ['07006'], 'transfer_conflicts' => true,
        ])->assertUnprocessable()->assertJsonValidationErrors('reason');

        $response = $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'units', 'collector_id' => $target->id, 'unit_ids' => ['07006'],
            'transfer_conflicts' => true, 'reason' => 'Penyeimbangan beban kerja',
        ])->assertCreated();

        $this->assertCount(1, $response->json('data.transferred'));
        $old = CollectorAssignment::find($otherId);
        $this->assertSame('transferred', $old->status);
        $new = CollectorAssignment::find($response->json('data.transferred.0'));
        $this->assertSame($target->id, $new->collector_id);
        $this->assertSame($otherId, $new->reassigned_from_id);
        $this->assertSame('Penyeimbangan beban kerja', $new->reassign_reason);

        app(CollectionAccountRefreshQueue::class)->flush();
        $this->assertSame($target->id, (int) CollectionAccountState::query()->find('07006')?->collector_id);
    }

    public function test_bulk_validates_collector_units_and_limits(): void
    {
        $this->actingAsRoot();
        $target = $this->makeCollector('b2.bulk.validate');

        $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'units', 'collector_id' => $this->user('kolektor.rudi')->id, 'unit_ids' => ['07008'],
        ])->assertUnprocessable()->assertJsonValidationErrors('collector_id');

        $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'units', 'collector_id' => $target->id, 'unit_ids' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_ids');

        $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'units', 'collector_id' => $target->id, 'unit_ids' => ['07008', 'XX999'],
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_ids');
        $this->assertFalse(CollectorAssignment::query()->where('unit_id', '07008')->exists());

        $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'units', 'collector_id' => $target->id,
            'unit_ids' => array_map(fn ($i) => 'U'.$i, range(1, 501)),
        ])->assertUnprocessable()->assertJsonValidationErrors('unit_ids');
    }

    public function test_bulk_area_creates_cluster_or_block_assignment_with_duplicate_guard(): void
    {
        $this->actingAsRoot();
        $a = $this->makeCollector('b2.area.a');
        $b = $this->makeCollector('b2.area.b');

        $id = $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'area', 'collector_id' => $a->id, 'cluster_id' => '07', 'block' => 'B',
        ])->assertCreated()->json('data.created.0');

        $row = CollectorAssignment::find($id);
        $this->assertSame('block', $row->scope_type);
        $this->assertSame('07', $row->cluster_id);
        $this->assertSame('B', $row->block);
        $this->assertNull($row->unit_id);

        $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'area', 'collector_id' => $a->id, 'cluster_id' => '07', 'block' => 'B',
        ])->assertUnprocessable();

        $response = $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'area', 'collector_id' => $b->id, 'cluster_id' => '07', 'block' => 'B',
        ])->assertUnprocessable()->assertJsonValidationErrors('scope_type');
        $this->assertStringContainsString($a->name, $response->json('errors.scope_type.0'));

        $id = $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'area', 'collector_id' => $b->id, 'cluster_id' => '07',
        ])->assertCreated()->json('data.created.0');
        $this->assertSame('cluster', CollectorAssignment::find($id)->scope_type);

        $this->postJson('/api/v1/collection/assignments/bulk', [
            'mode' => 'area', 'collector_id' => $b->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('cluster_id');
    }

    // ---- Preview ----

    public function test_preview_summarises_scope_units_and_current_collectors(): void
    {
        $this->actingAsRoot();
        $budi = $this->user('kolektor.budi');

        $data = $this->postJson('/api/v1/collection/assignments/preview', ['scope_type' => 'cluster', 'cluster_id' => '07'])
            ->assertOk()->json('data');
        $this->assertSame(14, $data['unit_count']);
        $this->assertSame(13, $data['unassigned_count']);
        $this->assertSame([['collector_id' => $budi->id, 'name' => $budi->name, 'unit_count' => 1]], $data['current_collectors']);
        foreach (['outstanding_total', 'overdue_accounts', 'critical_accounts'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }

        $data = $this->postJson('/api/v1/collection/assignments/preview', ['scope_type' => 'units', 'unit_ids' => ['07007', '07009', 'NOPE1']])
            ->assertOk()->json('data');
        $this->assertSame(2, $data['unit_count']);
        $this->assertSame(1, $data['unassigned_count']);

        $data = $this->postJson('/api/v1/collection/assignments/preview', ['scope_type' => 'block', 'cluster_id' => '01', 'block' => 'D1'])
            ->assertOk()->json('data');
        $this->assertSame(0, $data['unassigned_count']);
        $this->assertSame($budi->id, $data['current_collectors'][0]['collector_id']);

        $this->postJson('/api/v1/collection/assignments/preview', ['scope_type' => 'units'])
            ->assertUnprocessable()->assertJsonValidationErrors('unit_ids');
        $this->postJson('/api/v1/collection/assignments/preview', ['scope_type' => 'block', 'cluster_id' => '07'])
            ->assertUnprocessable()->assertJsonValidationErrors('block');
    }

    public function test_preview_outstanding_comes_from_account_state_cache(): void
    {
        $this->actingAsRoot();
        CollectionAccountState::query()->updateOrCreate(['unit_id' => '07002'], [
            'outstanding_total' => 1500000, 'aging_days' => 45, 'priority_level' => 'critical', 'status' => 'overdue',
        ]);
        CollectionAccountState::query()->updateOrCreate(['unit_id' => '07003'], [
            'outstanding_total' => 0, 'aging_days' => 0, 'priority_level' => 'normal', 'status' => 'paid',
        ]);

        $data = $this->postJson('/api/v1/collection/assignments/preview', ['scope_type' => 'units', 'unit_ids' => ['07002', '07003']])
            ->assertOk()->json('data');
        $this->assertEquals(1500000, $data['outstanding_total']);
        $this->assertSame(1, $data['overdue_accounts']);
        $this->assertSame(1, $data['critical_accounts']);
    }

    // ---- Unassigned units ----

    public function test_unassigned_units_excludes_covered_units_and_supports_filters(): void
    {
        $this->actingAsRoot();

        $items = collect($this->getJson('/api/v1/collection/assignments/unassigned-units?cluster_id=07&per_page=100')->assertOk()->json('data'));
        $ids = $items->pluck('unit_id');
        $this->assertCount(13, $ids);
        $this->assertNotContains('07007', $ids);
        $this->assertContains('07001', $ids); // hanya riwayat completed → tetap belum ditugaskan

        $first = $items->firstWhere('unit_id', '07001');
        foreach (['unit_id', 'cluster_id', 'cluster_name', 'block', 'lot_number', 'customer', 'outstanding_total', 'status', 'priority_level'] as $key) {
            $this->assertArrayHasKey($key, $first);
        }
        $this->assertSame('Ruko Alexandrite', $first['cluster_name']);

        // Unit dalam cluster yang ditugaskan (01 milik budi) tidak muncul.
        $this->assertSame(0, $this->getJson('/api/v1/collection/assignments/unassigned-units?cluster_id=01')->assertOk()->json('meta.total'));

        $blockJ2 = collect($this->getJson('/api/v1/collection/assignments/unassigned-units?cluster_id=03&block=J2&per_page=100')->json('data'));
        $this->assertTrue($blockJ2->every(fn ($row) => $row['block'] === 'J2'));
        $this->assertNotEmpty($blockJ2);

        // has_outstanding memakai cache state.
        CollectionAccountState::query()->updateOrCreate(['unit_id' => '07004'], ['outstanding_total' => 250000, 'aging_days' => 10]);
        $withDebt = collect($this->getJson('/api/v1/collection/assignments/unassigned-units?cluster_id=07&has_outstanding=1&per_page=100')->json('data'));
        $this->assertContains('07004', $withDebt->pluck('unit_id'));
        $this->assertTrue($withDebt->every(fn ($row) => $row['outstanding_total'] > 0));

        $this->getJson('/api/v1/collection/assignments/unassigned-units?search=07004')
            ->assertOk()->assertJsonPath('data.0.unit_id', '07004');

        // Setelah ditugaskan, unit langsung hilang dari daftar (cakupan dihitung live).
        $this->assignUnit($this->makeCollector('b2.unassigned.c'), '07004');
        $this->assertNotContains('07004', collect($this->getJson('/api/v1/collection/assignments/unassigned-units?cluster_id=07&per_page=100')->json('data'))->pluck('unit_id'));
    }

    public function test_unassigned_units_are_scoped_to_supervisor_clusters(): void
    {
        Sanctum::actingAs($this->user('supervisor.hendra')); // 03 (03001-003 milik ahmad) + 04 (blok AR1 milik dewi)

        $response = $this->getJson('/api/v1/collection/assignments/unassigned-units?per_page=100')->assertOk();
        $expected = Unit::query()
            ->whereIn('cluster_id', ['03', '04'])
            ->whereNotIn('id', ['03001', '03002', '03003'])
            ->where(fn ($q) => $q->where('cluster_id', '03')->orWhere('block', '!=', 'AR1'))
            ->count();
        $this->assertSame($expected, $response->json('meta.total'));

        $rows = collect($response->json('data'));
        $this->assertNotEmpty($rows);
        $this->assertTrue($rows->every(fn ($row) => in_array($row['cluster_id'], ['03', '04'], true)
            && ! in_array($row['unit_id'], ['03001', '03002', '03003'], true)
            && ! ($row['cluster_id'] === '04' && $row['block'] === 'AR1')));
    }
}
