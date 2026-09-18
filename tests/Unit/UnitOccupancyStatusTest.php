<?php

namespace Tests\Unit;

use App\Models\Resident;
use App\Models\Unit;
use Database\Seeders\EstateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Unit::getOccupancyStatusAttribute() adalah satu-satunya sumber status Ready Stock/
 * Tanah Kosong/Booked/Occupied di seluruh aplikasi (API, dashboard, filter). Test ini menutup
 * semua kombinasi tipe unit x penghuni dari spesifikasi, termasuk lifecycle penghuni
 * ditambahkan/dipindahkan dan penghuni lama/soft-deleted yang tidak boleh dihitung aktif.
 */
class UnitOccupancyStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Hanya butuh lookup tables (cluster, property type, occupancy/resident status,
        // district) - bukan seluruh DatabaseSeeder - supaya Unit::count() di test ini tetap
        // presisi terhadap unit yang dibuat oleh test, bukan tercampur data seeder lain.
        $this->seed(EstateSeeder::class);
    }

    public function test_bangunan_tanpa_penghuni_is_ready_stock(): void
    {
        $unit = Unit::factory()->create(['property_type_id' => 'B', 'resident_id' => null, 'status_id' => 'TA']);

        $this->assertSame('ready_stock', $unit->occupancy_status);
        $this->assertSame('Ready Stock', $unit->occupancy_status_label);
    }

    public function test_kavling_tanpa_penghuni_is_tanah_kosong(): void
    {
        $unit = Unit::factory()->create(['property_type_id' => 'K', 'resident_id' => null, 'status_id' => 'TA']);

        $this->assertSame('tanah_kosong', $unit->occupancy_status);
        $this->assertSame('Tanah Kosong', $unit->occupancy_status_label);
    }

    public function test_bangunan_dengan_penghuni_aktif_is_occupied(): void
    {
        $unit = Unit::factory()->create(['property_type_id' => 'B', 'status_id' => 'AK']);

        $this->assertSame('occupied', $unit->occupancy_status);
        $this->assertSame('Occupied', $unit->occupancy_status_label);
    }

    public function test_kavling_dengan_penghuni_aktif_is_occupied(): void
    {
        $unit = Unit::factory()->create(['property_type_id' => 'P', 'status_id' => 'AK']);

        $this->assertSame('occupied', $unit->occupancy_status);
    }

    public function test_ruko_behaves_like_bangunan(): void
    {
        $vacant = Unit::factory()->create(['property_type_id' => 'R', 'resident_id' => null, 'status_id' => 'TA']);
        $occupied = Unit::factory()->create(['property_type_id' => 'R', 'status_id' => 'AK']);

        $this->assertSame('ready_stock', $vacant->occupancy_status);
        $this->assertSame('occupied', $occupied->occupancy_status);
    }

    public function test_bangunan_dengan_penghuni_lama_inactive_is_still_ready_stock(): void
    {
        // resident_id tetap terisi (penghuni lama/sudah pindah), tapi status_id bukan AK -
        // tidak boleh dihitung sebagai penghuni aktif.
        $unit = Unit::factory()->inactive()->create(['property_type_id' => 'B']);

        $this->assertNotNull($unit->resident_id);
        $this->assertSame('ready_stock', $unit->occupancy_status);
    }

    public function test_kavling_dengan_penghuni_lama_inactive_is_still_tanah_kosong(): void
    {
        $unit = Unit::factory()->vacant()->create(['property_type_id' => 'K']);

        $this->assertNotNull($unit->resident_id);
        $this->assertSame('tanah_kosong', $unit->occupancy_status);
    }

    public function test_soft_deleted_resident_does_not_count_as_active_occupant(): void
    {
        $resident = Resident::factory()->create();
        $unit = Unit::factory()->create(['property_type_id' => 'B', 'status_id' => 'AK', 'resident_id' => $resident->id]);

        $this->assertSame('occupied', $unit->fresh()->occupancy_status);

        $resident->delete();

        $this->assertSame('ready_stock', $unit->fresh()->occupancy_status);
    }

    public function test_tenant_resident_alone_can_also_make_a_unit_occupied(): void
    {
        $tenant = Resident::factory()->create();
        $unit = Unit::factory()->create([
            'property_type_id' => 'B',
            'status_id' => 'AK',
            'resident_id' => null,
            'tenant_resident_id' => $tenant->id,
        ]);

        $this->assertSame('occupied', $unit->occupancy_status);
    }

    public function test_lifecycle_adding_then_removing_resident_toggles_status_automatically(): void
    {
        $unit = Unit::factory()->create(['property_type_id' => 'B', 'resident_id' => null, 'status_id' => 'TA']);
        $this->assertSame('ready_stock', $unit->occupancy_status);

        $resident = Resident::factory()->create();
        $unit->update(['resident_id' => $resident->id, 'status_id' => 'AK']);
        $this->assertSame('occupied', $unit->fresh()->occupancy_status);

        // Penghuni pindah/dihapus: status_id dikembalikan (mis. lewat alur non-aktifkan unit).
        $unit->update(['status_id' => 'TA']);
        $this->assertSame('ready_stock', $unit->fresh()->occupancy_status);
    }

    public function test_lifecycle_works_the_same_way_for_kavling(): void
    {
        $unit = Unit::factory()->create(['property_type_id' => 'K', 'resident_id' => null, 'status_id' => 'TA']);
        $this->assertSame('tanah_kosong', $unit->occupancy_status);

        $resident = Resident::factory()->create();
        $unit->update(['resident_id' => $resident->id, 'status_id' => 'AK']);
        $this->assertSame('occupied', $unit->fresh()->occupancy_status);

        $unit->update(['status_id' => 'RK']);
        $this->assertSame('tanah_kosong', $unit->fresh()->occupancy_status);
    }

    public function test_unit_linked_to_resident_but_not_yet_active_is_booked(): void
    {
        $resident = Resident::factory()->create();
        $unit = Unit::factory()->create(['property_type_id' => 'B', 'resident_id' => null, 'status_id' => 'TA', 'occupancy_id' => '2']);
        $this->assertSame('ready_stock', $unit->occupancy_status);

        $unit->update(['resident_id' => $resident->id, 'occupancy_id' => Unit::OCCUPANCY_BOOKED_ID]);

        $this->assertSame('booked', $unit->fresh()->occupancy_status);
        $this->assertSame('Booked', $unit->fresh()->occupancy_status_label);

        // Serah terima kunci (status AK) mengubah Booked menjadi Occupied.
        $unit->update(['status_id' => 'AK']);
        $this->assertSame('occupied', $unit->fresh()->occupancy_status);
    }

    public function test_kavling_linked_to_resident_but_not_yet_active_is_booked(): void
    {
        $unit = Unit::factory()->create(['property_type_id' => 'K', 'status_id' => 'RK', 'occupancy_id' => Unit::OCCUPANCY_BOOKED_ID]);

        $this->assertSame('booked', $unit->occupancy_status);
    }

    public function test_booked_marker_without_a_resident_is_not_booked(): void
    {
        $unit = Unit::factory()->create(['property_type_id' => 'B', 'resident_id' => null, 'status_id' => 'TA', 'occupancy_id' => Unit::OCCUPANCY_BOOKED_ID]);

        $this->assertSame('ready_stock', $unit->occupancy_status);
    }

    #[DataProvider('occupancyStatusProvider')]
    public function test_scope_occupancy_status_matches_the_computed_accessor_for_every_unit(string $status): void
    {
        Unit::factory()->create(['property_type_id' => 'B', 'resident_id' => null, 'status_id' => 'TA']);
        Unit::factory()->create(['property_type_id' => 'K', 'resident_id' => null, 'status_id' => 'RK']);
        Unit::factory()->create(['property_type_id' => 'B', 'status_id' => 'AK']);
        Unit::factory()->create(['property_type_id' => 'P', 'status_id' => 'AK']);
        Unit::factory()->inactive()->create(['property_type_id' => 'R']);
        Unit::factory()->create(['property_type_id' => 'B', 'status_id' => 'TA', 'occupancy_id' => Unit::OCCUPANCY_BOOKED_ID]);

        $expectedIds = Unit::all()->filter(fn (Unit $unit) => $unit->occupancy_status === $status)->pluck('id')->sort()->values();
        $actualIds = Unit::occupancyStatus($status)->pluck('id')->sort()->values();

        $this->assertEquals($expectedIds, $actualIds);
        $this->assertNotEmpty($actualIds, "expected at least one unit for status {$status} in this fixture");
    }

    public static function occupancyStatusProvider(): array
    {
        return [
            'ready_stock' => [Unit::OCCUPANCY_STATUS_READY_STOCK],
            'tanah_kosong' => [Unit::OCCUPANCY_STATUS_TANAH_KOSONG],
            'booked' => [Unit::OCCUPANCY_STATUS_BOOKED],
            'occupied' => [Unit::OCCUPANCY_STATUS_OCCUPIED],
        ];
    }

    public function test_total_of_the_three_statuses_equals_total_units_in_scope(): void
    {
        Unit::factory()->count(3)->create(['property_type_id' => 'B', 'resident_id' => null, 'status_id' => 'TA']);
        Unit::factory()->count(2)->create(['property_type_id' => 'K', 'resident_id' => null, 'status_id' => 'RK']);
        Unit::factory()->count(4)->create(['property_type_id' => 'B', 'status_id' => 'AK']);

        $total = Unit::count();
        $sum = Unit::occupancyStatus('ready_stock')->count()
            + Unit::occupancyStatus('tanah_kosong')->count()
            + Unit::occupancyStatus('occupied')->count();

        $this->assertSame($total, $sum);
    }
}
