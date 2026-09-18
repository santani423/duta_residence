<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\NotificationQueue;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\PaymentStaffNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentStaffNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_gateway_paid_alerts_loket_once_and_not_collectors(): void
    {
        $this->seed();
        $customer = User::where('username', 'customer')->first();
        $billing = Billing::where('unit_id', 'GA012')->where('status_id', '01')->whereNotNull('approved_at')->firstOrFail();

        Sanctum::actingAs($customer);
        $paymentId = $this->postJson("/api/v1/resident/invoices/{$billing->id}/payments", ['provider' => 'manual'])->assertCreated()->json('data.id');
        $payment = PaymentTransaction::findOrFail($paymentId);

        $notifier = app(PaymentStaffNotifier::class);
        $notifier->gatewayPaid($payment);
        $notifier->gatewayPaid($payment); // webhook retried

        $loket = User::role('loket')->firstOrFail();
        $rows = NotificationQueue::where('user_id', $loket->id)->where('type', 'payment_received')->get();
        $this->assertCount(1, $rows);
        $this->assertSame((string) $payment->id, $rows->first()->reference_id);

        $collector = User::role('collector')->firstOrFail();
        $this->assertFalse(NotificationQueue::where('user_id', $collector->id)->where('type', 'payment_received')->exists());

        Sanctum::actingAs($loket);
        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonFragment(['type' => 'payment_received']);
    }
}
