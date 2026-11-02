<?php

namespace Tests\Feature;

use App\Models\Cluster;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\EstateSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

/**
 * Migrasi clusters.id dari kode angka ke kode huruf pada database yang sudah berisi data.
 * Data awal dibentuk dengan kode huruf lalu dibalik lewat down(), supaya kedua arah teruji.
 */
class ClusterLetterCodeMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const REFERENCING_TABLES = [
        'units', 'cluster_rate_schedules', 'penalty_rules', 'cluster_maps', 'collector_assignments', 'supervisor_assignments',
    ];

    private Migration $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstateSeeder::class);
        $this->migration = require database_path('migrations/2026_11_02_000001_use_letter_codes_for_cluster_ids.php');
    }

    private function referenceRows(): void
    {
        $user = User::factory()->create();
        $now = now();

        Unit::factory()->create(['id' => '07901', 'cluster_id' => 'AL', 'block' => 'AL1', 'block_number' => 1, 'lot_number' => '901']);
        // Unit terhapus juga harus ikut pindah supaya foreign key-nya tetap valid.
        Unit::factory()->create(['id' => '07902', 'cluster_id' => 'AL', 'block' => 'AL1', 'block_number' => 1, 'lot_number' => '902'])->delete();
        DB::table('penalty_rules')->insert(['name' => 'Denda', 'cluster_id' => 'AL', 'minimum_overdue_month' => 1, 'effective_start_date' => '2026-01-01']);
        DB::table('cluster_maps')->insert(['cluster_id' => 'AL', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('collector_assignments')->insert(['collector_id' => $user->id, 'scope_type' => 'cluster', 'cluster_id' => 'AL']);
        DB::table('supervisor_assignments')->insert(['supervisor_id' => $user->id, 'cluster_id' => 'AL']);
        DB::table('broadcasts')->insert([
            ['type' => 'resident', 'message' => 'A', 'sender_id' => $user->id, 'target_criteria' => json_encode(['cluster_id' => 'E', 'unit_ids' => null])],
            ['type' => 'resident', 'message' => 'B', 'sender_id' => $user->id, 'target_criteria' => json_encode(['cluster_id' => null, 'unit_ids' => ['07901']])],
        ]);
    }

    private function assertReferencesPointTo(string $clusterId): void
    {
        foreach (self::REFERENCING_TABLES as $table) {
            $this->assertGreaterThan(0, DB::table($table)->where('cluster_id', $clusterId)->count(), "{$table} -> {$clusterId}");
        }
    }

    public function test_cluster_ids_and_every_reference_move_between_numeric_and_letter_codes(): void
    {
        $this->referenceRows();
        $clusters = Cluster::query()->orderBy('name')->get(['name', 'code_prefix', 'area_type', 'allowed_blocks', 'monthly_rate'])->toArray();
        $schedules = DB::table('cluster_rate_schedules')->count();

        $this->migration->down();

        $this->assertSame(
            ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12', '13', '14'],
            Cluster::query()->orderBy('id')->pluck('id')->all()
        );
        $this->assertReferencesPointTo('07');
        $this->assertStringContainsString('blok 07.', Cluster::query()->find('07')->description);
        $this->assertSame('10', json_decode(DB::table('broadcasts')->where('message', 'A')->value('target_criteria'), true)['cluster_id']);
        $this->assertSame(0, DB::table('units')->whereIn('cluster_id', ['AL', 'E', 'D'])->count());

        $this->migration->up();

        $this->assertSame(
            ['AL', 'AM', 'AR', 'AX', 'B', 'C', 'D', 'DD', 'E', 'G', 'J', 'JA', 'RD', 'RE'],
            Cluster::query()->orderBy('id')->pluck('id')->all()
        );
        $this->assertReferencesPointTo('AL');
        $this->assertStringContainsString('blok AL.', Cluster::query()->find('AL')->description);
        $this->assertSame('E', json_decode(DB::table('broadcasts')->where('message', 'A')->value('target_criteria'), true)['cluster_id']);
        $this->assertSame(['07901'], json_decode(DB::table('broadcasts')->where('message', 'B')->value('target_criteria'), true)['unit_ids']);

        // Kode unit, nomor blok, dan isi cluster tidak berubah; tidak ada baris yang hilang.
        $this->assertSame(1, Unit::withTrashed()->whereKey('07901')->where('block_number', 1)->count());
        $this->assertSame(1, Unit::onlyTrashed()->whereKey('07902')->where('cluster_id', 'AL')->count());
        $this->assertSame($schedules, DB::table('cluster_rate_schedules')->count());
        $this->assertSame($clusters, Cluster::query()->orderBy('name')->get(['name', 'code_prefix', 'area_type', 'allowed_blocks', 'monthly_rate'])->toArray());
    }

    public function test_clusters_created_manually_are_left_untouched(): void
    {
        $this->migration->down();
        Cluster::query()->create(['id' => '15', 'name' => 'Cluster Manual', 'monthly_rate' => 100000]);

        $this->migration->up();

        $this->assertSame('Cluster Manual', Cluster::query()->find('15')->name);
        $this->assertNull(Cluster::query()->find('01'));
    }

    public function test_code_already_taken_stops_the_migration_without_partial_changes(): void
    {
        $this->migration->down();
        Cluster::query()->create(['id' => 'AX', 'name' => 'Cluster Manual', 'monthly_rate' => 100000]);

        try {
            $this->migration->up();
            $this->fail('Migrasi seharusnya berhenti karena kode AX sudah dipakai.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('AX', $e->getMessage());
        }

        $this->assertNotNull(Cluster::query()->find('01'));
        $this->assertNull(Cluster::query()->find('D'));
        $this->assertSame('Cluster Manual', Cluster::query()->find('AX')->name);
    }
}
