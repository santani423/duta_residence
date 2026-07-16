<?php

namespace Tests\Unit;

use App\Models\Billing;
use App\Models\DiscountRule;
use App\Models\Unit;
use App\Services\DiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DiscountServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_discount_rule_computes_flat_amount(): void
    {
        $rule = new DiscountRule(['type' => DiscountRule::TYPE_FIXED, 'value' => 15000]);

        $this->assertSame(15000.0, $rule->computeDiscount(500000));
    }

    public function test_percentage_discount_rule_computes_proportional_amount(): void
    {
        $rule = new DiscountRule(['type' => DiscountRule::TYPE_PERCENTAGE, 'value' => 10]);

        $this->assertSame(35000.0, $rule->computeDiscount(350000));
    }

    public function test_discount_never_exceeds_the_principal_amount(): void
    {
        $fixed = new DiscountRule(['type' => DiscountRule::TYPE_FIXED, 'value' => 999999]);
        $percentage = new DiscountRule(['type' => DiscountRule::TYPE_PERCENTAGE, 'value' => 150]);

        $this->assertSame(100000.0, $fixed->computeDiscount(100000));
        $this->assertSame(100000.0, $percentage->computeDiscount(100000));
    }

    public function test_unit_without_eligibility_or_rule_gets_zero_discount(): void
    {
        $service = app(DiscountService::class);

        $ineligibleUnit = new Unit(['is_discount_eligible' => false, 'discount_rule_id' => 1]);
        $result = $service->calculateForNewBilling($ineligibleUnit, 500000);
        $this->assertSame(0.0, $result['amount']);
        $this->assertNull($result['rule']);

        $noRuleUnit = new Unit(['is_discount_eligible' => true, 'discount_rule_id' => null]);
        $result = $service->calculateForNewBilling($noRuleUnit, 500000);
        $this->assertSame(0.0, $result['amount']);
        $this->assertNull($result['rule']);
    }

    public function test_eligible_unit_with_active_rule_gets_computed_discount(): void
    {
        $rule = DiscountRule::query()->create(['name' => 'Diskon Test', 'type' => DiscountRule::TYPE_FIXED, 'value' => 20000, 'is_active' => true]);
        $unit = new Unit(['is_discount_eligible' => true, 'discount_rule_id' => $rule->id]);
        $unit->setRelation('discountRule', $rule);

        $service = app(DiscountService::class);
        $result = $service->calculateForNewBilling($unit, 500000);

        $this->assertSame(20000.0, $result['amount']);
        $this->assertSame($rule->id, $result['rule']->id);
    }

    public function test_inactive_rule_yields_zero_discount(): void
    {
        $rule = DiscountRule::query()->create(['name' => 'Diskon Nonaktif', 'type' => DiscountRule::TYPE_FIXED, 'value' => 20000, 'is_active' => false]);
        $unit = new Unit(['is_discount_eligible' => true, 'discount_rule_id' => $rule->id]);
        $unit->setRelation('discountRule', $rule);

        $service = app(DiscountService::class);
        $result = $service->calculateForNewBilling($unit, 500000);

        $this->assertSame(0.0, $result['amount']);
        $this->assertNull($result['rule']);
    }

    public function test_manual_discount_cannot_be_applied_to_a_paid_billing(): void
    {
        $billing = new Billing(['status_id' => Billing::STATUS_PAID, 'amount' => 500000, 'principal_paid' => 500000]);

        $this->expectException(ValidationException::class);
        app(DiscountService::class)->applyManualDiscount($billing, 10000, 'Alasan uji', 1);
    }

    public function test_manual_discount_cannot_exceed_remaining_principal(): void
    {
        $billing = new Billing(['status_id' => Billing::STATUS_PARTIAL, 'amount' => 500000, 'principal_paid' => 480000]);

        $this->expectException(ValidationException::class);
        app(DiscountService::class)->applyManualDiscount($billing, 30000, 'Alasan uji', 1);
    }
}
