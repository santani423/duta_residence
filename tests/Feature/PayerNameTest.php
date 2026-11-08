<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\PaymentTransaction;
use App\Models\Receipt;
use App\Models\Unit;
use App\Models\User;
use App\Services\PaymentPrintService;
use App\Support\PayerName;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Nama pembayar: terpisah dari pemilik unit, opsional, dan tercatat di transaksi, kuitansi, dan ekspor. */
class PayerNameTest extends TestCase
{
    use RefreshDatabase;

    private const UNIT = '07012';

    private function approvedBilling(int $offset = 0): Billing
    {
        return Billing::where('unit_id', self::UNIT)->where('status_id', '01')->whereNotNull('approved_at')
            ->orderBy('year')->orderBy('month')->skip($offset)->firstOrFail();
    }

    private function ownerName(): string
    {
        return Unit::with('resident')->findOrFail(self::UNIT)->resident?->name ?? '-';
    }

    /** Login lewat jalur API: token menjadi sesi aktif akun (sama seperti test lain di proyek ini). */
    private function actAs(string $username): void
    {
        $this->withToken($this->apiTokenFor(User::where('username', $username)->firstOrFail()));
    }

    private function loketPayload(Billing $billing, array $extra = []): array
    {
        return array_merge([
            'unit_id' => self::UNIT,
            'billing_ids' => [$billing->id],
            'payment_method_id' => 'C',
            'loket_code' => 'L01',
        ], $extra);
    }

    public function test_payer_name_is_normalized_and_blank_becomes_null(): void
    {
        $this->assertNull(PayerName::normalize(null));
        $this->assertNull(PayerName::normalize('   '));
        $this->assertNull(PayerName::normalize(['bukan', 'string']));
        $this->assertSame('Andi Pratama', PayerName::normalize("  Andi \t  Pratama "));
    }

    public function test_loket_payment_stores_payer_separately_from_owner(): void
    {
        $this->seed();
        $this->actAs('loket');
        $owner = $this->ownerName();

        $response = $this->postJson('/api/v1/payments/process', $this->loketPayload($this->approvedBilling(), [
            'payer_name' => '  Andi   Pratama ',
        ]))->assertCreated()->assertJsonPath('data.payer_name', 'Andi Pratama');

        $receipt = Receipt::findOrFail($response->json('data.number'));
        $this->assertSame('Andi Pratama', $receipt->payer_name);
        // Snapshot kuitansi tetap nama pemilik unit; nama pembayar tidak menggantikannya.
        $this->assertSame($owner, $receipt->resident_name);

        $transaction = PaymentTransaction::findOrFail($receipt->payment_transaction_id);
        $this->assertSame('Andi Pratama', $transaction->payer_name);
        $this->assertSame($owner, $this->ownerName());
    }

    public function test_missing_or_blank_payer_is_stored_as_null_not_owner_name(): void
    {
        $this->seed();
        $this->actAs('loket');

        $response = $this->postJson('/api/v1/payments/process', $this->loketPayload($this->approvedBilling(), [
            'payer_name' => '   ',
        ]))->assertCreated()->assertJsonPath('data.payer_name', null);

        $receipt = Receipt::findOrFail($response->json('data.number'));
        $this->assertNull($receipt->payer_name);
        $this->assertNull(PaymentTransaction::findOrFail($receipt->payment_transaction_id)->payer_name);
    }

