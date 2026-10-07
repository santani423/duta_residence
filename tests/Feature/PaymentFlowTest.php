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

        $billing = Billing::where('unit_id', 'GA012')->where('status_id', '01')->whereNotNull('approved_at')->firstOrFail();

        $this->postJson('/api/v1/payments/preview', [
            'unit_id' => 'GA012',
            'billing_ids' => [$billing->id],
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson('/api/v1/payments/process', [
            'unit_id' => 'GA012',
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

    public function test_loket_search_can_filter_paid_billings_while_totals_stay_outstanding(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'loket')->first());

        $billing = Billing::where('unit_id', 'GA012')->where('status_id', '01')->whereNotNull('approved_at')->firstOrFail();
        $before = $this->getJson('/api/v1/payments/search?unit_id=GA012')->assertOk()->json('data');

        $this->postJson('/api/v1/payments/process', [
            'unit_id' => 'GA012',
            'billing_ids' => [$billing->id],
            'payment_method_id' => 'C',
            'loket_code' => 'L01',
            'cashier_name' => 'Loket Kasir',
        ])->assertCreated();

        $unpaid = $this->getJson('/api/v1/payments/search?unit_id=GA012&status=unpaid')->assertOk()->json('data');
        $paid = $this->getJson('/api/v1/payments/search?unit_id=GA012&status=paid')->assertOk()->json('data');

        $this->assertNotContains($billing->id, collect($unpaid['billings'])->pluck('id'));
        $this->assertContains($billing->id, collect($paid['billings'])->pluck('id'));
        $this->assertSame(['02'], collect($paid['billings'])->pluck('status_id')->unique()->values()->all());
        $this->assertEquals($unpaid['total_outstanding'], $paid['total_outstanding']);
        $this->assertLessThan($before['total_outstanding'], $paid['total_outstanding']);

        $this->getJson('/api/v1/payments/search?unit_id=GA012&status=partial')->assertUnprocessable();
    }
}
