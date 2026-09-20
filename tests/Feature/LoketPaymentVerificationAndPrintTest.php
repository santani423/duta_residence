<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\User;
use App\Services\PaymentPrintService;
use App\Support\Terbilang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LoketPaymentVerificationAndPrintTest extends TestCase
{
    use RefreshDatabase;

    private function waitingVerificationPayment(): PaymentTransaction
    {
        Storage::fake('public');
        $billing = Billing::where('unit_id', 'GA012')->where('status_id', '01')->whereNotNull('approved_at')->firstOrFail();

        Sanctum::actingAs(User::where('username', 'customer')->first());
        $payment = PaymentTransaction::findOrFail(
            $this->postJson("/api/v1/resident/invoices/{$billing->id}/payments", ['provider' => 'manual'])->assertCreated()->json('data.id')
        );
        $this->postJson("/api/v1/resident/payments/{$payment->id}/manual-proof", [
            'sender_name' => 'Budi', 'sender_bank' => 'BCA', 'sender_account_number' => '123',
            'amount' => (string) $payment->total, 'manual_transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertOk();

        return $payment->refresh();
    }

    public function test_loket_can_verify_an_incoming_transfer_and_settle_the_billing(): void
    {
        $this->seed();
        $payment = $this->waitingVerificationPayment();
        $loket = User::where('username', 'loket')->first();

        Sanctum::actingAs($loket);
        $this->postJson("/api/v1/payments/{$payment->id}/verify", ['verification_notes' => 'Sesuai mutasi'])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $payment->refresh();
        $this->assertSame($loket->id, $payment->verified_by);
        $this->assertSame(Billing::STATUS_PAID, $payment->billings()->first()->status_id);
    }

    public function test_loket_can_reject_an_incoming_transfer_with_a_reason(): void
    {
        $this->seed();
        $payment = $this->waitingVerificationPayment();

        Sanctum::actingAs(User::where('username', 'loket')->first());
        $this->postJson("/api/v1/payments/{$payment->id}/reject", [])->assertStatus(422);
        $this->postJson("/api/v1/payments/{$payment->id}/reject", ['verification_notes' => 'Nominal tidak sesuai'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_cs_still_cannot_verify_payments(): void
    {
        $this->seed();
        $payment = $this->waitingVerificationPayment();

        Sanctum::actingAs(User::role('cs')->firstOrFail());
        $this->postJson("/api/v1/payments/{$payment->id}/verify")->assertForbidden();
    }

    public static function printableDocuments(): array
    {
        return [
            'kuitansi' => ['spt/{number}'],
            'kuitansi thermal' => ['spt/{number}?format=thermal'],
            'kuitansi dari transaksi' => ['payment-transactions/{transaction}/receipt'],
            'bukti transaksi' => ['payment-transactions/{transaction}'],
            'daftar transaksi' => ['payment-transactions'],
            'riwayat kuitansi' => ['payment-receipts?unit_id=GA012'],
        ];
    }

    // Satu PDF per tes: DomPDF memakai banyak memori, jadi menumpuknya dalam satu tes melewati memory_limit 128M.
    #[DataProvider('printableDocuments')]
    public function test_loket_can_print_payment_documents(string $path): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'loket')->first());

        $number = $this->postJson('/api/v1/payments/process', [
            'unit_id' => 'GA012',
            'payment_method_id' => 'C',
            'loket_code' => 'L01',
            'cashier_name' => 'Loket Kasir',
        ])->assertCreated()->json('data.number');
        $transaction = PaymentTransaction::where('unit_id', 'GA012')->where('payment_provider', 'loket')->latest('id')->firstOrFail();

        $url = '/api/v1/documents/'.str_replace(['{number}', '{transaction}'], [$number, $transaction->id], $path);
        $response = $this->get($url)->assertOk();

        $this->assertStringStartsWith('%PDF', $response->getContent(), $url);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'), $url);
    }

    public function test_receipt_list_pdf_refuses_result_sets_too_big_for_dompdf_instead_of_crashing(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'loket')->first());

        // Data seeder berisi ratusan kuitansi; tanpa filter dulu berakhir HTTP 500 (memori habis).
        $this->assertGreaterThan(500, Receipt::count());
        $this->getJson('/api/v1/documents/payment-receipts')
            ->assertStatus(422)
            ->assertJsonPath('message', fn (string $message) => str_contains($message, 'Persempit filter'));

        // Ekspor CSV tidak dibatasi.
        $this->get('/api/v1/documents/payment-receipts-excel')->assertOk();
    }

    public function test_transaction_pdf_prints_for_a_transfer_still_waiting_verification(): void
    {
        $this->seed();
        $payment = $this->waitingVerificationPayment();

        Sanctum::actingAs(User::where('username', 'loket')->first());
        $this->get("/api/v1/documents/payment-transactions/{$payment->id}")->assertOk();
        $this->get('/api/v1/documents/payment-transactions/999999')->assertNotFound();
    }

    public function test_transaction_receipt_is_only_available_once_the_payment_is_paid(): void
    {
        $this->seed();
        $payment = $this->waitingVerificationPayment();

        Sanctum::actingAs(User::where('username', 'loket')->first());
        $this->getJson("/api/v1/documents/payment-transactions/{$payment->id}/receipt")->assertStatus(422);

        $this->postJson("/api/v1/payments/{$payment->id}/verify")->assertOk();
        $response = $this->get("/api/v1/documents/payment-transactions/{$payment->id}/receipt?format=thermal")->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_kuitansi_shows_amounts_from_the_stored_receipt_and_the_terbilang(): void
    {
        $this->seed();
        Sanctum::actingAs(User::where('username', 'loket')->first());

        $number = $this->postJson('/api/v1/payments/process', [
            'unit_id' => 'GA012', 'payment_method_id' => 'C', 'loket_code' => 'L01', 'cashier_name' => 'Loket Kasir',
        ])->assertCreated()->json('data.number');
        $receipt = Receipt::findOrFail($number);

        $html = view('pdf.kwitansi', [
            'data' => app(PaymentPrintService::class)->forReceipt($receipt),
            'thermal' => false,
        ])->render();

        $this->assertStringContainsString($number, $html);
        $this->assertStringContainsString('Rp '.number_format((float) $receipt->grand_total, 0, ',', '.'), $html);
        $this->assertStringContainsString(Terbilang::rupiah($receipt->grand_total), $html);
        $this->assertStringContainsString('Sisa tagihan unit', $html);
    }

    public function test_customer_cannot_print_staff_documents(): void
    {
        $this->seed();
        $payment = $this->waitingVerificationPayment();

        Sanctum::actingAs(User::where('username', 'customer')->first());
        $this->get("/api/v1/documents/payment-transactions/{$payment->id}")->assertForbidden();
    }
}
