<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\DiscountRule;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\DiscountService;
use Database\Seeders\EstateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DiscountFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstateSeeder::class);
    }

    public function test_prepare_special_billing_applies_the_units_discount_rule_automatically(): void
    {
        $rule = DiscountRule::query()->create(['name' => 'Diskon Karyawan', 'type' => DiscountRule::TYPE_FIXED, 'value' => 25000, 'is_active' => true]);
        $resident = Resident::factory()->create();
        $unit = Unit::factory()->create(['resident_id' => $resident->id, 'is_discount_eligible' => true, 'discount_rule_id' => $rule->id]);
        $user = User::factory()->create();

        $billing = app(BillingService::class)->prepareSpecial($unit, 2026, 7, 500000, $user->id);

        $this->assertSame(25000.0, (float) $billing->discount);
        $this->assertSame($rule->id, $billing->discount_rule_id);
    }

    public function test_prepare_special_billing_applies_percentage_discount_rule(): void
    {
        $rule = DiscountRule::query()->create(['name' => 'Diskon Persen', 'type' => DiscountRule::TYPE_PERCENTAGE, 'value' => 10, 'is_active' => true]);
        $resident = Resident::factory()->create();
        $unit = Unit::factory()->create(['resident_id' => $resident->id, 'is_discount_eligible' => true, 'discount_rule_id' => $rule->id]);
        $user = User::factory()->create();

        $billing = app(BillingService::class)->prepareSpecial($unit, 2026, 7, 400000, $user->id);

        $this->assertSame(40000.0, (float) $billing->discount);
    }

    public function test_ineligible_unit_gets_no_automatic_discount_even_with_a_rule_assigned(): void
    {
        $rule = DiscountRule::query()->create(['name' => 'Diskon Tidak Terpakai', 'type' => DiscountRule::TYPE_FIXED, 'value' => 25000, 'is_active' => true]);
        $resident = Resident::factory()->create();
        $unit = Unit::factory()->create(['resident_id' => $resident->id, 'is_discount_eligible' => false, 'discount_rule_id' => $rule->id]);
        $user = User::factory()->create();

        $billing = app(BillingService::class)->prepareSpecial($unit, 2026, 7, 400000, $user->id);

        $this->assertSame(0.0, (float) $billing->discount);
    }

    public function test_admin_can_manually_set_discount_on_an_outstanding_billing_and_it_is_audited(): void
    {
        $resident = Resident::factory()->create();
        $unit = Unit::factory()->create(['resident_id' => $resident->id]);
        $user = User::factory()->create();
        $billing = Billing::factory()->create([
            'unit_id' => $unit->id,
            'amount' => 500000,
            'discount' => 0,
            'status_id' => Billing::STATUS_UNPAID,
            'approved_at' => now(),
        ]);

        app(DiscountService::class)->applyManualDiscount($billing, 30000, 'Kompensasi keluhan layanan.', $user->id);

        $billing->refresh();
        $this->assertSame(30000.0, (float) $billing->discount);
        $this->assertNull($billing->discount_rule_id);
        $this->assertSame($user->id, $billing->discount_set_by);
        $this->assertSame('Kompensasi keluhan layanan.', $billing->discount_reason);
        $this->assertDatabaseHas('audit_logs', ['module' => 'billings', 'action' => 'DISCOUNT_SET']);
    }

    public function test_manual_discount_rejected_when_it_would_exceed_remaining_principal(): void
    {
        $resident = Resident::factory()->create();
        $unit = Unit::factory()->create(['resident_id' => $resident->id]);
        $billing = Billing::factory()->create([
            'unit_id' => $unit->id,
            'amount' => 500000,
            'principal_paid' => 480000,
            'status_id' => Billing::STATUS_PARTIAL,
            'approved_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        app(DiscountService::class)->applyManualDiscount($billing, 30000, 'Alasan uji', 1);
    }
}
