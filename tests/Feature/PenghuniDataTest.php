<?php

namespace Tests\Feature;

use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\EstateSeeder;
use Database\Seeders\ResidentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Daftar penghuni dari buku IPL docs/final_revisi_1.md (lihat PenghuniData): kolom block berisi
 * prefix cluster + nomor blok (E1 = Emerald 48 blok 1, E7 = Ruko Emerald blok 7) dan nama
 * penghuni diambil dari pembayaran terakhir.
 */
class PenghuniDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Tanpa seeder tagihan/pembayaran: yang diuji hanya data unit dan penghuni.
        $this->seed([RolePermissionSeeder::class, EstateSeeder::class, AdminUserSeeder::class, ResidentSeeder::class, UnitSeeder::class]);
    }

    private function unit(string $block, string $lot): Unit
    {
        return Unit::query()->with('resident')->where('block', $block)
            ->whereIn('lot_number', [$lot, str_pad($lot, 2, '0', STR_PAD_LEFT)])->sole();
    }

    public function test_emerald_codes_map_to_emerald_48_blocks_1_to_6_and_ruko_emerald_block_7(): void
    {
        $units = Unit::query()->where('block', 'like', 'E%')->get();

        $this->assertGreaterThan(300, $units->count());
        foreach ($units as $unit) {
            $number = (int) substr($unit->block, 1);
            $this->assertSame($number === 7 ? '12' : '10', $unit->cluster_id, "{$unit->id} {$unit->block}");
            $this->assertEquals($number, $unit->block_number, "{$unit->id} {$unit->block}");
        }
    }

    public function test_unit_only_in_the_ipl_book_is_added_with_its_latest_payer_as_resident(): void
    {
        $unit = $this->unit('E3', '28');

        $this->assertSame('10', $unit->cluster_id);
        $this->assertSame('Silvana Anita', $unit->resident->name);
        $this->assertSame('2', $unit->occupancy_id);
        $this->assertSame('AK', $unit->status_id);
        $this->assertTrue(User::query()->where('username', "customer.{$unit->id}")->where('resident_id', $unit->resident_id)->exists());

        // E4/2 tercatat atas nama Nina Nopianti lalu Sukarna; yang dipakai pembayar terakhir.
        $this->assertSame('Sukarna', $this->unit('E4', '2')->resident->name);
    }

    public function test_existing_unit_takes_the_latest_name_but_keeps_its_contact_data(): void
    {
        $unit = $this->unit('D2', '2');

        $this->assertSame('01091', $unit->id);
        $this->assertSame('Denny', $unit->resident->name);
        $this->assertSame('085711230905', $unit->resident->phone);
    }

    public function test_resident_list_block_filter_follows_the_cluster_code(): void
    {
        Sanctum::actingAs(User::where('username', 'root')->first());

        $listed = fn (string $block) => collect($this->getJson("/api/v1/residents?block={$block}&per_page=500")->assertOk()->json('data'))
            ->pluck('id')->sort()->values()->all();
        $expected = fn (string $clusterId, int $number) => Unit::query()->where('cluster_id', $clusterId)->where('block_number', $number)
            ->whereNotNull('resident_id')->pluck('resident_id')->unique()->sort()->values()->all();

        $this->assertNotEmpty($expected('12', 7));
        $this->assertSame($expected('12', 7), $listed('E7'));
        $this->assertSame($expected('10', 1), $listed('E1'));
        // C1 tidak lagi ikut menampilkan penghuni blok C10/C11.
        $this->assertSame($expected('09', 1), $listed('C1'));
    }
}
