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

    private function setDiscount(Billing $billing, float $value, ?string $type = null)
    {
        return $this->putJson("/api/v1/billings/{$billing->id}/discount", array_filter(
            ['discount' => $value, 'discount_type' => $type, 'reason' => 'Uji batas diskon'],
            fn ($v) => $v !== null,
        ));
    }

    private function configure(array $attributes): void
    {
        DiscountSetting::current()->update($attributes);
    }

    public function test_defaults_are_30_percent_and_percentage_type(): void
    {
        $this->assertSame(30.0, DiscountSetting::maximumAdminDiscount());
        $this->assertSame('percentage', DiscountSetting::adminDiscountType());
    }

    public function test_admin_can_apply_exactly_the_maximum(): void
    {
        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $billing = $this->billing();

        $this->setDiscount($billing, 30)->assertOk();

        // Yang tersimpan tetap nominal: 30% x Rp500.000; harga akhir = 500.000 - 150.000.
        $billing->refresh();
        $this->assertSame(150000.0, (float) $billing->discount);
        $this->assertSame(350000.0, (float) $billing->amount - (float) $billing->discount);
    }

    public function test_admin_cannot_exceed_the_maximum_even_by_a_little(): void
    {
        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $billing = $this->billing();

        $this->setDiscount($billing, 30.01)
            ->assertStatus(422)
            ->assertJsonValidationErrors('discount');
        $this->setDiscount($billing, 100)->assertStatus(422);
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
        $this->setDiscount($billing, 40)->assertOk();
        $this->assertSame(200000.0, (float) $billing->refresh()->discount);
        $this->setDiscount($billing, 40.01)->assertStatus(422)->assertJsonValidationErrors('discount');
    }

    public function test_lowering_the_limit_applies_immediately(): void
    {
        $billing = $this->billing();
        DiscountSetting::current()->update(['maximum_admin_discount' => 10]);

        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $this->setDiscount($billing, 10)->assertOk();
        $this->setDiscount($billing, 10.01)->assertStatus(422);
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
            ->assertJsonPath('data.maximum_percent', 30)
            ->assertJsonPath('data.discount_type', 'percentage');

        Sanctum::actingAs($this->userWithRole('super_admin'));
        $this->getJson('/api/v1/discount-limit')->assertOk()
            ->assertJsonPath('data.is_limited', false)
            ->assertJsonPath('data.maximum_percent', null)
            ->assertJsonPath('data.discount_type', null);
    }

    public function test_admin_cannot_bypass_the_limit_through_a_billing_adjustment_request(): void
    {
        $billing = $this->billing();
        Sanctum::actingAs($this->userWithRole('admin_estate'));

        $payload = fn (float $value) => [
            'billing_id' => $billing->id, 'adjustment_type' => 'discount', 'new_value' => $value, 'reason' => 'Uji',
        ];
        // Mode nominal: tipe yang salah ditolak, nilai nominal dibatasi setara persen maksimum.
        $this->configure(['admin_discount_type' => 'nominal']);
        $this->postJson('/api/v1/approval-requests/billing-adjustments', $payload(150001))->assertStatus(422);
        $this->postJson('/api/v1/approval-requests/billing-adjustments', $payload(30) + ['discount_type' => 'percentage'])->assertStatus(422);
        $this->configure(['admin_discount_type' => 'percentage']);

        $this->postJson('/api/v1/approval-requests/billing-adjustments', $payload(30.01))->assertStatus(422);
        $this->postJson('/api/v1/approval-requests/billing-adjustments', $payload(30))->assertCreated();

        // Disimpan sebagai nominal, apa pun tipe input pengajunya.
        $this->assertDatabaseHas('billing_adjustments', ['billing_id' => $billing->id, 'new_value' => 150000]);
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

    public function test_super_admin_can_change_the_discount_type_and_it_is_validated(): void
    {
        Sanctum::actingAs($this->userWithRole('super_admin'));

        $this->putJson('/api/v1/admin/discount-settings', ['admin_discount_type' => 'nominal'])
            ->assertOk()
            ->assertJsonPath('data.admin_discount_type', 'nominal')
            ->assertJsonPath('data.maximum_admin_discount', '30.00');

        $this->putJson('/api/v1/admin/discount-settings', ['admin_discount_type' => 'bogus'])
            ->assertStatus(422)->assertJsonValidationErrors('admin_discount_type');
        $this->putJson('/api/v1/admin/discount-settings', [])->assertStatus(422);

        $this->putJson('/api/v1/admin/discount-settings', ['admin_discount_type' => 'percentage', 'maximum_admin_discount' => 25])->assertOk();
        $this->assertSame('percentage', DiscountSetting::adminDiscountType());
        $this->assertSame(25.0, DiscountSetting::maximumAdminDiscount());
    }

    public function test_only_super_admin_can_change_the_discount_type(): void
    {
        foreach (['admin_estate', 'finance', 'back_office', 'loket'] as $role) {
            Sanctum::actingAs($this->userWithRole($role));
            $this->putJson('/api/v1/admin/discount-settings', ['admin_discount_type' => 'nominal'])->assertForbidden();
        }

        $this->assertSame('percentage', DiscountSetting::adminDiscountType());
    }

    public function test_nominal_mode_validates_the_rupiah_amount_against_the_percentage_maximum(): void
    {
        $this->configure(['admin_discount_type' => 'nominal']);
        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $billing = $this->billing(1000000);

        // Harga asli Rp1.000.000 x 30% = maksimal Rp300.000.
        $this->setDiscount($billing, 300000)->assertOk();
        $billing->refresh();
        $this->assertSame(300000.0, (float) $billing->discount);
        $this->assertSame(700000.0, (float) $billing->amount - (float) $billing->discount);

        $this->setDiscount($billing, 300000.01)->assertStatus(422)->assertJsonValidationErrors('discount');
        $this->setDiscount($billing, 1000000)->assertStatus(422);
        $this->assertSame(300000.0, (float) $billing->refresh()->discount);

        // Maksimum dinaikkan -> batas nominal ikut naik.
        $this->configure(['maximum_admin_discount' => 40]);
        $this->setDiscount($billing, 400000)->assertOk();
        $this->setDiscount($billing, 400000.01)->assertStatus(422);
    }

    public function test_admin_cannot_use_a_different_type_than_configured(): void
    {
        $billing = $this->billing();
        Sanctum::actingAs($this->userWithRole('admin_estate'));

        // Mode persentase: nominal eksplisit ditolak, dan angka polos dibaca sebagai persen.
        $this->setDiscount($billing, 100000, 'nominal')->assertStatus(422)->assertJsonValidationErrors('discount_type');
        $this->setDiscount($billing, 150000)->assertStatus(422); // = 150000%

        $this->configure(['admin_discount_type' => 'nominal']);
        $this->setDiscount($billing, 10, 'percentage')->assertStatus(422)->assertJsonValidationErrors('discount_type');
        $this->setDiscount($billing, 10, 'bogus')->assertStatus(422);
        $this->assertSame(0.0, (float) $billing->refresh()->discount);
    }

    public function test_percentage_input_must_be_a_valid_percentage(): void
    {
        $billing = $this->billing();
        $this->configure(['maximum_admin_discount' => 100]);
        Sanctum::actingAs($this->userWithRole('admin_estate'));

        $this->setDiscount($billing, 100.01)->assertStatus(422);
        $this->setDiscount($billing, -1)->assertStatus(422);
        $this->setDiscount($billing, 100)->assertOk();

        // Harga akhir tidak pernah negatif: diskon 100% = pokok penuh, tepat Rp0.
        $billing->refresh();
        $this->assertSame(0.0, (float) $billing->amount - (float) $billing->discount);
    }

    public function test_percentage_discount_cannot_push_price_below_what_is_already_paid(): void
    {
        $this->configure(['maximum_admin_discount' => 100]);
        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $billing = $this->billing();
        $billing->update(['principal_paid' => 400000, 'status_id' => Billing::STATUS_PARTIAL]);

        // 30% = Rp150.000 > sisa pokok Rp100.000 -> tetap ditolak oleh aturan sisa pokok.
        $this->setDiscount($billing, 30)->assertStatus(422);
        $this->setDiscount($billing, 20)->assertOk();
    }

    public function test_users_without_a_limit_default_to_nominal_and_may_choose_percentage(): void
    {
        $billing = $this->billing();
        Sanctum::actingAs($this->userWithRole('super_admin'));

        $this->setDiscount($billing, 60000)->assertOk();
        $this->assertSame(60000.0, (float) $billing->refresh()->discount);

        $this->setDiscount($billing, 50, 'percentage')->assertOk();
        $this->assertSame(250000.0, (float) $billing->refresh()->discount);
    }

    public function test_changing_the_setting_does_not_alter_stored_discounts(): void
    {
        $billing = $this->billing();
        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $this->setDiscount($billing, 20)->assertOk();
        $this->assertSame(100000.0, (float) $billing->refresh()->discount);

        Sanctum::actingAs($this->userWithRole('super_admin'));
        $this->putJson('/api/v1/admin/discount-settings', ['admin_discount_type' => 'nominal', 'maximum_admin_discount' => 10])->assertOk();

        $this->assertSame(100000.0, (float) $billing->refresh()->discount);
    }

    public function test_admin_discount_rule_type_must_match_the_configured_type(): void
    {
        Sanctum::actingAs($this->userWithRole('admin_estate'));
        $fixed = ['name' => 'Rp', 'type' => 'fixed', 'value' => 10000, 'is_active' => true];
        $percent = ['name' => 'Persen', 'type' => 'percentage', 'value' => 10, 'is_active' => true];

        $this->postJson('/api/v1/discount-rules', $fixed)->assertStatus(422)->assertJsonValidationErrors('type');
        $this->postJson('/api/v1/discount-rules', $percent)->assertCreated();

        $this->configure(['admin_discount_type' => 'nominal']);
        $this->postJson('/api/v1/discount-rules', $percent)->assertStatus(422)->assertJsonValidationErrors('type');
        $this->postJson('/api/v1/discount-rules', $fixed)->assertCreated();

        Sanctum::actingAs($this->userWithRole('super_admin'));
        $this->postJson('/api/v1/discount-rules', $percent)->assertCreated();
    }
}
