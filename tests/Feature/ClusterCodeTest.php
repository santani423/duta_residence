<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Cluster;
use App\Models\Unit;
use App\Models\User;
use App\Services\ClusterCodeService;
use Database\Seeders\EstateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kode properti = prefix cluster + nomor blok (E3 = Emerald 48 blok 3, E7 = Ruko Emerald blok 7).
 */
class ClusterCodeTest extends TestCase
{
    use RefreshDatabase;

    private ClusterCodeService $codes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstateSeeder::class);
        $this->codes = new ClusterCodeService;
    }

    /** @return array<string, array{string, string, int}> */
    public static function officialCodes(): array
    {
        return [
            'AX1 Alexandrite' => ['AX1', '11', 1],
            'AL1 Ruko Alexandrite' => ['AL1', '07', 1],
            'AR1 Amber' => ['AR1', '04', 1],
            'AM1 Ruko Amber' => ['AM1', '06', 1],
            'E3 Emerald 48' => ['E3', '10', 3],
            'E6 Emerald 48' => ['E6', '10', 6],
            'E7 Ruko Emerald' => ['E7', '12', 7],
            'D6 Dolomite' => ['D6', '01', 6],
            'D7 Ruko Dolomite' => ['D7', '14', 7],
            'D8 Ruko Dolomite' => ['D8', '14', 8],
            'B10 prefix 1 huruf' => ['B10', '13', 10],
            'DD2 prefix DD bukan D' => ['DD2', '05', 2],
            'JA3 prefix JA' => ['JA3', '08', 3],
            'huruf kecil dan pemisah' => ['al-2', '07', 2],
        ];
    }

    #[DataProvider('officialCodes')]
    public function test_code_is_parsed_into_cluster_prefix_and_block_number(string $code, string $clusterId, int $block): void
    {
        $parsed = $this->codes->parse($code);

        $this->assertNotNull($parsed);
        $this->assertSame($clusterId, $parsed['cluster']->id);
        $this->assertSame($block, $parsed['block_number']);
        $this->assertSame($parsed['cluster']->code_prefix, $parsed['prefix']);
        $this->assertSame($parsed['prefix'].$block, $parsed['code']);
    }

    #[DataProvider('invalidCodes')]
    public function test_codes_outside_the_configuration_are_rejected(string $code): void
    {
        $this->assertNull($this->codes->parse($code));
    }

    /** @return array<string, array{string}> */
    public static function invalidCodes(): array
    {
        return [
            'Emerald blok 8' => ['E8'],
            'Emerald blok 0' => ['E0'],
            'blok 7 Dolomite lama' => ['D9'],
            'prefix tidak dikenal' => ['ZZ1'],
            'tanpa nomor' => ['AX'],
            'hanya angka' => ['12'],
            'prefix A saja' => ['A10'],
        ];
    }

    public function test_hinting_the_cluster_disambiguates_shared_prefixes(): void
    {
        $this->assertSame('01', $this->codes->parse('D6', '01')['cluster']->id);
        $this->assertNull($this->codes->parse('D6', '14'));
        $this->assertNull($this->codes->parse('E3', '12'));
    }

    public function test_cluster_configuration_is_synced_and_prefix_conflicts_are_detected(): void
    {
        $emerald = Cluster::query()->find('10');
        $this->assertSame('E', $emerald->code_prefix);
        $this->assertSame([1, 2, 3, 4, 5, 6], $emerald->allowed_blocks);
        $this->assertSame('ruko', Cluster::query()->find('12')->area_type);
        $this->assertNull(Cluster::query()->find('11')->allowed_blocks);

        // Prefix E dengan blok yang tumpang tindih (5) atau tanpa batas bentrok dengan Emerald 48.
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->codes->assertNoCodeConflict('E', [5, 6]);
    }

    public function test_non_overlapping_blocks_can_share_a_prefix(): void
    {
        $this->codes->assertNoCodeConflict('E', [8, 9]);
        $this->addToAssertionCount(1);
    }

    private function actAsRoot(): void
    {
        // Tanpa data pelanggan asli supaya hasil filter deterministik.
        $this->seed([RolePermissionSeeder::class, AdminUserSeeder::class]);
        Sanctum::actingAs(User::where('username', 'root')->first());
    }

    private function unitPayload(array $overrides = []): array
    {
        return [
            'cluster_id' => '10',
            'block' => 'E3',
            'lot_number' => '905',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'AK',
            ...$overrides,
        ];
    }

    public function test_creating_a_unit_stores_combined_code_and_separate_block_number(): void
    {
        $this->actAsRoot();

        $this->postJson('/api/v1/units', $this->unitPayload())
            ->assertCreated()
            ->assertJsonPath('data.block', 'E3')
            ->assertJsonPath('data.block_number', 3)
            ->assertJsonPath('data.cluster.code_prefix', 'E');

        // Hanya nomor blok: kode gabungan disusun backend dari prefix cluster.
        $this->postJson('/api/v1/units', $this->unitPayload(['cluster_id' => '12', 'block' => null, 'block_number' => 7, 'lot_number' => '906']))
            ->assertCreated()
            ->assertJsonPath('data.block', 'E7')
            ->assertJsonPath('data.cluster_id', '12');
    }

    public function test_invalid_block_for_the_selected_cluster_is_rejected_by_the_backend(): void
    {
        $this->actAsRoot();

        // Blok 7 milik Ruko Emerald, bukan Emerald 48; blok 8 di luar konfigurasi.
        $this->postJson('/api/v1/units', $this->unitPayload(['block' => 'E7']))->assertUnprocessable()->assertJsonValidationErrors('block_number');
        $this->postJson('/api/v1/units', $this->unitPayload(['block' => 'E8']))->assertUnprocessable()->assertJsonValidationErrors('block_number');
        $this->postJson('/api/v1/units', $this->unitPayload(['cluster_id' => '12', 'block' => 'E3']))->assertUnprocessable();
        $this->postJson('/api/v1/units', $this->unitPayload(['block' => 'D3']))->assertUnprocessable()->assertJsonValidationErrors('block');
        $this->postJson('/api/v1/units', $this->unitPayload(['block' => null, 'block_number' => 0]))->assertUnprocessable();

        $this->assertSame(0, Unit::query()->count());
    }

    public function test_cluster_blocks_endpoint_lists_valid_block_numbers(): void
    {
        $this->actAsRoot();

        $this->getJson('/api/v1/clusters/14/blocks')
            ->assertOk()
            ->assertJsonPath('data.code_prefix', 'D')
            ->assertJsonPath('data.blocks.0.code', 'D7')
            ->assertJsonPath('data.blocks.1.block_number', 8)
            ->assertJsonCount(2, 'data.blocks');

        $this->getJson('/api/v1/clusters/10/blocks')->assertJsonCount(6, 'data.blocks');
    }

    public function test_code_search_and_filters_resolve_to_the_right_cluster_and_block(): void
    {
        $this->actAsRoot();

        $e3 = Unit::factory()->create(['id' => 'ZE901', 'cluster_id' => '10', 'block' => 'E3', 'lot_number' => '901']);
        $e5 = Unit::factory()->create(['id' => 'ZE902', 'cluster_id' => '10', 'block' => 'E5', 'lot_number' => '901']);
        $e7 = Unit::factory()->create(['id' => 'ZE903', 'cluster_id' => '12', 'block' => 'E7', 'lot_number' => '901']);
        $d7 = Unit::factory()->create(['id' => 'ZE904', 'cluster_id' => '14', 'block' => 'D7', 'lot_number' => '901']);
        $d6 = Unit::factory()->create(['id' => 'ZE905', 'cluster_id' => '01', 'block' => 'D6', 'lot_number' => '901']);

        $this->assertSame(3, $e3->refresh()->block_number);

        $ids = fn (string $query) => collect($this->getJson("/api/v1/units?{$query}&per_page=50")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        // Pencarian kode dan filter Alamat.
        $this->assertContains($e3->id, $ids('search=E3'));
        $this->assertNotContains($e7->id, $ids('search=E3'));
        $this->assertContains($e7->id, $ids('search=E7'));
        $this->assertNotContains($e3->id, $ids('search=E7'));
        $this->assertContains($e7->id, $ids('address=E7'));
        $this->assertContains($d7->id, $ids('search=D7'));
        $this->assertNotContains($d6->id, $ids('search=D7'));

        // Filter cluster + blok saling terhubung.
        $this->assertSame([$e3->id], $ids('cluster_id=10&block=E3'));
        $this->assertSame([$e3->id], $ids('cluster_id=10&block_number=3'));
        $this->assertSame([], $ids('cluster_id=12&block=E3'));
        $this->assertSame([$d7->id], $ids('cluster_id=14&block_number=7'));
        $this->assertNotContains($e5->id, $ids('cluster_id=10&block_number=3'));
    }

    public function test_billing_and_payment_history_stay_linked_to_the_unit_after_block_edit(): void
    {
        $this->actAsRoot();

        $unit = Unit::factory()->create(['id' => 'ZE906', 'cluster_id' => '10', 'block' => 'E3', 'lot_number' => '909']);
        $billing = Billing::factory()->create(['unit_id' => $unit->id, 'year' => 2026, 'month' => 1]);

        $this->putJson("/api/v1/units/{$unit->id}", $this->unitPayload(['block' => 'E4', 'lot_number' => '909']))
            ->assertOk()
            ->assertJsonPath('data.block_number', 4);

        $this->assertSame('E4', $unit->refresh()->block);
        $this->assertSame($unit->id, $billing->refresh()->unit_id);
        $this->assertSame(1, $unit->billings()->count());
    }

    public function test_legacy_units_with_nonstandard_codes_stay_readable_and_editable_without_being_remapped(): void
    {
        $this->actAsRoot();

        // E8 di Emerald 48 (di luar blok 1-6): tidak dipetakan, block_number NULL.
        $legacy = Unit::factory()->create(['id' => 'ZE907', 'cluster_id' => '10', 'block' => 'E8', 'lot_number' => '902']);
        $this->assertNull($legacy->refresh()->block_number);

        $this->getJson("/api/v1/units/{$legacy->id}")->assertOk()->assertJsonPath('data.block', 'E8');

        // Mengedit field lain tanpa mengubah cluster/blok tetap diterima dan kode lama dipertahankan.
        $this->putJson("/api/v1/units/{$legacy->id}", $this->unitPayload(['block' => 'E8', 'lot_number' => '902', 'notes' => 'cek']))
            ->assertOk();

        $legacy->refresh();
        $this->assertSame('E8', $legacy->block);
        $this->assertNull($legacy->block_number);

        // Tetapi memilih blok baru yang tidak valid ditolak.
        $this->putJson("/api/v1/units/{$legacy->id}", $this->unitPayload(['block' => 'E9', 'lot_number' => '902']))->assertUnprocessable();
    }

    public function test_backfill_fills_only_valid_codes_and_audit_reports_the_rest_without_changing_them(): void
    {
        $units = [
            ['ZE911', '11', 'AX3'],
            ['ZE912', '10', 'E8'],
            ['ZE913', '07', 'AL-'],
            ['ZE914', '01', 'D7'],
        ];
        foreach ($units as $i => [$id, $cluster, $block]) {
            Unit::factory()->create(['id' => $id, 'cluster_id' => $cluster, 'block' => $block, 'lot_number' => (string) (900 + $i)]);
        }
        // Keadaan sebelum migrasi: block_number belum terisi.
        DB::table('units')->update(['block_number' => null]);

        $service = new ClusterCodeService;
        $this->assertSame(1, $service->backfillUnitBlockNumbers());
        $this->assertSame(3, (int) DB::table('units')->where('id', 'ZE911')->value('block_number'));
        $this->assertSame(3, DB::table('units')->whereNull('block_number')->count());

        $issues = collect($service->auditUnits())->keyBy('unit_id');
        $this->assertSame(['ZE912', 'ZE913', 'ZE914'], $issues->keys()->sort()->values()->all());
        // D7 di Dolomite sebenarnya kode Ruko Dolomite: dilaporkan sebagai saran, tidak dipindah otomatis.
        $this->assertSame('14', $issues['ZE914']['suggestion']);
        $this->assertSame('01', DB::table('units')->where('id', 'ZE914')->value('cluster_id'));
        $this->assertSame('D7', DB::table('units')->where('id', 'ZE914')->value('block'));
    }

    public function test_cluster_api_validates_and_blocks_prefix_changes_for_clusters_with_units(): void
    {
        $this->actAsRoot();

        $this->postJson('/api/v1/clusters', ['id' => 'ZA', 'name' => 'Zircon', 'monthly_rate' => 100000, 'code_prefix' => 'E'])
            ->assertUnprocessable()->assertJsonValidationErrors('code_prefix');

        $this->postJson('/api/v1/clusters', ['id' => 'ZA', 'name' => 'Zircon', 'monthly_rate' => 100000, 'code_prefix' => 'ZR', 'area_type' => 'cluster'])
            ->assertCreated()->assertJsonPath('data.code_prefix', 'ZR');

        Unit::factory()->create(['id' => 'ZE908', 'cluster_id' => '10', 'block' => 'E1', 'lot_number' => '903']);
        $this->putJson('/api/v1/clusters/10', ['monthly_rate' => 150000, 'code_prefix' => 'EX'])->assertUnprocessable();
        $this->assertSame('E', Cluster::query()->find('10')->code_prefix);
    }
}
