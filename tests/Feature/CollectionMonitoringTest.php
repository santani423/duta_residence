<?php

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\CollectionAccountState;
use App\Models\CollectorAssignment;
use App\Models\CollectorProfile;
use App\Models\Resident;
use App\Models\SupervisorAssignment;
use App\Models\Unit;
use App\Models\User;
use App\Services\CollectionAccountRefreshQueue;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CollectionMonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $collectorA;

    private User $collectorB;

    private int $residentSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Carbon::setTestNow(Carbon::create(2026, 10, 15, 9));

        // Dua cluster uji yang tidak disentuh data seed.
        Cluster::query()->create(['id' => 'ZM', 'name' => 'Cluster Monitor M', 'monthly_rate' => 100000]);
        Cluster::query()->create(['id' => 'ZN', 'name' => 'Cluster Monitor N', 'monthly_rate' => 100000]);

        $this->collectorA = $this->makeCollector(['name' => 'Aan Monitor']);
        $this->collectorB = $this->makeCollector(['name' => 'Bobi Monitor']);

        // ZM: 4 akun (A pegang blok A, B pegang satu unit blok B, satu belum ditugaskan, satu lunas).
        $m1 = $this->makeUnit('ZM001', 'ZM', 'A', ['name' => 'Citra Kritis', 'phone' => '081111000001']);
        $m2 = $this->makeUnit('ZM002', 'ZM', 'A', ['name' => 'Dedi Sedang', 'phone' => '081111000002']);
        $m3 = $this->makeUnit('ZM003', 'ZM', 'B', ['name' => 'Eka Belum', 'phone' => '081111000003']);
        $m4 = $this->makeUnit('ZM004', 'ZM', 'B', ['name' => 'Fani Lunas', 'phone' => '081111000004']);
        // ZN: 1 akun milik collector B (di luar cakupan supervisor ZM).
        $n1 = $this->makeUnit('ZN001', 'ZN', 'A', ['name' => 'Gita Luar', 'phone' => '081111000005']);

        $this->assign($this->collectorA, ['scope_type' => 'block', 'cluster_id' => 'ZM', 'block' => 'A']);
        $this->assign($this->collectorB, ['scope_type' => 'unit', 'unit_id' => $m4->id]);
        $this->assign($this->collectorB, ['scope_type' => 'cluster', 'cluster_id' => 'ZN']);

        // Jalankan refresh yang tertunda (seed + data di atas) lalu timpa state dengan angka pasti.
        app(CollectionAccountRefreshQueue::class)->flush();

        $this->state($m1, $this->collectorA, [
            'outstanding_principal' => 3000000, 'outstanding_penalty' => 150000, 'outstanding_total' => 3150000,
            'open_invoice_count' => 6, 'oldest_due_date' => '2026-03-20', 'aging_days' => 209,
            'aging_bucket' => '180_plus', 'status' => 'overdue', 'priority_score' => 92, 'priority_level' => 'critical',
            'last_contact_at' => '2026-10-10 10:00:00', 'last_contact_result' => 'refused', 'failed_contact_count' => 3,
        ]);
        $this->state($m2, $this->collectorA, [
            'outstanding_principal' => 1000000, 'outstanding_total' => 1000000, 'open_invoice_count' => 2,
            'oldest_due_date' => '2026-08-20', 'aging_days' => 56, 'aging_bucket' => '31_60',
            'status' => 'promise_to_pay', 'priority_score' => 55, 'priority_level' => 'medium',
            'next_follow_up_at' => '2026-10-20 09:00:00',
        ]);
        $this->state($m3, null, [
            'outstanding_principal' => 500000, 'outstanding_total' => 500000, 'open_invoice_count' => 1,
            'oldest_due_date' => '2026-10-20', 'next_due_date' => '2026-10-20', 'aging_days' => 0,
            'aging_bucket' => 'current', 'status' => 'due_soon', 'priority_score' => 20, 'priority_level' => 'normal',
            'last_contact_at' => '2026-10-01 08:00:00',
        ]);
        $this->state($m4, $this->collectorB, [
            'outstanding_total' => 0, 'status' => 'paid', 'priority_score' => 0, 'priority_level' => 'normal',
        ]);
        $this->state($n1, $this->collectorB, [
            'outstanding_principal' => 2000000, 'outstanding_total' => 2000000, 'open_invoice_count' => 4,
            'oldest_due_date' => '2026-06-20', 'aging_days' => 117, 'aging_bucket' => '91_180',
            'status' => 'overdue', 'priority_score' => 75, 'priority_level' => 'high',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUnit(string $id, string $clusterId, string $block, array $resident): Unit
    {
        $customer = Resident::factory()->create([
            'id' => 'RZM'.str_pad((string) ++$this->residentSequence, 5, '0', STR_PAD_LEFT),
            ...$resident,
        ]);

        return Unit::factory()->create([
            'id' => $id, 'cluster_id' => $clusterId, 'block' => $block,
            'lot_number' => (string) (int) substr($id, 2), 'resident_id' => $customer->id,
        ]);
    }

    private function makeUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['is_active' => true, ...$attributes]);
        $user->assignRole($role);

        return $user;
    }

    private function makeCollector(array $attributes = [], string $accountStatus = CollectorProfile::STATUS_ACTIVE): User
    {
        $collector = $this->makeUser('collector', $attributes);
        CollectorProfile::query()->create([
            'user_id' => $collector->id,
            'collector_code' => 'COL-M'.$collector->id,
            'account_status' => $accountStatus,
        ]);

        return $collector;
    }

    private function assign(User $collector, array $scope): CollectorAssignment
    {
        return CollectorAssignment::query()->create([
            'collector_id' => $collector->id, 'is_active' => true, 'status' => 'active', ...$scope,
        ]);
    }

    private function state(Unit $unit, ?User $collector, array $attributes): void
    {
        CollectionAccountState::query()->updateOrCreate(['unit_id' => $unit->id], [
            'customer_resident_id' => $unit->resident_id,
            'collector_id' => $collector?->id,
            'outstanding_principal' => 0, 'outstanding_penalty' => 0, 'outstanding_total' => 0,
            'open_invoice_count' => 0, 'oldest_due_date' => null, 'next_due_date' => null,
            'aging_days' => 0, 'aging_bucket' => 'current', 'last_contact_at' => null,
            'last_contact_result' => null, 'next_follow_up_at' => null, 'failed_contact_count' => 0,
            'failed_visit_count' => 0, 'broken_ptp_count' => 0, 'refreshed_at' => now(),
            ...$attributes,
        ]);
    }

    private function supervisorOf(string $clusterId): User
    {
        $supervisor = $this->makeUser('supervisor');
        SupervisorAssignment::query()->create([
            'supervisor_id' => $supervisor->id, 'cluster_id' => $clusterId, 'is_active' => true, 'status' => 'active',
        ]);

        return $supervisor;
    }

    /** @return list<string> */
    private function unitIds($response): array
    {
        return collect($response->json('data'))->pluck('unit.id')->all();
    }

    public function test_full_scope_lists_accounts_with_contract_fields_summary_and_aging(): void
    {
        Sanctum::actingAs($this->makeUser('property_manager'));

        $response = $this->getJson('/api/v1/collection/accounts?cluster_id=ZM')
            ->assertOk()
            ->assertJsonStructure([
                'success', 'message',
                'data' => [[
                    'unit' => ['id', 'cluster_id', 'cluster_name', 'block', 'lot_number'],
                    'customer' => ['id', 'name', 'phone'],
                    'collector',
                    'outstanding_principal', 'outstanding_penalty', 'outstanding_total', 'open_invoice_count',
                    'oldest_due_date', 'next_due_date', 'aging_days', 'aging_bucket', 'status',
                    'priority_score', 'priority_level', 'last_contact_at', 'last_contact_result',
                    'next_follow_up_at', 'failed_contact_count', 'failed_visit_count', 'broken_ptp_count',
                    'refreshed_at',
                ]],
                'meta' => [
                    'current_page', 'per_page', 'total', 'last_page',
                    'summary' => ['account_count', 'outstanding_total', 'by_status', 'by_priority', 'overdue_accounts', 'critical_accounts'],
                    'aging' => [['bucket', 'label', 'account_count', 'outstanding_amount', 'percentage']],
                ],
                'summary', 'aging',
            ]);

        // Default has_outstanding=1 (ZM004 lunas tidak ikut) & urut priority_score desc.
        $this->assertSame(['ZM001', 'ZM002', 'ZM003'], $this->unitIds($response));

        $first = $response->json('data.0');
        $this->assertSame('Cluster Monitor M', $first['unit']['cluster_name']);
        $this->assertSame('A', $first['unit']['block']);
        $this->assertSame('Citra Kritis', $first['customer']['name']);
        $this->assertSame(['id' => $this->collectorA->id, 'name' => 'Aan Monitor'], $first['collector']);
        $this->assertEquals(3150000, $first['outstanding_total']);
        $this->assertSame('2026-03-20', $first['oldest_due_date']);
        $this->assertNull($response->json('data.2.collector'));

        $summary = $response->json('meta.summary');
        $this->assertSame(3, $summary['account_count']);
        $this->assertEquals(4650000, $summary['outstanding_total']);
        $byStatus = $summary['by_status'];
        ksort($byStatus);
        $this->assertSame(['due_soon' => 1, 'overdue' => 1, 'promise_to_pay' => 1], $byStatus);
        $this->assertSame(1, $summary['by_priority']['critical']);
        $this->assertSame(0, $summary['by_priority']['high']);
        $this->assertSame(1, $summary['critical_accounts']);
        $this->assertSame(2, $summary['overdue_accounts']);
        $this->assertSame($summary, $response->json('summary'));

        // Aging konsisten dengan summary (filter yang sama).
        $aging = collect($response->json('meta.aging'))->keyBy('bucket');
        $this->assertCount(6, $aging);
        $this->assertSame($summary['account_count'], $aging->sum('account_count'));
        $this->assertEquals($summary['outstanding_total'], $aging->sum('outstanding_amount'));
        $this->assertSame(1, $aging['180_plus']['account_count']);
        $this->assertSame(1, $aging['current']['account_count']);
        $this->assertSame(0, $aging['91_180']['account_count']);
    }

    public function test_has_outstanding_zero_includes_paid_accounts(): void
    {
        Sanctum::actingAs($this->makeUser('admin_estate'));

        $response = $this->getJson('/api/v1/collection/accounts?cluster_id=ZM&has_outstanding=0&sort=unit_id&direction=asc')->assertOk();

        $this->assertSame(['ZM001', 'ZM002', 'ZM003', 'ZM004'], $this->unitIds($response));
        $this->assertSame(4, $response->json('meta.summary.account_count'));
        $this->assertSame(1, $response->json('meta.summary.by_status.paid'));
    }

    public function test_filters_narrow_list_and_summary_together(): void
    {
        Sanctum::actingAs($this->makeUser('root'));
        $base = '/api/v1/collection/accounts?cluster_id=ZM&sort=unit_id&direction=asc';

        $cases = [
            '&collector_id='.$this->collectorA->id => ['ZM001', 'ZM002'],
            '&unassigned=1' => ['ZM003'],
            '&status[]=overdue&status[]=due_soon' => ['ZM001', 'ZM003'],
            '&status=promise_to_pay' => ['ZM002'],
            '&aging_bucket[]=180_plus&aging_bucket[]=31_60' => ['ZM001', 'ZM002'],
            '&priority[]=critical' => ['ZM001'],
            '&min_outstanding=600000&max_outstanding=2000000' => ['ZM002'],
            '&block=B' => ['ZM003'],
            '&search=ZM002' => ['ZM002'],
            '&search=citra' => ['ZM001'],
            '&search=081111000003' => ['ZM003'],
        ];

        foreach ($cases as $query => $expected) {
            $response = $this->getJson($base.$query)->assertOk();
            $this->assertSame($expected, $this->unitIds($response), $query);
            $this->assertSame(count($expected), $response->json('meta.summary.account_count'), $query);
            $this->assertSame(count($expected), collect($response->json('meta.aging'))->sum('account_count'), $query);
        }

        // Tanpa filter cluster: collector B hanya memegang ZN001 yang masih menunggak.
        $response = $this->getJson('/api/v1/collection/accounts?collector_id='.$this->collectorB->id)->assertOk();
        $this->assertSame(['ZN001'], $this->unitIds($response));
    }

    public function test_sort_allowlist_and_ordering(): void
    {
        Sanctum::actingAs($this->makeUser('root'));

        $this->getJson('/api/v1/collection/accounts?sort=customer_resident_id')
            ->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->getJson('/api/v1/collection/accounts?sort=outstanding_total;drop')
            ->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->getJson('/api/v1/collection/accounts?direction=sideways')
            ->assertStatus(422)->assertJsonValidationErrors('direction');
        $this->getJson('/api/v1/collection/accounts?status[]=bogus')
            ->assertStatus(422)->assertJsonValidationErrors('status.0');
        $this->getJson('/api/v1/collection/accounts?priority[]=urgent')
            ->assertStatus(422)->assertJsonValidationErrors('priority.0');
        $this->getJson('/api/v1/collection/accounts?aging_bucket[]=999')
            ->assertStatus(422)->assertJsonValidationErrors('aging_bucket.0');

        $base = '/api/v1/collection/accounts?cluster_id=ZM';
        $this->assertSame(['ZM003', 'ZM002', 'ZM001'], $this->unitIds($this->getJson($base.'&sort=outstanding_total&direction=asc')));
        $this->assertSame(['ZM001', 'ZM002', 'ZM003'], $this->unitIds($this->getJson($base.'&sort=aging_days')));
        $this->assertSame(['ZM001', 'ZM002', 'ZM003'], $this->unitIds($this->getJson($base.'&sort=oldest_due_date&direction=asc')));
        // Nilai null selalu di akhir, baik asc maupun desc.
        $this->assertSame(['ZM001', 'ZM003', 'ZM002'], $this->unitIds($this->getJson($base.'&sort=last_contact_at&direction=desc')));
        $this->assertSame(['ZM003', 'ZM001', 'ZM002'], $this->unitIds($this->getJson($base.'&sort=last_contact_at&direction=asc')));
        $this->assertSame(['ZM002', 'ZM001', 'ZM003'], $this->unitIds($this->getJson($base.'&sort=next_follow_up_at&direction=asc')));
        $this->assertSame(['ZM003', 'ZM002', 'ZM001'], $this->unitIds($this->getJson($base.'&sort=unit_id&direction=desc')));
    }

    public function test_supervisor_is_limited_to_own_clusters(): void
    {
        Sanctum::actingAs($this->supervisorOf('ZM'));

        $response = $this->getJson('/api/v1/collection/accounts?sort=unit_id&direction=asc')->assertOk();
        $this->assertSame(['ZM001', 'ZM002', 'ZM003'], $this->unitIds($response));
        $this->assertSame(3, $response->json('meta.summary.account_count'));

        // Filter cluster di luar cakupan → kosong (bukan bocor).
        $this->assertSame([], $this->unitIds($this->getJson('/api/v1/collection/accounts?cluster_id=ZN')->assertOk()));

        // Collector B menyentuh ZM (unit ZM004) → boleh difilter, tapi hanya akun dalam cakupan.
        $response = $this->getJson('/api/v1/collection/accounts?has_outstanding=0&collector_id='.$this->collectorB->id)->assertOk();
        $this->assertSame(['ZM004'], $this->unitIds($response));

        // Collector yang tidak menyentuh cluster supervisor → 403.
        $outsider = $this->makeCollector(['name' => 'Orang Luar']);
        $this->assign($outsider, ['scope_type' => 'cluster', 'cluster_id' => 'ZN']);
        $this->getJson('/api/v1/collection/accounts?collector_id='.$outsider->id)->assertForbidden();

        // Supervisor tanpa assignment cluster sama sekali → tidak melihat apa pun.
        Sanctum::actingAs($this->makeUser('supervisor'));
        $response = $this->getJson('/api/v1/collection/accounts')->assertOk();
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('meta.summary.account_count'));
    }

    public function test_collector_cannot_open_monitoring_and_property_manager_sees_all(): void
    {
        Sanctum::actingAs($this->collectorA);
        $this->getJson('/api/v1/collection/accounts')->assertForbidden();

        Sanctum::actingAs($this->makeUser('property_manager'));
        $response = $this->getJson('/api/v1/collection/accounts?per_page=100')->assertOk();

        $expected = CollectionAccountState::query()
            ->where('outstanding_total', '>', 0)
            ->whereIn('unit_id', Unit::query()->select('id'))
            ->count();
        $this->assertSame($expected, $response->json('meta.total'));
        $this->assertSame($expected, $response->json('meta.summary.account_count'));
        $this->assertContains('ZN001', $this->unitIds($this->getJson('/api/v1/collection/accounts?cluster_id=ZN')));
    }

    public function test_per_page_is_clamped(): void
    {
        Sanctum::actingAs($this->makeUser('root'));

        $this->assertSame(100, $this->getJson('/api/v1/collection/accounts?per_page=1000')->assertOk()->json('meta.per_page'));
        $this->assertSame(15, $this->getJson('/api/v1/collection/accounts?per_page=0')->assertOk()->json('meta.per_page'));

        $response = $this->getJson('/api/v1/collection/accounts?cluster_id=ZM&per_page=2')->assertOk();
        $this->assertSame(2, $response->json('meta.per_page'));
        $this->assertCount(2, $response->json('data'));
        $this->assertSame(3, $response->json('meta.total'));
        $this->assertSame(2, $response->json('meta.last_page'));
        // Ringkasan tetap untuk seluruh hasil filter, bukan hanya halaman ini.
        $this->assertSame(3, $response->json('meta.summary.account_count'));
    }

    public function test_deleted_units_are_excluded(): void
    {
        Sanctum::actingAs($this->makeUser('root'));

        Unit::query()->findOrFail('ZM001')->delete();
        app(CollectionAccountRefreshQueue::class)->flush();
        // Pastikan baris state masih ada (yang diuji adalah filter unit terhapus di endpoint).
        $this->state(Unit::withTrashed()->findOrFail('ZM001'), $this->collectorA, [
            'outstanding_total' => 3150000, 'aging_bucket' => '180_plus', 'status' => 'overdue', 'priority_level' => 'critical',
        ]);

        $response = $this->getJson('/api/v1/collection/accounts?cluster_id=ZM')->assertOk();
        $this->assertSame(['ZM002', 'ZM003'], $this->unitIds($response));
        $this->assertSame(2, $response->json('meta.summary.account_count'));
        $this->assertSame(0, $response->json('meta.summary.critical_accounts'));
        $this->assertSame(0, collect($response->json('meta.aging'))->firstWhere('bucket', '180_plus')['account_count']);
    }

    public function test_collector_options_are_scoped_sorted_and_hide_inactive_by_default(): void
    {
        $inactive = $this->makeCollector(['name' => 'Cici Nonaktif'], CollectorProfile::STATUS_INACTIVE);
        $disabled = $this->makeCollector(['name' => 'Dodi Dimatikan', 'is_active' => false]);
        $legacy = $this->makeUser('collector', ['name' => 'Eko Tanpa Profil']);
        $deleted = $this->makeCollector(['name' => 'Fajar Terhapus']);
        $deleted->delete();

        Sanctum::actingAs($this->makeUser('admin_estate'));

        $options = collect($this->getJson('/api/v1/collection/collectors/options')->assertOk()->json('data'));
        $ids = $options->pluck('id')->all();
        $this->assertContains($this->collectorA->id, $ids);
        $this->assertContains($legacy->id, $ids, 'Collector lama tanpa profil tetap aktif.');
        $this->assertNotContains($inactive->id, $ids);
        $this->assertNotContains($disabled->id, $ids);
        $this->assertNotContains($deleted->id, $ids);

        $names = $options->pluck('name')->all();
        $sorted = $names;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);
        $this->assertSame($sorted, $names, 'Urut berdasarkan nama.');

        $this->assertSame([
            'id' => $this->collectorA->id,
            'name' => 'Aan Monitor',
            'username' => $this->collectorA->username,
            'collector_code' => 'COL-M'.$this->collectorA->id,
            'account_status' => 'active',
            'is_active' => true,
        ], $options->firstWhere('id', $this->collectorA->id));
        $this->assertSame('active', $options->firstWhere('id', $legacy->id)['account_status']);
        $this->assertNull($options->firstWhere('id', $legacy->id)['collector_code']);

        $all = collect($this->getJson('/api/v1/collection/collectors/options?include_inactive=1')->assertOk()->json('data'));
        $this->assertContains($inactive->id, $all->pluck('id'));
        $this->assertContains($disabled->id, $all->pluck('id'));
        $this->assertNotContains($deleted->id, $all->pluck('id'));
        $this->assertSame('inactive', $all->firstWhere('id', $inactive->id)['account_status']);
        $this->assertFalse($all->firstWhere('id', $disabled->id)['is_active']);

        $search = $this->getJson('/api/v1/collection/collectors/options?search=bobi')->assertOk()->json('data');
        $this->assertSame([$this->collectorB->id], array_column($search, 'id'));
        $search = $this->getJson('/api/v1/collection/collectors/options?search=COL-M'.$this->collectorA->id)->assertOk()->json('data');
        $this->assertContains($this->collectorA->id, array_column($search, 'id'));
    }

    public function test_collector_options_scope_for_supervisor_and_collector(): void
    {
        $unassigned = $this->makeCollector(['name' => 'Gilang Bebas']);

        Sanctum::actingAs($this->supervisorOf('ZN'));
        $ids = array_column($this->getJson('/api/v1/collection/collectors/options?include_inactive=1')->assertOk()->json('data'), 'id');
        $this->assertSame([$this->collectorB->id], $ids);

        Sanctum::actingAs($this->supervisorOf('ZM'));
        $ids = array_column($this->getJson('/api/v1/collection/collectors/options')->assertOk()->json('data'), 'id');
        $this->assertEqualsCanonicalizing([$this->collectorA->id, $this->collectorB->id], $ids);
        $this->assertNotContains($unassigned->id, $ids);

        // Collector (punya collector-performance.view) hanya melihat dirinya.
        Sanctum::actingAs($this->collectorA);
        $ids = array_column($this->getJson('/api/v1/collection/collectors/options')->assertOk()->json('data'), 'id');
        $this->assertSame([$this->collectorA->id], $ids);

        // Full scope melihat collector tanpa assignment.
        Sanctum::actingAs($this->makeUser('property_manager'));
        $ids = array_column($this->getJson('/api/v1/collection/collectors/options')->assertOk()->json('data'), 'id');
        $this->assertContains($unassigned->id, $ids);
    }

    public function test_monitoring_index_migration_rolls_back_and_reapplies(): void
    {
        $migration = require database_path('migrations/2026_10_08_000010_add_monitoring_indexes_to_collection_account_states_table.php');
        $indexes = [
            'collection_account_states_priority_score_index',
            'collection_account_states_outstanding_total_index',
            'collection_account_states_aging_days_index',
        ];

        foreach ($indexes as $index) {
            $this->assertTrue(Schema::hasIndex('collection_account_states', $index), $index);
        }

        $migration->down();
        foreach ($indexes as $index) {
            $this->assertFalse(Schema::hasIndex('collection_account_states', $index), $index);
        }

        $migration->up();
        $migration->up(); // idempoten
        foreach ($indexes as $index) {
            $this->assertTrue(Schema::hasIndex('collection_account_states', $index), $index);
        }
    }
}
