<?php

namespace Tests\Feature;

use App\Models\PaymentGatewaySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentGatewayVaCodeTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'active_gateway' => 'manual',
            'is_active' => true,
            'mode' => 'sandbox',
            'currency' => 'IDR',
            'admin_fee' => 0,
            'payment_timeout_minutes' => 1440,
            'proof_max_size_kb' => 5120,
        ], $overrides);
    }

    private function actingAsSuperAdmin(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        Sanctum::actingAs($user);
    }

    public function test_super_admin_can_save_va_bank_and_company_code(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/settings/payment-gateway', $this->payload([
            'va_bank_code' => '8277',
            'va_company_code' => '1234',
        ]))
            ->assertOk()
            ->assertJsonPath('data.va_bank_code', '8277')
            ->assertJsonPath('data.va_company_code', '1234')
            ->assertJsonPath('data.public_config.va_code.prefix', '82771234');

        $this->assertSame('82771234', PaymentGatewaySetting::current()->vaPrefix());
    }

    public function test_va_codes_must_be_numeric(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/v1/admin/settings/payment-gateway', $this->payload([
            'va_bank_code' => 'BCA',
            'va_company_code' => '12 34',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['va_bank_code', 'va_company_code']);
    }

    private function unitPayload(array $overrides = []): array
    {
        return array_merge([
            'cluster_id' => 'GA',
            'block' => 'Z',
            'lot_number' => '98',
            'property_type_id' => 'B',
            'occupancy_id' => '1',
            'status_id' => 'TA',
        ], $overrides);
    }

    public function test_unit_va_number_is_prefix_plus_nine_digit_suffix(): void
    {
        $this->actingAsSuperAdmin();
        PaymentGatewaySetting::current()->update(['va_bank_code' => '8277', 'va_company_code' => '1234']);

        $this->getJson('/api/v1/units/va-format')
            ->assertOk()
            ->assertJsonPath('data.prefix', '82771234')
            ->assertJsonPath('data.suffix_length', 9);

        $unitId = $this->postJson('/api/v1/units', $this->unitPayload(['va_suffix' => '000000001']))
            ->assertCreated()
            ->assertJsonPath('data.va_number', '82771234000000001')
            ->json('data.id');

        // Nomor yang sama tidak boleh dipakai unit lain.
        $this->postJson('/api/v1/units', $this->unitPayload(['lot_number' => '97', 'va_suffix' => '000000001']))
            ->assertUnprocessable()->assertJsonValidationErrors('va_suffix');

        // Update tanpa va_suffix tidak mengubah nomor VA.
        $this->putJson("/api/v1/units/{$unitId}", $this->unitPayload())
            ->assertOk()->assertJsonPath('data.va_number', '82771234000000001');
    }

    public function test_unit_va_suffix_must_be_nine_digits_and_prefix_must_be_configured(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson('/api/v1/units', $this->unitPayload(['va_suffix' => '000000001']))
            ->assertUnprocessable()->assertJsonValidationErrors('va_suffix');

        PaymentGatewaySetting::current()->update(['va_bank_code' => '8277', 'va_company_code' => '1234']);

        $this->postJson('/api/v1/units', $this->unitPayload(['va_suffix' => '123']))
            ->assertUnprocessable()->assertJsonValidationErrors('va_suffix');
    }
}