    public function test_payer_name_is_limited_to_150_characters(): void
    {
        $this->seed();
        $this->actAs('loket');
        $billing = $this->approvedBilling();

        $transactionsBefore = PaymentTransaction::count();

        $this->postJson('/api/v1/payments/process', $this->loketPayload($billing, [
            'payer_name' => str_repeat('A', 151),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['payer_name']);

        // Validasi gagal: tagihan belum berubah dan tidak ada transaksi baru yang dibuat.
        $this->assertSame('01', $billing->fresh()->status_id);
        $this->assertSame($transactionsBefore, PaymentTransaction::count());

        $this->postJson('/api/v1/payments/process', $this->loketPayload($billing, [
            'payer_name' => str_repeat('A', 150),
        ]))->assertCreated();

        $this->assertDatabaseHas('payment_transactions', ['payer_name' => str_repeat('A', 150)]);
    }

    public function test_resident_payment_keeps_payer_name_when_proof_is_uploaded_without_it(): void
    {
        $this->seed();
        Storage::fake('public');
        $this->actAs('customer');
        $billing = $this->approvedBilling();

        $paymentId = $this->postJson("/api/v1/resident/invoices/{$billing->id}/payments", [
            'provider' => 'manual',
            'payer_name' => 'Andi Pratama',
        ])->assertCreated()->assertJsonPath('data.payer_name', 'Andi Pratama')->json('data.id');

        $this->postJson("/api/v1/resident/payments/{$paymentId}/manual-proof", [
            'sender_name' => 'Andi Pratama',
            'sender_bank' => 'BCA',
            'sender_account_number' => '1234567890',
            'amount' => (string) PaymentTransaction::findOrFail($paymentId)->total,
            'manual_transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertOk()->assertJsonPath('data.payer_name', 'Andi Pratama');

        $this->assertSame('Andi Pratama', PaymentTransaction::findOrFail($paymentId)->payer_name);
        $this->getJson("/api/v1/resident/payments/{$paymentId}")
            ->assertOk()
            ->assertJsonPath('data.payer_name', 'Andi Pratama');
    }

    public function test_proof_upload_can_add_payer_name_when_none_was_given_at_creation(): void
    {
        $this->seed();
        Storage::fake('public');
        $this->actAs('customer');
        $billing = $this->approvedBilling();

        $paymentId = $this->postJson("/api/v1/resident/invoices/{$billing->id}/payments", [
            'provider' => 'manual',
        ])->assertCreated()->assertJsonPath('data.payer_name', null)->json('data.id');

        $this->postJson("/api/v1/resident/payments/{$paymentId}/manual-proof", [
            'sender_name' => 'Budi Santoso',
            'sender_bank' => 'BCA',
            'sender_account_number' => '1234567890',
            'amount' => (string) PaymentTransaction::findOrFail($paymentId)->total,
            'manual_transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
            'payer_name' => 'Dewi Lestari',
        ])->assertOk();

        $this->assertSame('Dewi Lestari', PaymentTransaction::findOrFail($paymentId)->payer_name);
        // Nama pengirim (rekening) tetap tersimpan terpisah dari nama pembayar.
        $this->assertSame('Budi Santoso', PaymentTransaction::findOrFail($paymentId)->manual_sender_name);
    }

    public function test_payer_name_is_searchable_and_exported_with_receipts(): void
    {
        $this->seed();
        $this->actAs('loket');
        [$first, $second] = [$this->approvedBilling(0), $this->approvedBilling(1)];

        $withPayer = $this->postJson('/api/v1/payments/process', $this->loketPayload($first, [
            'payer_name' => 'Andi Pratama',
        ]))->assertCreated()->json('data.number');

        $withoutPayer = $this->postJson('/api/v1/payments/process', $this->loketPayload($second))
            ->assertCreated()->json('data.number');

        $this->getJson('/api/v1/payments/receipts?search=Andi+Pratama')
            ->assertOk()
            ->assertJsonFragment(['number' => $withPayer])
            ->assertJsonMissing(['number' => $withoutPayer]);

        $this->actAs('finance');
        $csv = $this->get('/api/v1/documents/payment-receipts-excel')->assertOk()->streamedContent();

        $this->assertStringContainsString('Nama Pembayar', $csv);
        $rows = collect(explode("\n", $csv));
        $this->assertTrue($rows->contains(fn (string $row) => str_starts_with($row, $withPayer) && str_contains($row, 'Andi Pratama')));
        $this->assertTrue($rows->contains(fn (string $row) => str_starts_with($row, $withoutPayer) && str_ends_with(trim($row), ',-')));
    }

    public function test_kwitansi_shows_payer_while_owner_stays_on_record(): void
    {
        $this->seed();
        $this->actAs('loket');
        $owner = $this->ownerName();
        [$first, $second] = [$this->approvedBilling(0), $this->approvedBilling(1)];

        $withPayer = $this->postJson('/api/v1/payments/process', $this->loketPayload($first, [
            'payer_name' => 'Andi Pratama',
        ]))->assertCreated()->json('data.number');

        $withoutPayer = $this->postJson('/api/v1/payments/process', $this->loketPayload($second))
            ->assertCreated()->json('data.number');

        $printer = app(PaymentPrintService::class);

        $dataWithPayer = $printer->forReceipt(Receipt::findOrFail($withPayer));
        $this->assertSame('Andi Pratama', $dataWithPayer['payer_name']);
        $this->assertSame($owner, $dataWithPayer['resident_name']);

        $htmlWithPayer = view('pdf.kwitansi', ['data' => $dataWithPayer, 'thermal' => false])->render();
        $this->assertStringContainsString('Andi Pratama', $htmlWithPayer);
        $this->assertStringContainsString('Atas nama pemilik unit', $htmlWithPayer);

        $dataWithoutPayer = $printer->forReceipt(Receipt::findOrFail($withoutPayer));
        $this->assertNull($dataWithoutPayer['payer_name']);

        $htmlWithoutPayer = view('pdf.kwitansi', ['data' => $dataWithoutPayer, 'thermal' => false])->render();
        $this->assertStringNotContainsString('Atas nama pemilik unit', $htmlWithoutPayer);
        $this->assertStringContainsString($owner, $htmlWithoutPayer);
    }
}
