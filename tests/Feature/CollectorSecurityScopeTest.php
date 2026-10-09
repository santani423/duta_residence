<?php

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\CollectionLetter;
use App\Models\CollectorAssignment;
use App\Models\CollectorLocation;
use App\Models\CollectorReminder;
use App\Models\CollectorVisit;
use App\Models\CollectorVisitEvidence;
use App\Models\EmergencyAlert;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\Resident;
use App\Models\SupervisorAssignment;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Stage B5 — perbaikan keamanan endpoint collector lama: semua list/detail di-scope lewat
 * CollectionScopeService (collector → unit/dirinya sendiri, supervisor → clusternya,
 * full scope → semua), integritas status PTP, dan SOS collector tampil ke supervisor.
 */
class CollectorSecurityScopeTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unitQ;

    private Unit $unitR;

    private User $collectorQ;

    private User $collectorR;

    private User $supervisorQ;

    private User $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Cluster::query()->create(['id' => 'ZQ', 'name' => 'Cluster Uji Q', 'monthly_rate' => 100000]);
        Cluster::query()->create(['id' => 'ZR', 'name' => 'Cluster Uji R', 'monthly_rate' => 100000]);

        $this->unitQ = $this->makeUnit('ZQ001', 'ZQ');
        $this->unitR = $this->makeUnit('ZR001', 'ZR');

        $this->collectorQ = $this->makeUser('collector');
        $this->collectorR = $this->makeUser('collector');
        $this->assignUnit($this->collectorQ, $this->unitQ);
        $this->assignUnit($this->collectorR, $this->unitR);

        $this->supervisorQ = $this->makeUser('supervisor');
        SupervisorAssignment::query()->create([
            'supervisor_id' => $this->supervisorQ->id, 'cluster_id' => 'ZQ', 'is_active' => true, 'status' => 'active',
        ]);

        $this->root = User::query()->where('username', 'root')->firstOrFail();
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

    private function assignUnit(User $collector, Unit $unit): void
    {
        CollectorAssignment::query()->create([
            'collector_id' => $collector->id, 'scope_type' => 'unit', 'unit_id' => $unit->id,
            'is_active' => true, 'status' => 'active',
        ]);
    }

    private function ids($response, string $key = 'data'): array
    {
        return collect($response->json($key))->pluck('id')->all();
    }

    // ── Lokasi collector ────────────────────────────────────────────────────────────────

    public function test_collector_locations_are_scoped_per_role(): void
    {
        $pingQ = CollectorLocation::query()->create(['collector_id' => $this->collectorQ->id, 'latitude' => -6.2, 'longitude' => 106.8, 'recorded_at' => now()]);
        $pingR = CollectorLocation::query()->create(['collector_id' => $this->collectorR->id, 'latitude' => -6.3, 'longitude' => 106.9, 'recorded_at' => now()]);

        // Collector: hanya ping dirinya sendiri, tidak boleh mengintip collector lain.
        Sanctum::actingAs($this->collectorQ);
        $this->assertSame([$pingQ->id], $this->ids($this->getJson('/api/v1/collector-locations')->assertOk()));
        $this->assertSame([$pingQ->id], $this->ids($this->getJson('/api/v1/collector-locations/latest')->assertOk()));
        $this->getJson("/api/v1/collector-locations?collector_id={$this->collectorR->id}")->assertForbidden();

        // Supervisor: hanya collector dalam clusternya.
        Sanctum::actingAs($this->supervisorQ);
        $this->assertSame([$pingQ->id], $this->ids($this->getJson('/api/v1/collector-locations')->assertOk()));
        $this->assertSame([$pingQ->id], $this->ids($this->getJson('/api/v1/collector-locations/latest')->assertOk()));
        $this->getJson("/api/v1/collector-locations?collector_id={$this->collectorR->id}")->assertForbidden();
        $this->getJson("/api/v1/collector-locations?collector_id={$this->collectorQ->id}&per_page=500")
            ->assertOk()->assertJsonPath('meta.per_page', 100);

        // Full scope: semua.
        Sanctum::actingAs($this->root);
        $all = $this->ids($this->getJson('/api/v1/collector-locations/latest')->assertOk());
        $this->assertContains($pingQ->id, $all);
        $this->assertContains($pingR->id, $all);
    }

    // ── Bukti kunjungan ─────────────────────────────────────────────────────────────────

    public function test_visit_evidence_index_and_destroy_are_scoped_to_the_visit_unit(): void
    {
        $visitQ = CollectorVisit::query()->create(['unit_id' => $this->unitQ->id, 'collector_id' => $this->collectorQ->id, 'visit_date' => now(), 'purpose' => 'Penagihan']);
        $visitR = CollectorVisit::query()->create(['unit_id' => $this->unitR->id, 'collector_id' => $this->collectorR->id, 'visit_date' => now(), 'purpose' => 'Penagihan']);
        $evidenceQ = CollectorVisitEvidence::query()->create(['visit_id' => $visitQ->id, 'type' => 'gps', 'latitude' => -6.2, 'longitude' => 106.8, 'captured_at' => now()]);
        $evidenceR = CollectorVisitEvidence::query()->create(['visit_id' => $visitR->id, 'type' => 'gps', 'latitude' => -6.3, 'longitude' => 106.9, 'captured_at' => now()]);

        Sanctum::actingAs($this->collectorQ);
        $this->assertSame([$evidenceQ->id], $this->ids($this->getJson("/api/v1/visits/{$visitQ->id}/evidence")->assertOk()));
        $this->getJson("/api/v1/visits/{$visitR->id}/evidence")->assertForbidden();

        $this->supervisorQ->givePermissionTo('collector-evidence.delete');
        Sanctum::actingAs($this->supervisorQ);
        $this->getJson("/api/v1/visits/{$visitQ->id}/evidence")->assertOk();
        $this->getJson("/api/v1/visits/{$visitR->id}/evidence")->assertForbidden();
        $this->deleteJson("/api/v1/visit-evidence/{$evidenceR->id}")->assertForbidden();
        $this->assertDatabaseHas('collector_visit_evidence', ['id' => $evidenceR->id]);
        $this->deleteJson("/api/v1/visit-evidence/{$evidenceQ->id}")->assertOk();

        Sanctum::actingAs($this->root);
        $this->getJson("/api/v1/visits/{$visitR->id}/evidence")->assertOk();
        $this->deleteJson("/api/v1/visit-evidence/{$evidenceR->id}")->assertOk();
    }

    // ── Surat penagihan ─────────────────────────────────────────────────────────────────

    public function test_collection_letters_index_and_download_are_scoped(): void
    {
        Storage::fake('public');
        $letterQ = $this->makeLetter($this->unitQ);
        $letterR = $this->makeLetter($this->unitR);

        Sanctum::actingAs($this->collectorQ);
        $this->assertSame([$letterQ->id], $this->ids($this->getJson('/api/v1/collection-letters')->assertOk()));

        // Unduh: izin diberikan langsung agar logika scope controller teruji.
        $this->collectorQ->givePermissionTo('collection-letters.download');
        $this->get("/api/v1/collection-letters/{$letterQ->id}/download")->assertOk();
        $this->getJson("/api/v1/collection-letters/{$letterR->id}/download")->assertForbidden();

        Sanctum::actingAs($this->supervisorQ);
        $this->assertSame([$letterQ->id], $this->ids($this->getJson('/api/v1/collection-letters')->assertOk()));
        $this->getJson("/api/v1/collection-letters/{$letterR->id}/download")->assertForbidden();

        Sanctum::actingAs($this->root);
        $all = $this->ids($this->getJson('/api/v1/collection-letters?per_page=100')->assertOk());
        $this->assertContains($letterQ->id, $all);
        $this->assertContains($letterR->id, $all);
        $this->get("/api/v1/collection-letters/{$letterR->id}/download")->assertOk();
    }

    private function makeLetter(Unit $unit): CollectionLetter
    {
        $letter = CollectionLetter::query()->create([
            'unit_id' => $unit->id, 'resident_id' => $unit->resident_id, 'letter_type' => 'reminder',
            'content' => 'Mohon segera melunasi tagihan.', 'generated_by' => $this->root->id, 'generated_at' => now(),
        ]);
        $path = "collection-letters/{$letter->id}.pdf";
        Storage::disk('public')->put($path, '%PDF-1.4 uji');
        $letter->update(['pdf_path' => $path]);

        return $letter;
    }

    // ── Riwayat pengingat WA ────────────────────────────────────────────────────────────

    public function test_reminder_history_is_scoped_and_readable_by_collector_for_own_unit(): void
    {
        $reminderQ = $this->makeReminder($this->unitQ, $this->collectorQ);
        $reminderR = $this->makeReminder($this->unitR, $this->collectorR);

        // Route saat ini masih `permission:reports.view`; izin diberikan langsung untuk
        // mensimulasikan route yang sudah dilonggarkan (lihat laporan Stage B5).
        $this->collectorQ->givePermissionTo('reports.view');
        Sanctum::actingAs($this->collectorQ);
        $this->assertSame([$reminderQ->id], $this->ids($this->getJson("/api/v1/collector-reminders?unit_id={$this->unitQ->id}")->assertOk()));
        $this->assertSame([$reminderQ->id], $this->ids($this->getJson('/api/v1/collector-reminders')->assertOk()));
        $this->getJson("/api/v1/collector-reminders?unit_id={$this->unitR->id}")->assertForbidden();

        Sanctum::actingAs($this->supervisorQ);
        $this->assertSame([$reminderQ->id], $this->ids($this->getJson('/api/v1/collector-reminders')->assertOk()));
        $this->getJson("/api/v1/collector-reminders?unit_id={$this->unitR->id}")->assertForbidden();

        Sanctum::actingAs($this->root);
        $all = $this->ids($this->getJson('/api/v1/collector-reminders?per_page=100')->assertOk());
        $this->assertContains($reminderQ->id, $all);
        $this->assertContains($reminderR->id, $all);
    }

    private function makeReminder(Unit $unit, User $sender): CollectorReminder
    {
        return CollectorReminder::query()->create([
            'unit_id' => $unit->id, 'resident_id' => $unit->resident_id, 'message' => 'Pengingat tagihan',
            'phone' => '6281200000000', 'sent_at' => now(), 'sent_by' => $sender->id,
        ]);
    }

    // ── Promise to Pay ──────────────────────────────────────────────────────────────────

    public function test_collector_can_only_create_pending_promises_and_cannot_settle_them(): void
    {
        Sanctum::actingAs($this->collectorQ);
        $payload = ['promised_amount' => 150000, 'promised_date' => now()->addDays(3)->toDateString()];

        foreach (['fulfilled', 'broken', 'rescheduled'] as $status) {
            $this->postJson("/api/v1/units/{$this->unitQ->id}/payment-promises", [...$payload, 'status' => $status])
                ->assertUnprocessable()->assertJsonValidationErrors('status');
        }

        // Payload Flutter (status 'pending') tetap diterima; collector_id = collector yang login.
        $promiseId = $this->postJson("/api/v1/units/{$this->unitQ->id}/payment-promises", [...$payload, 'status' => 'pending', 'payment_method' => 'transfer'])
            ->assertCreated()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->assertDatabaseHas('payment_promises', ['id' => $promiseId, 'collector_id' => $this->collectorQ->id, 'created_by' => $this->collectorQ->id]);

        // Tanpa status → default pending.
        $this->postJson("/api/v1/units/{$this->unitQ->id}/payment-promises", $payload)
            ->assertCreated()->assertJsonPath('data.status', 'pending');

        // Unit di luar penugasan tetap ditolak.
        $this->postJson("/api/v1/units/{$this->unitR->id}/payment-promises", [...$payload, 'status' => 'pending'])->assertForbidden();

        // Update: hanya pending/rescheduled.
        $this->putJson("/api/v1/payment-promises/{$promiseId}", [...$payload, 'status' => 'fulfilled'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->putJson("/api/v1/payment-promises/{$promiseId}", [...$payload, 'status' => 'rescheduled'])
            ->assertOk()->assertJsonPath('data.status', 'rescheduled');

        // Staf menandai diingkari → broken_at terisi; setelah itu collector tidak bisa mengubahnya.
        Sanctum::actingAs($this->root);
        $this->putJson("/api/v1/payment-promises/{$promiseId}", [...$payload, 'status' => 'broken'])->assertOk();
        $this->assertNotNull(PaymentPromise::query()->find($promiseId)->broken_at);

        Sanctum::actingAs($this->collectorQ);
        $this->putJson("/api/v1/payment-promises/{$promiseId}", [...$payload, 'status' => 'pending'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertSame('broken', PaymentPromise::query()->find($promiseId)->status);
    }

    public function test_staff_can_still_create_promises_with_any_status(): void
    {
        Sanctum::actingAs($this->root);

        $this->postJson("/api/v1/units/{$this->unitR->id}/payment-promises", [
            'promised_amount' => 100000, 'promised_date' => now()->toDateString(), 'status' => 'fulfilled',
        ])->assertCreated()->assertJsonPath('data.status', 'fulfilled');

        $this->postJson("/api/v1/units/{$this->unitR->id}/payment-promises", [
            'promised_amount' => 100000, 'promised_date' => now()->toDateString(),
        ])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    // ── SOS collector ke supervisor ─────────────────────────────────────────────────────

    public function test_collector_sos_without_unit_reaches_their_supervisor_only(): void
    {
        Sanctum::actingAs($this->collectorQ);
        $sosQ = $this->postJson('/api/v1/emergency-alerts', ['latitude' => -6.2, 'longitude' => 106.8, 'note' => 'Tolong'])
            ->assertCreated()->json('data.id');
        Sanctum::actingAs($this->collectorR);
        $sosR = $this->postJson('/api/v1/emergency-alerts', ['latitude' => -6.3, 'longitude' => 106.9])->assertCreated()->json('data.id');
        $unitAlertQ = EmergencyAlert::query()->create(['unit_id' => $this->unitQ->id, 'status' => 'active'])->id;
        EmergencyAlert::query()->create(['unit_id' => $this->unitR->id, 'status' => 'active']);

        Sanctum::actingAs($this->supervisorQ);
        $this->getJson('/api/v1/supervisor/dashboard')->assertOk()->assertJsonPath('data.active_emergencies', 2);

        $map = $this->getJson('/api/v1/supervisor/map')->assertOk();
        $this->assertEqualsCanonicalizing([$sosQ, $unitAlertQ], $this->ids($map, 'data.active_emergencies'));
        $this->assertNotContains($sosR, $this->ids($map, 'data.active_emergencies'));
        $sos = collect($map->json('data.active_emergencies'))->firstWhere('id', $sosQ);
        $this->assertNull($sos['unit']);
        $this->assertSame($this->collectorQ->name, $sos['reporter']['name']);

        Sanctum::actingAs($this->root);
        $this->getJson('/api/v1/supervisor/dashboard')->assertOk()
            ->assertJsonPath('data.active_emergencies', EmergencyAlert::query()->where('status', 'active')->count());
    }

    // ── Kuitansi & transaksi gateway ────────────────────────────────────────────────────

    public function test_receipts_and_gateway_transactions_are_scoped_for_collectors(): void
    {
        $receiptQ = $this->makeReceipt('RCPT-ZQ-1', $this->unitQ);
        $receiptR = $this->makeReceipt('RCPT-ZR-1', $this->unitR);
        $trxQ = PaymentTransaction::factory()->manual()->create(['unit_id' => $this->unitQ->id]);
        $trxR = PaymentTransaction::factory()->manual()->create(['unit_id' => $this->unitR->id]);

        Sanctum::actingAs($this->collectorQ);
        $receipts = collect($this->getJson('/api/v1/payments/receipts?per_page=100')->assertOk()->json('data'))->pluck('number')->all();
        $this->assertSame([$receiptQ->number], $receipts);
        $this->assertSame([$trxQ->id], $this->ids($this->getJson('/api/v1/payments/gateway/transactions?per_page=100')->assertOk()));

        Sanctum::actingAs($this->root);
        $receipts = collect($this->getJson('/api/v1/payments/receipts?per_page=100')->assertOk()->json('data'))->pluck('number')->all();
        $this->assertContains($receiptR->number, $receipts);
        $this->assertContains($receiptQ->number, $receipts);
        $trx = $this->ids($this->getJson('/api/v1/payments/gateway/transactions?per_page=100')->assertOk());
        $this->assertContains($trxR->id, $trx);
        $this->assertContains($trxQ->id, $trx);
    }

    private function makeReceipt(string $number, Unit $unit): Receipt
    {
        return Receipt::query()->create([
            'number' => $number, 'unit_id' => $unit->id, 'transaction_date' => now(),
            'resident_name' => 'Penghuni Uji', 'cluster_name' => $unit->cluster_id, 'block' => 'A', 'lot_number' => '01',
            'total_billing' => 100000, 'grand_total' => 100000, 'payment_method_id' => 'C', 'status' => 'paid',
        ]);
    }
}
