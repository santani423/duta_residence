<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_loket_can_preview_and_process_approved_billing_payment(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'loket')->first());

        $billing = Billing::where('customer_id', 'GA012')->where('status_id', '01')->whereNotNull('approved_at')->firstOrFail();

        $this->postJson('/api/v1/payments/preview', [
            'customer_id' => 'GA012',
            'billing_ids' => [$billing->id],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/payments/process', [
            'customer_id' => 'GA012',
            'billing_ids' => [$billing->id],
            'payment_method_id' => 'C',
            'loket_code' => 'L01',
            'cashier_name' => 'Loket Kasir',
        ])->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('billings', [
            'id' => $billing->id,
            'status_id' => '02',
        ]);
    }
}
