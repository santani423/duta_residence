<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResidentBalanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function makeUnitWithCustomer(): array
    {
        $resident = Resident::factory()->create();
        $unit = Unit::factory()->create(['resident_id' => $resident->id, 'is_penalty_eligible' => false]);
        $user = User::factory()->create(['resident_id' => $resident->id, 'unit_id' => $unit->id]);
        $user->assignRole('customer');

        return [$unit, $user];
    }

    private function makeBilling(Unit $unit, float $amount): Billing
    {
        $finance = User::where('username', 'finance')->firstOrFail();
        $period = now()->startOfMonth();

        return Billing::query()->create([
            'unit_id' => $unit->id,
            'year' => $period->year,
            'month' => $period->month,
            'amount' => $amount,
            'status_id' => Billing::STATUS_UNPAID,
            'is_penalty_eligible' => false,
            'billing_type' => 'regular',
            'approved_by' => $finance->id,
            'approved_at' => now()->subDay(),
            'created_by' => $finance->id,
        ]);
    }

    public function test_dashboard_exposes_unit_balance(): void
    {
        [$unit, $user] = $this->makeUnitWithCustomer();
        $unit->forceFill(['balance' => 250000])->save();

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/resident/dashboard')
            ->assertOk()
            ->assertJsonPath('data.balance.available', 250000);
    }

    public function test_resident_can_view_balance_and_ledger(): void
    {
        [$unit, $user] = $this->makeUnitWithCustomer();
        $loket = User::where('username', 'loket')->firstOrFail();
        $this->makeBilling($unit, 900000);
        app(PaymentService::class)->process($unit, null, ['payment_method_id' => 'C', 'amount' => 1000000], $loket->id);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/resident/balance')
            ->assertOk()
            ->assertJsonPath('data.available_balance', 100000);

        $this->getJson('/api/v1/resident/balance/ledger')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_resident_can_preview_and_use_balance_to_pay_outstanding_bill(): void
    {
        [$unit, $user] = $this->makeUnitWithCustomer();
        $unit->forceFill(['balance' => 500000])->save();
        $billing = $this->makeBilling($unit, 300000);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/resident/balance/preview')
            ->assertOk()
            ->assertJsonPath('data.balance_available', 500000)
            ->assertJsonPath('data.balance_used', 300000)
            ->assertJsonPath('data.remaining_outstanding', 0);

        $this->postJson('/api/v1/resident/balance/use')
            ->assertCreated()
            ->assertJsonPath('data.balance_used', '300000.00');

        $this->assertSame(200000.0, (float) $unit->fresh()->balance);
        $this->assertSame(Billing::STATUS_PAID, $billing->fresh()->status_id);
    }

    public function test_using_balance_fails_when_unit_has_no_balance(): void
    {
        [$unit, $user] = $this->makeUnitWithCustomer();
        $this->makeBilling($unit, 300000);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/resident/balance/use')
            ->assertStatus(422)
            ->assertJsonValidationErrors('balance');
    }

    public function test_resident_cannot_use_balance_belonging_to_another_unit(): void
    {
        [$unitA, $userA] = $this->makeUnitWithCustomer();
        [$unitB] = $this->makeUnitWithCustomer();
        $unitA->forceFill(['balance' => 500000])->save();
        $unitB->forceFill(['balance' => 500000])->save();
        $otherBilling = $this->makeBilling($unitB, 300000);

        Sanctum::actingAs($userA);

        $this->postJson('/api/v1/resident/balance/use', ['billing_ids' => [$otherBilling->id]])
            ->assertStatus(422);

        $this->assertSame(500000.0, (float) $unitB->fresh()->balance);
    }
}
