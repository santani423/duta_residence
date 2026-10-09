<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\CollectionAccountState;
use App\Models\CollectionActivity;
use App\Models\CollectionNote;
use App\Models\CollectorAssignment;
use App\Models\CollectorVisit;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Services\CollectionAccountRefreshQueue;
use App\Services\CollectionAccountService;
use App\Services\CollectionActivityService;
use App\Services\CollectionTimelineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CollectionAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private CollectionAccountService $accounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        config(['grandduta.notification.penalty_day' => 20, 'collector.due_soon_days' => 7]);
        Carbon::setTestNow(Carbon::create(2026, 10, 15, 9));

        $this->accounts = app(CollectionAccountService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private int $unitSequence = 0;

    /**
     * Unit uji di blok khusus 'Z' dengan id & nomor kavling berurutan, supaya tidak pernah
     * bentrok dengan unit seed (unique cluster_id+block+lot_number dan primary key id).
     */
    private function makeUnit(array $attributes = []): Unit
    {
        $resident = Resident::factory()->create();
        $sequence = ++$this->unitSequence;

        return Unit::factory()->create([
            'id' => 'Z'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'block' => 'Z',
            'lot_number' => (string) $sequence,
            'resident_id' => $resident->id,
            ...$attributes,
        ]);
    }

    /** Tagihan disetujui tanpa denda supaya nominal outstanding mudah diverifikasi. */
    private function bill(Unit $unit, int $year, int $month, float $amount = 500000, array $attributes = []): Billing
    {
        return Billing::query()->create([
            'unit_id' => $unit->id,
            'year' => $year,
            'month' => $month,
            'amount' => $amount,
            'status_id' => Billing::STATUS_UNPAID,
            'is_penalty_eligible' => false,
            'billing_type' => 'regular',
            'approved_at' => Carbon::create($year, $month, 2),
            ...$attributes,
        ]);
    }

    private function makeCollector(): User
    {
        $collector = User::factory()->create(['is_active' => true]);
        $collector->assignRole('collector');

        return $collector;
    }

    public function test_unit_without_open_invoices_is_paid(): void
    {
        $unit = $this->makeUnit();
        $this->bill($unit, 2026, 9, 500000, ['status_id' => Billing::STATUS_PAID, 'principal_paid' => 500000, 'paid_at' => now()]);

        $state = $this->accounts->refresh($unit->id);

        $this->assertSame('paid', $state->status);
        $this->assertSame(0.0, (float) $state->outstanding_total);
        $this->assertSame(0, $state->open_invoice_count);
        $this->assertSame('current', $state->aging_bucket);
        $this->assertSame('normal', $state->priority_level);
    }

    public function test_status_follows_due_date_of_oldest_open_invoice(): void
    {
        $current = $this->makeUnit();
        $this->bill($current, 2026, 11);

        $dueSoon = $this->makeUnit();
        $this->bill($dueSoon, 2026, 10);

        $this->assertSame('current', $this->accounts->refresh($current->id)->status);
        $this->assertSame('due_soon', $this->accounts->refresh($dueSoon->id)->status);

        Carbon::setTestNow(Carbon::create(2026, 10, 20, 9));
        $this->assertSame('due_today', $this->accounts->refresh($dueSoon->id)->status);

        Carbon::setTestNow(Carbon::create(2026, 10, 21, 9));
        $state = $this->accounts->refresh($dueSoon->id);
        $this->assertSame('overdue', $state->status);
        $this->assertSame(1, $state->aging_days);
        $this->assertSame('1_30', $state->aging_bucket);
    }

    public function test_overdue_account_aggregates_outstanding_and_aging(): void
    {
        $unit = $this->makeUnit();
        $this->bill($unit, 2026, 8, 400000);
        $this->bill($unit, 2026, 9, 400000, ['status_id' => Billing::STATUS_PARTIAL, 'principal_paid' => 100000]);
        $this->bill($unit, 2026, 10, 400000);

        $state = $this->accounts->refresh($unit->id);

        $this->assertSame('overdue', $state->status);
        $this->assertSame(1100000.0, (float) $state->outstanding_total);
        $this->assertSame(3, $state->open_invoice_count);
        $this->assertSame('2026-08-20', $state->oldest_due_date->toDateString());
        $this->assertSame('2026-10-20', $state->next_due_date->toDateString());
        $this->assertSame(56, $state->aging_days);
        $this->assertSame('31_60', $state->aging_bucket);
        $this->assertSame($unit->resident_id, $state->customer_resident_id);
    }

    public function test_partially_paid_invoice_not_yet_due(): void
    {
        $unit = $this->makeUnit();
        $this->bill($unit, 2026, 10, 500000, ['status_id' => Billing::STATUS_PARTIAL, 'principal_paid' => 200000]);

        $state = $this->accounts->refresh($unit->id);

        $this->assertSame('partially_paid', $state->status);
        $this->assertSame(300000.0, (float) $state->outstanding_total);
    }

    public function test_unapproved_and_cancelled_invoices_are_not_collectable(): void
    {
        $unit = $this->makeUnit();
        $this->bill($unit, 2026, 7, 500000, ['approved_at' => null]);
        $this->bill($unit, 2026, 8, 500000, ['status_id' => Billing::STATUS_CANCELLED, 'cancelled_at' => now()]);

        $state = $this->accounts->refresh($unit->id);

        $this->assertSame('paid', $state->status);
        $this->assertSame(0, $state->open_invoice_count);
    }

    public function test_customer_follows_billing_payer(): void
    {
        $tenant = Resident::factory()->create();
        $unit = $this->makeUnit(['tenant_resident_id' => $tenant->id, 'billing_payer' => 'penyewa']);
        $this->bill($unit, 2026, 9);

        $this->assertSame($tenant->id, $this->accounts->refresh($unit->id)->customer_resident_id);
    }

    public function test_active_promise_overrides_overdue_and_drives_follow_up(): void
    {
        $unit = $this->makeUnit();
        $this->bill($unit, 2026, 8);

        $promise = PaymentPromise::query()->create([
            'unit_id' => $unit->id,
            'promised_amount' => 500000,
            'promised_date' => '2026-10-18',
            'status' => PaymentPromise::STATUS_PENDING,
        ]);

        $state = $this->accounts->refresh($unit->id);
        $this->assertSame('promise_to_pay', $state->status);
        $this->assertSame($promise->id, $state->active_promise_id);
        $this->assertSame('2026-10-18', $state->next_follow_up_at->toDateString());

        // Janji yang tanggalnya sudah lewat tidak lagi melindungi akun dari status overdue.
        Carbon::setTestNow(Carbon::create(2026, 10, 19, 9));
        $state = $this->accounts->refresh($unit->id);
        $this->assertSame('overdue', $state->status);
        $this->assertNull($state->active_promise_id);
    }

    public function test_priority_reflects_aging_outstanding_and_broken_promises(): void
    {
        config(['collector.large_outstanding_threshold' => 5000000]);
        $unit = $this->makeUnit();
        $this->bill($unit, 2026, 1, 6000000);

        foreach (range(1, 3) as $i) {
            PaymentPromise::query()->create([
                'unit_id' => $unit->id,
                'promised_amount' => 100000,
                'promised_date' => '2026-09-0'.$i,
                'status' => PaymentPromise::STATUS_BROKEN,
                'broken_at' => '2026-09-0'.$i,
            ]);
        }

        $state = $this->accounts->refresh($unit->id);

        $this->assertSame(3, $state->broken_ptp_count);
        $this->assertGreaterThan(180, $state->aging_days);
        $this->assertSame('180_plus', $state->aging_bucket);
        $this->assertSame(80, $state->priority_score);
        $this->assertSame('critical', $state->priority_level);
    }

    public function test_failed_contacts_and_last_contact_are_tracked(): void
    {
        $collector = $this->makeCollector();
        $unit = $this->makeUnit();
        $this->bill($unit, 2026, 9);
        $activities = app(CollectionActivityService::class);

        foreach (['2026-10-10 10:00', '2026-10-12 10:00', '2026-10-14 10:00'] as $at) {
            $activities->record($unit->id, CollectionActivity::TYPE_CALL, [
                'channel_result' => CollectionActivity::RESULT_NO_ANSWER,
                'occurred_at' => $at,
            ], $collector);
        }

        $activities->record($unit->id, CollectionActivity::TYPE_WHATSAPP, [
            'channel_result' => CollectionActivity::RESULT_CALLBACK_REQUESTED,
            'occurred_at' => '2026-10-15 08:00',
            'next_follow_up_at' => '2026-10-17 10:00',
        ], $collector);

        $state = CollectionAccountState::query()->find($unit->id);

        $this->assertSame(3, $state->failed_contact_count);
        $this->assertSame('2026-10-15 08:00:00', $state->last_contact_at->toDateTimeString());
        $this->assertSame('callback_requested', $state->last_contact_result);
        $this->assertSame('2026-10-17 10:00:00', $state->next_follow_up_at->toDateTimeString());
        $this->assertSame($collector->id, CollectionActivity::query()->latest('id')->value('collector_id'));
    }

    public function test_record_is_idempotent_by_client_uuid(): void
    {
        $collector = $this->makeCollector();
        $unit = $this->makeUnit();
        $uuid = (string) Str::uuid();
        $activities = app(CollectionActivityService::class);

        $first = $activities->record($unit->id, CollectionActivity::TYPE_CALL, ['client_uuid' => $uuid, 'channel_result' => 'answered'], $collector);
        $second = $activities->record($unit->id, CollectionActivity::TYPE_CALL, ['client_uuid' => $uuid, 'channel_result' => 'answered'], $collector);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CollectionActivity::query()->where('client_uuid', $uuid)->count());
    }

    public function test_most_specific_assignment_becomes_primary_collector(): void
    {
        $clusterCollector = $this->makeCollector();
        $unitCollector = $this->makeCollector();
        $unit = $this->makeUnit();
        $sibling = $this->makeUnit(['cluster_id' => $unit->cluster_id]);

        CollectorAssignment::query()->create([
            'collector_id' => $clusterCollector->id, 'scope_type' => 'cluster', 'cluster_id' => $unit->cluster_id,
            'is_active' => true, 'status' => 'active',
        ]);
        CollectorAssignment::query()->create([
            'collector_id' => $unitCollector->id, 'scope_type' => 'unit', 'unit_id' => $unit->id,
            'is_active' => true, 'status' => 'active',
        ]);

        $this->accounts->refreshMany([$unit->id, $sibling->id]);

        $this->assertSame($unitCollector->id, CollectionAccountState::query()->find($unit->id)->collector_id);
        $this->assertSame($clusterCollector->id, CollectionAccountState::query()->find($sibling->id)->collector_id);
    }

    public function test_visits_promises_and_payments_appear_on_timeline_automatically(): void
    {
        $collector = $this->makeCollector();
        $unit = $this->makeUnit();
        $this->bill($unit, 2026, 9);

        $visit = CollectorVisit::query()->create([
            'unit_id' => $unit->id, 'collector_id' => $collector->id, 'visit_date' => '2026-10-15 10:00',
            'purpose' => 'Penagihan', 'status' => 'no_answer', 'created_by' => $collector->id,
        ]);
        $visit->update(['status' => 'completed']);

        PaymentPromise::query()->create([
            'unit_id' => $unit->id, 'collector_id' => $collector->id, 'promised_amount' => 500000,
            'promised_date' => '2026-10-20', 'status' => 'pending', 'created_by' => $collector->id,
        ])->update(['status' => PaymentPromise::STATUS_FULFILLED]);

        PaymentTransaction::query()->create([
            'transaction_number' => 'TRX-TL-1', 'invoice_number' => 'INV-TL-1', 'unit_id' => $unit->id,
            'subtotal' => 500000, 'total' => 500000, 'payment_provider' => 'loket', 'status' => 'paid',
            'paid_at' => now(), 'created_by' => $collector->id,
        ]);

        $events = CollectionActivity::query()->where('unit_id', $unit->id)->orderBy('id')
            ->get()->map(fn ($a) => $a->type.':'.$a->event)->all();

        $this->assertSame([
            'visit:created', 'visit:result_completed', 'promise:created', 'promise:fulfilled', 'payment:paid',
        ], $events);

        // Recording the same subject/event again is a no-op.
        $this->assertNull(app(CollectionActivityService::class)->recordForSubject($visit));

        $timeline = app(CollectionTimelineService::class)->forUnit($unit->id, ['visit']);
        $this->assertCount(2, $timeline->items());
    }

    public function test_backfill_command_rebuilds_timeline_and_states(): void
    {
        $collector = $this->makeCollector();
        $unit = $this->makeUnit();
        $this->bill($unit, 2026, 8);

        CollectorVisit::query()->create([
            'unit_id' => $unit->id, 'collector_id' => $collector->id, 'visit_date' => '2026-10-01 10:00',
            'purpose' => 'Penagihan', 'status' => 'refused',
        ]);
        CollectionActivity::query()->delete();
        CollectionAccountState::query()->delete();

        $this->artisan('collection:backfill-activities')->assertSuccessful();

        $this->assertSame(1, CollectionActivity::query()->where('unit_id', $unit->id)->where('type', 'visit')->count());
        $state = CollectionAccountState::query()->find($unit->id);
        $this->assertSame('overdue', $state->status);
        $this->assertSame(1, $state->failed_visit_count);

        // Idempoten.
        $this->artisan('collection:backfill-activities', ['--skip-refresh' => true])->assertSuccessful();
        $this->assertSame(1, CollectionActivity::query()->where('unit_id', $unit->id)->where('type', 'visit')->count());
    }

    public function test_billing_changes_are_refreshed_through_queue(): void
    {
        $unit = $this->makeUnit();
        $billing = $this->bill($unit, 2026, 8);
        app(CollectionAccountRefreshQueue::class)->flush();
        $this->assertSame('overdue', CollectionAccountState::query()->find($unit->id)->status);

        $billing->update(['status_id' => Billing::STATUS_PAID, 'principal_paid' => 500000, 'paid_at' => now()]);
        app(CollectionAccountRefreshQueue::class)->flush();

        $this->assertSame('paid', CollectionAccountState::query()->find($unit->id)->status);
    }

    public function test_refresh_command_and_deleted_units(): void
    {
        $unit = $this->makeUnit();
        $this->bill($unit, 2026, 9);

        $this->artisan('collection:refresh-account-states', ['--unit' => [$unit->id]])->assertSuccessful();
        $this->assertTrue(CollectionAccountState::query()->whereKey($unit->id)->exists());

        $unit->delete();
        $this->accounts->refresh($unit->id);
        $this->assertFalse(CollectionAccountState::query()->whereKey($unit->id)->exists());
    }

    public function test_version_increments_on_update(): void
    {
        $unit = $this->makeUnit();
        $note = CollectionNote::query()->create(['unit_id' => $unit->id, 'title' => 'Catatan', 'body' => 'Isi']);
        $this->assertSame(1, $note->fresh()->version);

        $note->update(['body' => 'Isi baru']);
        $this->assertSame(2, $note->fresh()->version);
        $this->assertTrue($note->fresh()->isVersion(2));
    }

    public function test_collection_permissions_are_seeded_per_role(): void
    {
        $collector = Role::findByName('collector', 'web');
        $finance = Role::findByName('finance', 'web');
        $supervisor = Role::findByName('supervisor', 'web');

        $this->assertTrue($collector->hasPermissionTo('collection-accounts.view'));
        $this->assertTrue($collector->hasPermissionTo('collection-payments.submit'));
        $this->assertFalse($collector->hasPermissionTo('collection-disputes.resolve'));
        $this->assertFalse($collector->hasPermissionTo('collection-escalations.handle'));
        $this->assertTrue($supervisor->hasPermissionTo('collection-escalations.handle'));
        $this->assertFalse($finance->hasPermissionTo('collection-payments.submit'));
    }
}
