<?php

namespace Tests\Feature;

use App\Models\PaymentTransaction;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Rentang tanggal pada dashboard Penghuni dan Supervisor. Angka `*_today` / `successful_total`
 * tetap dikembalikan tanpa perubahan karena aplikasi Flutter masih membacanya.
 */
class DashboardRangeScopesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_resident_period_counts_payments_paid_inside_the_range_only(): void
    {
        [$unit, $user] = $this->residentWithUnit();
        $this->paidPayment($unit, 100000, '2026-10-01 09:00:00');
        $this->paidPayment($unit, 200000, '2026-10-31 23:30:00');
        $this->paidPayment($unit, 400000, '2026-11-02 08:00:00');

        Sanctum::actingAs($user);

        $data = $this->getJson('/api/v1/resident/dashboard?start=2026-10-01&end=2026-10-31')->assertOk()->json('data');

        $this->assertEquals(300000, (float) $data['payment_summary']['successful_period']);
        // Total sepanjang waktu tidak ikut rentang (dibaca aplikasi Flutter).
        $this->assertEquals(700000, (float) $data['payment_summary']['successful_total']);
        $this->assertSame(['start' => '2026-10-01', 'end' => '2026-10-31'], $data['period']);
    }

    public function test_resident_dashboard_rejects_an_end_date_before_the_start(): void
    {
        [, $user] = $this->residentWithUnit();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/resident/dashboard?start=2026-10-31&end=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end']);
    }

    public function test_supervisor_dashboard_echoes_the_period_and_keeps_today_keys(): void
    {
        $supervisor = User::factory()->create(['is_active' => true]);
        $supervisor->assignRole('root');
        Sanctum::actingAs($supervisor);

        $this->getJson('/api/v1/supervisor/dashboard?start=2026-10-01&end=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.period.start', '2026-10-01')
            ->assertJsonPath('data.period.end', '2026-10-31')
            ->assertJsonStructure(['data' => [
                'payments' => ['successful_today', 'on_site_today', 'successful_period', 'on_site_period'],
                'visits_today',
                'visits_period',
            ]]);

        $this->getJson('/api/v1/supervisor/dashboard?start=2026-10-31&end=2026-10-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end']);
    }

    /** @return array{0: Unit, 1: User} */
    private function residentWithUnit(): array
    {
        $resident = Resident::factory()->create();
        $unit = Unit::factory()->create(['resident_id' => $resident->id, 'is_penalty_eligible' => false]);
        $user = User::factory()->create(['resident_id' => $resident->id, 'unit_id' => $unit->id]);
        $user->assignRole('customer');

        return [$unit, $user];
    }

    private function paidPayment(Unit $unit, int $amount, string $paidAt): void
    {
        PaymentTransaction::factory()->manual()->paid()->create([
            'unit_id' => $unit->id,
            'subtotal' => $amount,
            'total' => $amount,
            'paid_at' => $paidAt,
        ]);
    }
}
