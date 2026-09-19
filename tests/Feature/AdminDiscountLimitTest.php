<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\DiscountRule;
use App\Models\DiscountSetting;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDiscountLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function billing(float $amount = 500000): Billing
    {
        $unit = Unit::factory()->create(['resident_id' => Resident::factory()->create()->id]);

        return Billing::factory()->create([
            'unit_id' => $unit->id,
            'amount' => $amount,
            'discount' => 0,
            'principal_paid' => 0,
            'status_id' => Billing::STATUS_UNPAID,
            'approved_at' => now(),
        ]);
    }

    private function setDiscount(Billing $billing, float $amount)
    {
        return $this->putJson("/api/v1/billings/{$billing->id}/discount", ['discount' => $amount, 'reason' => 'Uji batas diskon']);
    }

    public function test_default_maximum_is_30_percent(): void
    {
        $this->assertSame(30.0, DiscountSetting::maximumAdminDiscount());
    }

    public function test_admin_can_apply_exactly_the_maximum(): void
    {
        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $billing = $this->billing();

        $this->setDiscount($billing, 150000)->assertOk();
        $this->assertSame(150000.0, (float) $billing->refresh()->discount);
    }

    public function test_admin_cannot_exceed_the_maximum_even_by_a_little(): void
    {
        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $billing = $this->billing();

        // 30.01% of 500.000 = 150.050
        $this->setDiscount($billing, 150050)
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount');
        $this->setDiscount($billing, 500000)->assertStatus(422);
        $this->assertSame(0.0, (float) $billing->refresh()->discount);
    }

    public function test_super_admin_and_root_are_not_limited(): void
    {
        $billing = $this->billing();

        Sanctum::actingAs($this->userWithRole('super_admin'));
        $this->setDiscount($billing, 400000)->assertOk();

        Sanctum::actingAs($this->userWithRole('root'));
        $this->setDiscount($billing, 500000)->assertOk();
    }

    public function test_super_admin_can_raise_the_limit_and_admin_follows_it(): void
    {
        $billing = $this->billing();

        Sanctum::actingAs($this->userWithRole('super_admin'));
        $this->putJson('/api/v1/admin/discount-settings', ['maximum_admin_discount' => 40])
            ->assertOk()
            ->assertJsonPath('data.maximum_admin_discount', '40.00');
        $this->assertDatabaseHas('audit_logs', ['module' => 'discount-settings', 'action' => 'UPDATE']);

        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $this->setDiscount($billing, 200000)->assertOk();
        $this->setDiscount($billing, 200050)->assertStatus(422)->assertJsonValidationErrors('discount');
    }

    public function test_lowering_the_limit_applies_immediately(): void
    {
        $billing = $this->billing();
        DiscountSetting::current()->update(['maximum_admin_discount' => 10]);

        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $this->setDiscount($billing, 50000)->assertOk();
        $this->setDiscount($billing, 60000)->assertStatus(422);
    }

    public function test_setting_is_restricted_to_super_admin(): void
    {
        foreach (['admin_estate', 'finance', 'back_office', 'loket'] as $role) {
            Sanctum::actingAs($this->userWithRole($role));
            $this->getJson('/api/v1/admin/discount-settings')->assertForbidden();
            $this->putJson('/api/v1/admin/discount-settings', ['maximum_admin_discount' => 100])->assertForbidden();
        }

        $this->assertSame(30.0, DiscountSetting::maximumAdminDiscount());
    }

    public function test_setting_rejects_values_outside_0_to_100(): void
    {
        Sanctum::actingAs($this->userWithRole('super_admin'));

        foreach ([-1, 100.01, 'abc', null] as $value) {
            $this->putJson('/api/v1/admin/discount-settings', ['maximum_admin_discount' => $value])
                ->assertStatus(422)
                ->assertJsonValidationErrors('maximum_admin_discount');
        }

        $this->putJson('/api/v1/admin/discount-settings', ['maximum_admin_discount' => 0])->assertOk();
        $this->putJson('/api/v1/admin/discount-settings', ['maximum_admin_discount' => 100])->assertOk();
    }

    public function test_limit_endpoint_reports_the_cap_for_admin_only(): void
    {
        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $this->getJson('/api/v1/discount-limit')->assertOk()
            ->assertJsonPath('data.is_limited', true)
            ->assertJsonPath('data.maximum_percent', 30);

        Sanctum::actingAs($this->userWithRole('super_admin'));
        $this->getJson('/api/v1/discount-limit')->assertOk()
            ->assertJsonPath('data.is_limited', false)
            ->assertJsonPath('data.maximum_percent', null);
    }

    public function test_admin_cannot_bypass_the_limit_through_a_billing_adjustment_request(): void
    {
        $billing = $this->billing();
        Sanctum::actingAs($this->userWithRole('admin_estate'));

        $payload = fn (float $value) => [
            'billing_id' => $billing->id, 'adjustment_type' => 'discount', 'new_value' => $value, 'reason' => 'Uji',
        ];

        $this->postJson('/api/v1/approval-requests/billing-adjustments', $payload(150050))->assertStatus(422);
        $this->postJson('/api/v1/approval-requests/billing-adjustments', $payload(150000))->assertCreated();
    }

    public function test_admin_percentage_discount_rules_are_capped_but_super_admin_is_not(): void
    {
        $rule = ['name' => 'Diskon Uji', 'type' => 'percentage', 'is_active' => true];

        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $this->postJson('/api/v1/discount-rules', $rule + ['value' => 30])->assertCreated();
        $this->postJson('/api/v1/discount-rules', $rule + ['value' => 30.01])->assertStatus(422)->assertJsonValidationErrors('value');
        $this->postJson('/api/v1/discount-rules', $rule + ['value' => 101])->assertStatus(422);

        Sanctum::actingAs($this->userWithRole('super_admin'));
        $this->postJson('/api/v1/discount-rules', $rule + ['value' => 50])->assertCreated();
    }

    public function test_admin_cannot_attach_an_over_limit_percentage_rule_to_a_unit(): void
    {
        $rule = DiscountRule::query()->create(['name' => 'Besar', 'type' => DiscountRule::TYPE_PERCENTAGE, 'value' => 50, 'is_active' => true]);
        $unit = Unit::factory()->create();

        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $this->putJson("/api/v1/units/{$unit->id}", [
            'cluster_id' => $unit->cluster_id, 'block' => $unit->block, 'lot_number' => $unit->lot_number,
            'property_type_id' => $unit->property_type_id, 'status_id' => $unit->status_id,
            'is_discount_eligible' => true, 'discount_rule_id' => $rule->id,
        ])->assertStatus(422)->assertJsonValidationErrors('discount_rule_id');
    }
}
