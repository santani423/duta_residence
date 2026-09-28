<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Cluster;
use App\Models\Installment;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\EstateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Filter Cluster/Blok/Unit/Customer/Alamat harus berperilaku sama di semua daftar Unit & Tagihan.
 */
class UnitFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Unit $target;

    private Unit $sameClusterOtherBlock;

    private Unit $prefixBlock;

    private Unit $otherCluster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstateSeeder::class);

        Cluster::query()->create(['id' => 'ZZ', 'name' => 'Cluster Zinnia', 'monthly_rate' => 300000, 'is_active' => true]);
        Cluster::query()->create(['id' => 'ZY', 'name' => 'Cluster Yasmin', 'monthly_rate' => 300000, 'is_active' => true]);

        $this->target = $this->unit('ZQ901', 'ZZ', 'Q', '12', 'Budi Filtertest');
        $this->sameClusterOtherBlock = $this->unit('ZQ902', 'ZZ', 'R', '12', 'Sari Filtertest');
        $this->prefixBlock = $this->unit('ZQ903', 'ZZ', 'QQ', '7', 'Tono Filtertest');
        $this->otherCluster = $this->unit('ZQ904', 'ZY', 'Q', '12', 'Budi Lainnya');

        foreach ([$this->target, $this->sameClusterOtherBlock, $this->prefixBlock, $this->otherCluster] as $unit) {
            Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 1, 'amount' => 100000, 'status_id' => Billing::STATUS_UNPAID]);
            Installment::query()->create(['unit_id' => $unit->id, 'amount' => 50000, 'payment_date' => '2026-01-10']);
        }

        $permissions = ['units.view', 'billings.view', 'reports.view', 'installments.view'];
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo($permissions);
        Sanctum::actingAs($user);
    }

    private function unit(string $id, string $cluster, string $block, string $lot, string $owner): Unit
    {
        return Unit::factory()->create([
            'id' => $id,
            'cluster_id' => $cluster,
            'block' => $block,
            'lot_number' => $lot,
            'resident_id' => Resident::factory()->create(['name' => $owner])->id,
        ]);
    }

    /** @return list<string> */
    private function unitIds(string $url, string $column = 'unit_id'): array
    {
        return collect($this->getJson($url)->assertOk()->json('data'))->pluck($column)->unique()->sort()->values()->all();
    }

    public function test_block_filter_matches_exactly_within_cluster_on_every_list(): void
    {
        $query = 'cluster_id=ZZ&block=q&per_page=50';

        $this->assertSame(['ZQ901'], $this->unitIds("/api/v1/units?{$query}", 'id'));
        $this->assertSame(['ZQ901'], $this->unitIds("/api/v1/billings?{$query}"));
        $this->assertSame(['ZQ901'], $this->unitIds("/api/v1/receivables?{$query}"));
        $this->assertSame(['ZQ901'], $this->unitIds("/api/v1/installments?{$query}"));
    }

    public function test_customer_and_address_filters(): void
    {
        $this->assertSame(['ZQ901', 'ZQ904'], $this->unitIds('/api/v1/billings?customer=budi&per_page=50'));
        $this->assertSame(['ZQ901', 'ZQ902', 'ZQ903'], $this->unitIds('/api/v1/billings?address=zinnia&per_page=50'));
        // "Q/12" dibaca sebagai Blok Q nomor 12.
        $this->assertSame(['ZQ901', 'ZQ904'], $this->unitIds('/api/v1/installments?address='.rawurlencode('Q/12').'&customer=budi&per_page=50'));
    }

    public function test_unit_filter_is_case_insensitive(): void
    {
        $this->assertSame(['ZQ902'], $this->unitIds('/api/v1/receivables?unit_id=zq902&per_page=50'));
    }

    public function test_receivable_aging_follows_filters(): void
    {
        $all = $this->getJson('/api/v1/receivables/aging?cluster_id=ZZ')->assertOk()->json('data.day_buckets');
        $one = $this->getJson('/api/v1/receivables/aging?cluster_id=ZZ&block=Q')->assertOk()->json('data.day_buckets');

        $this->assertEqualsWithDelta(array_sum($all) / 3, array_sum($one), 0.01);
    }

    public function test_blocks_lookup_depends_on_cluster(): void
    {
        $this->assertSame(['Q', 'QQ', 'R'], $this->getJson('/api/v1/units/blocks?cluster_id=ZZ')->assertOk()->json('data'));
        $this->assertSame(['Q'], $this->getJson('/api/v1/units/blocks?cluster_id=ZY')->assertOk()->json('data'));
    }
}
