<?php

namespace Tests\Feature;

use App\Models\CollectorAssignment;
use App\Models\CollectorTarget;
use App\Models\SupervisorAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\CollectionAccountRefreshQueue;
use App\Services\CollectorPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectorTargetPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function root(): User
    {
        return User::where('username', 'root')->first();
    }

    private function makeCollector(string $username, ?string $name = null): User
    {
        $collector = User::factory()->create(['username' => $username, 'name' => $name ?? ucfirst($username), 'is_active' => true]);
        $collector->assignRole('collector');
        $collector->collectorProfile()->create([
            'collector_code' => 'TST-'.str_pad((string) $collector->id, 4, '0', STR_PAD_LEFT),
            'account_status' => 'active',
        ]);

        return $collector;
    }

    private function makeSupervisor(string $username, string $clusterId): User
    {
        $supervisor = User::factory()->create(['username' => $username, 'is_active' => true]);
        $supervisor->assignRole('supervisor');
        SupervisorAssignment::query()->create([
            'supervisor_id' => $supervisor->id, 'cluster_id' => $clusterId, 'is_active' => true,
            'start_date' => now()->subDay()->toDateString(), 'status' => 'active',
        ]);

        return $supervisor;
    }

    private function assignCluster(User $collector, string $clusterId): void
    {
        CollectorAssignment::query()->create([
            'collector_id' => $collector->id, 'scope_type' => 'cluster', 'cluster_id' => $clusterId,
            'is_active' => true, 'status' => 'active', 'priority' => 'normal',
            'start_date' => now()->subDay()->toDateString(), 'assigned_by' => $this->root()->id,
        ]);
        app(CollectionAccountRefreshQueue::class)->flush();
    }

    private function unitId(string $clusterId = 'GA', int $offset = 0): string
    {
        return Unit::query()->where('cluster_id', $clusterId)->orderBy('id')->skip($offset)->value('id');
    }

    private function payment(array $attributes): void
    {
        $this->sequence++;
        DB::table('payment_transactions')->insert([
            'transaction_number' => 'TRX-PERF-'.$this->sequence,
            'invoice_number' => 'INV-PERF-'.$this->sequence,
            'unit_id' => $this->unitId(),
            'subtotal' => $attributes['total'],
            'currency' => 'IDR',
            'status' => 'paid',
            'payment_provider' => 'loket',
            'created_at' => '2026-09-10 10:00:00',
            'updated_at' => '2026-09-10 10:00:00',
            ...$attributes,
        ]);
    }

    private function visit(User $collector, string $date, array $attributes = []): void
    {
        DB::table('collector_visits')->insert([
            'unit_id' => $this->unitId(),
            'collector_id' => $collector->id,
            'visit_date' => $date,
            'purpose' => 'Penagihan',
            'status' => 'completed',
            'lifecycle' => 'completed',
            'created_at' => $date,
            'updated_at' => $date,
            ...$attributes,
        ]);
    }

    private function promise(array $attributes): void
    {
        DB::table('payment_promises')->insert([
            'unit_id' => $this->unitId(),
            'promised_amount' => 100000,
            'promised_date' => '2026-09-20',
            'status' => 'pending',
            'created_at' => '2026-09-05 09:00:00',
            'updated_at' => '2026-09-05 09:00:00',
            ...$attributes,
        ]);
    }

    public function test_resolve_period_aligns_to_the_start_of_each_period_type(): void
    {
        $service = app(CollectorPerformanceService::class);

        $daily = $service->resolvePeriod('daily', '2026-09-17');
        $this->assertSame('2026-09-17', $daily['start']->toDateString());
        $this->assertSame('2026-09-17 23:59:59', $daily['end']->format('Y-m-d H:i:s'));

        // 2026-09-17 = Kamis → minggu Senin 14 s/d Minggu 20.
        $weekly = $service->resolvePeriod('weekly', '2026-09-17');
        $this->assertSame('2026-09-14', $weekly['start']->toDateString());
        $this->assertSame('2026-09-20', $weekly['end']->toDateString());

        $monthly = $service->resolvePeriod('monthly', '2026-09-17');
        $this->assertSame('2026-09-01', $monthly['start']->toDateString());
        $this->assertSame('2026-09-30', $monthly['end']->toDateString());

        Carbon::setTestNow('2026-10-08 12:00:00');
        $this->assertSame('2026-10-08', $service->resolvePeriod('daily', null)['start_date']);
        $this->assertSame('2026-10-05', $service->resolvePeriod('weekly', null)['start_date']);
        $this->assertSame('2026-10-01', $service->resolvePeriod('monthly', null)['start_date']);
        Carbon::setTestNow();
    }

    public function test_metrics_for_applies_the_collected_rule_and_batches_every_metric(): void
    {
        $this->seed();
        $collector = $this->makeCollector('perf.metrics');
        $other = $this->makeCollector('perf.other');
        $id = $collector->id;

        // Tertagih: collected_by (non-loket juga), loket lama via created_by, fallback created_at.
        $this->payment(['total' => 300000, 'payment_provider' => 'xendit', 'collected_by' => $id, 'paid_at' => '2026-09-03 08:00:00']);
        $this->payment(['total' => 200000, 'created_by' => $id, 'paid_at' => '2026-09-15 08:00:00']);
        $this->payment(['total' => 50000, 'created_by' => $id, 'paid_at' => null, 'created_at' => '2026-09-20 08:00:00']);
        // Tidak dihitung: belum lunas, di luar periode, non-loket tanpa collected_by, milik collector lain.
        $this->payment(['total' => 999000, 'created_by' => $id, 'status' => 'pending', 'paid_at' => null]);
        $this->payment(['total' => 999000, 'created_by' => $id, 'paid_at' => '2026-10-01 00:00:00']);
        $this->payment(['total' => 999000, 'created_by' => $id, 'payment_provider' => 'xendit', 'paid_at' => '2026-09-10 08:00:00']);
        $this->payment(['total' => 999000, 'created_by' => $id, 'collected_by' => $other->id, 'paid_at' => '2026-09-10 08:00:00']);

        // Kunjungan: 2 selesai (1 gagal via result_code), 1 lifecycle failed, 1 terjadwal (tidak dihitung).
        $this->visit($collector, '2026-09-02 10:00:00');
        $this->visit($collector, '2026-09-03 10:00:00', ['result_code' => 'not_home']);
        $this->visit($collector, '2026-09-04 10:00:00', ['lifecycle' => 'failed']);
        $this->visit($collector, '2026-09-05 10:00:00', ['lifecycle' => 'scheduled']);
        $this->visit($collector, '2026-08-31 10:00:00');

        // PTP: dibuat oleh collector (collector_id null → created_by), terpenuhi, ingkar.
        $this->promise(['collector_id' => $id]);
        $this->promise(['created_by' => $id, 'status' => 'fulfilled', 'fulfilled_at' => '2026-09-21 10:00:00']);
        $this->promise(['collector_id' => $id, 'status' => 'broken', 'broken_at' => '2026-09-25 10:00:00', 'created_at' => '2026-08-20 09:00:00']);
        $this->promise(['collector_id' => $other->id, 'created_by' => $id]);

        DB::table('collection_account_states')->updateOrInsert(['unit_id' => $this->unitId('GA', 0)], [
            'collector_id' => $id, 'outstanding_total' => 450000, 'aging_days' => 40, 'priority_level' => 'critical', 'status' => 'overdue',
        ]);
        DB::table('collection_account_states')->updateOrInsert(['unit_id' => $this->unitId('GA', 1)], [
            'collector_id' => $id, 'outstanding_total' => 0, 'aging_days' => 0, 'priority_level' => 'normal', 'status' => 'paid',
        ]);

        CollectorTarget::query()->create([
            'collector_id' => $id, 'period_type' => 'monthly', 'period_start' => '2026-09-01',
            'target_amount' => 400000, 'target_visit_count' => 2, 'target_account_count' => 4, 'target_collection_rate' => 80,
        ]);

        $service = app(CollectorPerformanceService::class);
        $metrics = $service->metricsFor([$id, $other->id], $service->resolvePeriod('monthly', '2026-09-17'));
        $row = $metrics[$id];

        $this->assertEqualsWithDelta(550000, $row['collected_amount'], 0.001);
        $this->assertSame(3, $row['payment_count']);
        $this->assertSame(3, $row['visit_count']);
        $this->assertSame(1, $row['successful_visit_count']);
        $this->assertEqualsWithDelta(33.3, $row['successful_visit_rate'], 0.001);
        $this->assertSame(2, $row['ptp_created']);
        $this->assertSame(1, $row['ptp_fulfilled']);
        $this->assertSame(1, $row['ptp_broken']);
        $this->assertEqualsWithDelta(50.0, $row['ptp_fulfillment_rate'], 0.001);
        $this->assertSame(2, $row['assigned_accounts']);
        $this->assertEqualsWithDelta(450000, $row['outstanding_total'], 0.001);
        $this->assertSame(1, $row['overdue_accounts']);
        $this->assertSame(1, $row['critical_accounts']);
        $this->assertEqualsWithDelta(55.0, $row['collection_rate'], 0.001);
        $this->assertNull($row['overdue_reduction']);
        $this->assertEqualsWithDelta(400000, $row['target_amount'], 0.001);
        $this->assertSame(4, $row['target_account_count']);
        $this->assertEqualsWithDelta(80, $row['target_collection_rate'], 0.001);
        $this->assertEqualsWithDelta(137.5, $row['achievement_percent_raw'], 0.001);
        $this->assertEqualsWithDelta(100.0, $row['achievement_percent'], 0.001);
        $this->assertEqualsWithDelta(150.0, $row['visit_achievement_percent'], 0.001);

        // Collector lain: hanya PTP miliknya (collector_id menang atas created_by), tanpa target.
        $this->assertSame(1, $metrics[$other->id]['ptp_created']);
        $this->assertEqualsWithDelta(999000, $metrics[$other->id]['collected_amount'], 0.001);
        $this->assertNull($metrics[$other->id]['target_amount']);
        $this->assertNull($metrics[$other->id]['achievement_percent_raw']);
    }

    public function test_collector_performance_me_keeps_the_flutter_keys_and_adds_new_metrics(): void
    {
        $this->seed();
        $collector = $this->makeCollector('perf.me');
        $this->payment(['total' => 250000, 'created_by' => $collector->id, 'paid_at' => '2026-09-15 08:00:00']);
        CollectorTarget::query()->create([
            'collector_id' => $collector->id, 'period_type' => 'monthly', 'period_start' => '2026-09-01', 'target_amount' => 500000,
        ]);

        Sanctum::actingAs($collector);
        $response = $this->getJson('/api/v1/collector-performance/me?period_type=monthly&period_start=2026-09-01')->assertOk();

        $response->assertJsonStructure(['data' => [
            'period_type', 'period_start', 'period_end', 'target_amount', 'collected_amount',
            'achievement_percent', 'target_visit_count', 'visit_count',
            'achievement_percent_raw', 'collection_rate', 'successful_visit_rate', 'ptp_fulfilled', 'ptp_fulfillment_rate',
        ]]);
        $response->assertJsonPath('data.period_start', '2026-09-01')
            ->assertJsonPath('data.period_end', '2026-09-30');
        $this->assertEqualsWithDelta(50.0, $response->json('data.achievement_percent'), 0.001);
        $this->assertEqualsWithDelta(250000, $response->json('data.collected_amount'), 0.001);

        // Tanpa target: target_amount tetap 0 (bukan null) dan achievement_percent null.
        $weekly = $this->getJson('/api/v1/collector-performance/me?period_type=weekly&period_start=2026-09-14')->assertOk();
        $this->assertEquals(0, $weekly->json('data.target_amount'));
        $this->assertNull($weekly->json('data.achievement_percent'));
    }

    public function test_collector_cannot_read_another_collectors_performance(): void
    {
        $this->seed();
        $self = $this->makeCollector('perf.self');
        $other = $this->makeCollector('perf.someone');

        Sanctum::actingAs($self);
        $this->getJson("/api/v1/collector-performance?collector_id={$self->id}&period_type=monthly&period_start=2026-09-01")->assertOk();
        $this->getJson("/api/v1/collector-performance?collector_id={$other->id}&period_type=monthly&period_start=2026-09-01")->assertForbidden();
    }

    public function test_supervisor_is_limited_to_collectors_in_their_clusters(): void
    {
        $this->seed();
        $supervisor = $this->makeSupervisor('perf.spv', 'GA');
        $inScope = $this->makeCollector('perf.in.scope', 'Aaa Dalam Cakupan');
        $outScope = $this->makeCollector('perf.out.scope', 'Bbb Luar Cakupan');
        $this->assignCluster($inScope, 'GA');
        $this->assignCluster($outScope, 'AL');

        $outTarget = CollectorTarget::query()->create([
            'collector_id' => $outScope->id, 'period_type' => 'monthly', 'period_start' => '2026-09-01', 'target_amount' => 100000,
        ]);

        Sanctum::actingAs($supervisor);

        $this->getJson("/api/v1/collector-performance?collector_id={$outScope->id}&period_type=monthly&period_start=2026-09-01")->assertForbidden();
        $this->getJson("/api/v1/collector-performance?collector_id={$inScope->id}&period_type=monthly&period_start=2026-09-01")->assertOk();

        $this->postJson('/api/v1/collector-targets', [
            'collector_id' => $outScope->id, 'period_type' => 'monthly', 'period_start' => '2026-10-01', 'target_amount' => 100000,
        ])->assertForbidden();
        $this->putJson("/api/v1/collector-targets/{$outTarget->id}", [
            'collector_id' => $outScope->id, 'period_type' => 'monthly', 'period_start' => '2026-09-01', 'target_amount' => 1,
        ])->assertForbidden();
        $this->deleteJson("/api/v1/collector-targets/{$outTarget->id}")->assertForbidden();

        $this->postJson('/api/v1/collector-targets', [
            'collector_id' => $inScope->id, 'period_type' => 'monthly', 'period_start' => '2026-09-01', 'target_amount' => 100000,
        ])->assertCreated();

        $listed = collect($this->getJson('/api/v1/collector-targets')->assertOk()->json('data'))->pluck('collector_id');
        $this->assertTrue($listed->contains($inScope->id));
        $this->assertFalse($listed->contains($outScope->id));

        $progress = collect($this->getJson('/api/v1/collector-targets/progress?period_type=monthly&period_start=2026-09-01')->assertOk()->json('data'))
            ->pluck('collector.id');
        $this->assertTrue($progress->contains($inScope->id));
        $this->assertFalse($progress->contains($outScope->id));

        $this->getJson('/api/v1/collector-targets/progress?cluster_id=AL')->assertForbidden();
        $this->getJson('/api/v1/collection/performance?cluster_id=AL')->assertForbidden();

        $ranking = collect($this->getJson('/api/v1/collection/performance')->assertOk()->json('data.ranking'))->pluck('collector.id');
        $this->assertTrue($ranking->contains($inScope->id));
        $this->assertFalse($ranking->contains($outScope->id));
    }

    public function test_target_store_normalizes_period_start_validates_new_fields_and_rejects_duplicates(): void
    {
        $this->seed();
        $collector = $this->makeCollector('perf.target');
        Sanctum::actingAs($this->root());

        $response = $this->postJson('/api/v1/collector-targets', [
            'collector_id' => $collector->id, 'period_type' => 'monthly', 'period_start' => '2026-09-17',
            'target_amount' => 1500000, 'target_visit_count' => 30, 'target_account_count' => 12, 'target_collection_rate' => 85.5,
        ])->assertCreated();

        $response->assertJsonPath('data.period_start', '2026-09-01')
            ->assertJsonPath('data.target_account_count', 12);
        $this->assertEqualsWithDelta(85.5, (float) $response->json('data.target_collection_rate'), 0.001);

        // Tanggal lain di bulan yang sama → periode sama → duplikat.
        $this->postJson('/api/v1/collector-targets', [
            'collector_id' => $collector->id, 'period_type' => 'monthly', 'period_start' => '2026-09-25', 'target_amount' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('period_start');

        // Mingguan dinormalisasi ke Senin.
        $this->postJson('/api/v1/collector-targets', [
            'collector_id' => $collector->id, 'period_type' => 'weekly', 'period_start' => '2026-09-17', 'target_amount' => 1,
        ])->assertCreated()->assertJsonPath('data.period_start', '2026-09-14');

        $this->postJson('/api/v1/collector-targets', [
            'collector_id' => $collector->id, 'period_type' => 'daily', 'period_start' => '2026-09-17',
            'target_amount' => 99999999999999, 'target_collection_rate' => 150, 'target_account_count' => -1,
        ])->assertUnprocessable()->assertJsonValidationErrors(['target_amount', 'target_collection_rate', 'target_account_count']);

        // Bukan collector → ditolak.
        $this->postJson('/api/v1/collector-targets', [
            'collector_id' => $this->root()->id, 'period_type' => 'daily', 'period_start' => '2026-09-17', 'target_amount' => 1,
        ])->assertUnprocessable()->assertJsonValidationErrors('collector_id');

        // Filter index period_start (dinormalisasi bila period_type ada).
        $this->getJson("/api/v1/collector-targets?collector_id={$collector->id}&period_type=monthly&period_start=2026-09-10")
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_progress_returns_every_collector_with_target_and_metrics(): void
    {
        $this->seed();
        $withTarget = $this->makeCollector('perf.progress.a');
        $withoutTarget = $this->makeCollector('perf.progress.b');
        $target = CollectorTarget::query()->create([
            'collector_id' => $withTarget->id, 'period_type' => 'monthly', 'period_start' => '2026-09-01', 'target_amount' => 200000,
        ]);
        $this->payment(['total' => 100000, 'created_by' => $withTarget->id, 'paid_at' => '2026-09-15 08:00:00']);

        Sanctum::actingAs($this->root());
        $response = $this->getJson('/api/v1/collector-targets/progress?period_type=monthly&period_start=2026-09-20')->assertOk();

        $response->assertJsonPath('meta.period', ['type' => 'monthly', 'start' => '2026-09-01', 'end' => '2026-09-30']);
        $rows = collect($response->json('data'))->keyBy('collector.id');

        $this->assertSame($target->id, $rows[$withTarget->id]['target']['id']);
        $this->assertSame('2026-09-01', $rows[$withTarget->id]['target']['period_start']);
        $this->assertStringStartsWith('TST-', $rows[$withTarget->id]['collector']['collector_code']);
        $this->assertEqualsWithDelta(50.0, $rows[$withTarget->id]['metrics']['achievement_percent_raw'], 0.001);
        $this->assertNull($rows[$withoutTarget->id]['target']);
        $this->assertArrayHasKey('collection_rate', $rows[$withoutTarget->id]['metrics']);
    }

    public function test_ranking_orders_by_metric_and_returns_summary(): void
    {
        $this->seed();
        $low = $this->makeCollector('perf.rank.low', 'Zul Rendah');
        $high = $this->makeCollector('perf.rank.high', 'Ani Tinggi');
        $this->payment(['total' => 100000, 'created_by' => $low->id, 'paid_at' => '2026-09-15 08:00:00']);
        $this->payment(['total' => 700000, 'collected_by' => $high->id, 'paid_at' => '2026-09-16 08:00:00']);
        $this->visit($low, '2026-09-10 10:00:00');
        $this->visit($low, '2026-09-11 10:00:00');

        Sanctum::actingAs($this->root());

        $byAmount = $this->getJson('/api/v1/collection/performance?period_type=monthly&period_start=2026-09-01&metric=collected_amount')->assertOk();
        $byAmount->assertJsonPath('data.metric', 'collected_amount')
            ->assertJsonPath('data.period.start', '2026-09-01')
            ->assertJsonStructure(['data' => [
                'summary' => ['collected_amount', 'visit_count', 'ptp_fulfilled', 'average_collection_rate', 'collector_count'],
                'ranking' => [['rank', 'collector' => ['id', 'name', 'collector_code'], 'collected_amount', 'collection_rate', 'achievement_percent_raw', 'visit_count', 'successful_visit_rate', 'ptp_fulfilled', 'ptp_fulfillment_rate']],
            ]]);

        $ranking = collect($byAmount->json('data.ranking'));
        $this->assertSame(range(1, $ranking->count()), $ranking->pluck('rank')->all());
        $this->assertSame($ranking->count(), $byAmount->json('data.summary.collector_count'));
        $highRank = $ranking->firstWhere('collector.id', $high->id)['rank'];
        $lowRank = $ranking->firstWhere('collector.id', $low->id)['rank'];
        $this->assertLessThan($lowRank, $highRank);

        $byVisits = collect($this->getJson('/api/v1/collection/performance?period_type=monthly&period_start=2026-09-01&metric=visit_count')
            ->assertOk()->json('data.ranking'));
        $this->assertLessThan(
            $byVisits->firstWhere('collector.id', $high->id)['rank'],
            $byVisits->firstWhere('collector.id', $low->id)['rank'],
        );

        $this->getJson('/api/v1/collection/performance?metric=bogus')->assertUnprocessable();
    }

    public function test_supervisor_targets_defaults_to_the_current_period_of_the_requested_type(): void
    {
        Carbon::setTestNow('2026-10-08 09:00:00');
        $this->seed();
        $supervisor = $this->makeSupervisor('perf.spv.targets', 'GA');
        $collector = $this->makeCollector('perf.spv.collector');
        $this->assignCluster($collector, 'GA');
        CollectorTarget::query()->create([
            'collector_id' => $collector->id, 'period_type' => 'daily', 'period_start' => '2026-10-08', 'target_amount' => 100000,
        ]);
        CollectorTarget::query()->create([
            'collector_id' => $collector->id, 'period_type' => 'weekly', 'period_start' => '2026-10-05', 'target_amount' => 300000,
        ]);

        Sanctum::actingAs($supervisor);

        $daily = $this->getJson('/api/v1/supervisor/targets?period_type=daily')->assertOk()
            ->assertJsonStructure(['data' => [['collector', 'target', 'achievement' => ['achievement_percent', 'collected_amount', 'target_amount', 'visit_count']]]]);
        $row = collect($daily->json('data'))->firstWhere('collector.id', $collector->id);
        $this->assertNotNull($row);
        $this->assertSame('2026-10-08', $row['achievement']['period_start']);
        $this->assertEqualsWithDelta(100000, $row['achievement']['target_amount'], 0.001);

        $weekly = $this->getJson('/api/v1/supervisor/targets?period_type=weekly&period_start=2026-10-09')->assertOk();
        $row = collect($weekly->json('data'))->firstWhere('collector.id', $collector->id);
        $this->assertNotNull($row);
        $this->assertSame('2026-10-05', $row['achievement']['period_start']);
        $this->assertEqualsWithDelta(300000, $row['achievement']['target_amount'], 0.001);

        Carbon::setTestNow();
    }

    public function test_report_export_uses_batched_metrics_for_all_collectors_in_scope(): void
    {
        $this->seed();
        $collector = $this->makeCollector('perf.report', 'Kolektor Laporan');
        $this->payment(['total' => 123000, 'created_by' => $collector->id, 'paid_at' => now()->startOfMonth()->addHour()->format('Y-m-d H:i:s')]);
        Sanctum::actingAs($this->root());

        $exportId = $this->postJson('/api/v1/report-exports', ['type' => 'collector_performance', 'period_type' => 'weekly', 'period_start' => '2026-09-17'])
            ->assertCreated()
            ->assertJsonPath('data.filters.period_start', '2026-09-14')
            ->json('data.id');
        $this->assertDatabaseHas('report_exports', ['id' => $exportId, 'status' => 'completed']);

        $monthlyId = $this->postJson('/api/v1/report-exports', ['type' => 'collector_performance'])->assertCreated()->json('data.id');
        $csv = $this->get("/api/v1/report-exports/{$monthlyId}/download")->assertOk()->streamedContent();
        $this->assertStringContainsString('Kolektor Laporan', $csv);
        $this->assertStringContainsString('123000', $csv);
    }
}
