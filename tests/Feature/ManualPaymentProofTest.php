<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Billing;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualPaymentProofTest extends TestCase
{
    use RefreshDatabase;

    private function createManualPayment(): PaymentTransaction
    {
        $billing = Billing::where('unit_id', 'GA012')->where('status_id', '01')->whereNotNull('approved_at')->firstOrFail();

        Sanctum::actingAs(User::where('username', 'customer')->first());

        $paymentId = $this->postJson("/api/v1/resident/invoices/{$billing->id}/payments", [
            'provider' => 'manual',
        ])->assertCreated()->json('data.id');

        return PaymentTransaction::findOrFail($paymentId);
    }

    public function test_resident_can_upload_manual_proof_and_it_awaits_verification(): void
    {
        $this->seed();
        Storage::fake('public');
        $payment = $this->createManualPayment();

        Sanctum::actingAs(User::where('username', 'customer')->first());

        $response = $this->postJson("/api/v1/resident/payments/{$payment->id}/manual-proof", [
            'sender_name' => 'Budi Santoso',
            'sender_bank' => 'BCA',
            'sender_account_number' => '1234567890',
            'amount' => (string) $payment->total,
            'manual_transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
            'manual_notes' => 'Transfer via mobile banking',
        ])->assertOk();

        $response->assertJsonPath('data.status', 'waiting_verification');
        $response->assertJsonPath('data.manual_sender_name', 'Budi Santoso');
        $response->assertJsonPath('data.manual_sender_bank', 'BCA');
        $this->assertEquals((float) $payment->total, $response->json('data.manual_amount'));

        $this->assertDatabaseHas('payment_transactions', [
            'id' => $payment->id,
            'status' => 'waiting_verification',
            'manual_sender_name' => 'Budi Santoso',
            'manual_sender_bank' => 'BCA',
            'manual_sender_account_number' => '1234567890',
        ]);

        $payment->refresh();
        $this->assertNotNull($payment->manual_amount);
        $this->assertNotNull($payment->manual_proof_uploaded_at);
        $this->assertSame('Transfer via mobile banking', $payment->manual_notes);
        Storage::disk('public')->assertExists($payment->manual_proof_path);

        $this->assertDatabaseHas('notification_queues', [
            'unit_id' => $payment->unit_id,
            'user_id' => User::where('username', 'customer')->first()->id,
            'type' => 'payment_proof_uploaded',
        ]);

        // Staf dengan permission payments.verify harus mendapat notifikasi juga,
        // bukan hanya penghuni - kalau tidak, admin tidak tahu ada bukti baru masuk.
        $finance = User::where('username', 'finance')->first();
        $this->assertDatabaseHas('notification_queues', [
            'unit_id' => $payment->unit_id,
            'user_id' => $finance->id,
            'type' => 'payment_proof_uploaded',
        ]);

        // Dan notifikasi itu harus benar-benar muncul lewat endpoint notifikasi staff
        // (NotificationController::index), bukan cuma tersimpan di database.
        Sanctum::actingAs($finance);
        $this->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment(['type' => 'payment_proof_uploaded']);
    }

    public function test_admin_verifying_manual_proof_settles_billing_and_notifies_resident(): void
    {
        $this->seed();
        Storage::fake('public');
        $payment = $this->createManualPayment();
        $billingId = $payment->billings()->first()->id;

        Sanctum::actingAs(User::where('username', 'customer')->first());
        $this->postJson("/api/v1/resident/payments/{$payment->id}/manual-proof", [
            'sender_name' => 'Budi Santoso',
            'sender_bank' => 'BCA',
            'amount' => (string) $payment->total,
            'manual_transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertOk();

        Sanctum::actingAs(User::where('username', 'finance')->first());
        $this->postJson("/api/v1/payments/{$payment->id}/verify", [
            'verification_notes' => 'Sesuai mutasi rekening',
        ])->assertOk()->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('payment_transactions', ['id' => $payment->id, 'status' => 'paid']);
        $this->assertDatabaseHas('billings', ['id' => $billingId, 'status_id' => Billing::STATUS_PAID]);

        $this->assertDatabaseHas('notification_queues', [
            'unit_id' => $payment->unit_id,
            'type' => 'payment_verified',
        ]);

        $this->assertTrue(
            AuditLog::where('activity', 'payment_manual_verified')->where('entity_id', $payment->id)->exists()
        );
    }

    public function test_admin_rejecting_manual_proof_requires_reason_and_is_audited(): void
    {
        $this->seed();
        Storage::fake('public');
        $payment = $this->createManualPayment();

        Sanctum::actingAs(User::where('username', 'customer')->first());
        $this->postJson("/api/v1/resident/payments/{$payment->id}/manual-proof", [
            'sender_name' => 'Budi Santoso',
            'sender_bank' => 'BCA',
            'amount' => (string) $payment->total,
            'manual_transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertOk();

        Sanctum::actingAs(User::where('username', 'finance')->first());

        // Alasan penolakan wajib diisi.
        $this->postJson("/api/v1/payments/{$payment->id}/reject", [])
            ->assertStatus(422);

        $this->postJson("/api/v1/payments/{$payment->id}/reject", [
            'verification_notes' => 'Nominal transfer tidak sesuai dengan total tagihan.',
        ])->assertOk()->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('payment_transactions', [
            'id' => $payment->id,
            'status' => 'rejected',
            'verification_notes' => 'Nominal transfer tidak sesuai dengan total tagihan.',
        ]);

        $this->assertDatabaseHas('notification_queues', [
            'unit_id' => $payment->unit_id,
            'type' => 'payment_rejected',
        ]);

        $this->assertTrue(
            AuditLog::where('activity', 'payment_manual_rejected')->where('entity_id', $payment->id)->exists()
        );

        // Penghuni dapat mengunggah ulang bukti pembayaran setelah ditolak.
        Sanctum::actingAs(User::where('username', 'customer')->first());
        $this->postJson("/api/v1/resident/payments/{$payment->id}/manual-proof", [
            'sender_name' => 'Budi Santoso',
            'sender_bank' => 'Mandiri',
            'amount' => (string) $payment->total,
            'manual_transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti-ulang.jpg'),
        ])->assertOk()->assertJsonPath('data.status', 'waiting_verification');

        $this->assertDatabaseHas('payment_transactions', [
            'id' => $payment->id,
            'status' => 'waiting_verification',
            'manual_sender_bank' => 'Mandiri',
            'verification_notes' => null,
        ]);
    }
}
