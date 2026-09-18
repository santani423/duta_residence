<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\NotificationQueue;
use App\Models\PaymentTransaction;
use App\Models\SupervisorNotification;
use App\Models\User;
use App\Services\NotificationPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Detail / read-unread / routing behaviour shared by web and mobile. Both clients hit
 * these same endpoints, so read-state sync between them is a property of the database
 * row - asserted here by reading state back through an independent request.
 */
class NotificationDetailTest extends TestCase
{
    use RefreshDatabase;

    private function staffNotification(User $user, array $attrs = []): NotificationQueue
    {
        return NotificationQueue::factory()->unread()->create([
            'user_id' => $user->id,
            'type' => 'payment_verified',
            'recipient' => (string) $user->id,
            ...$attrs,
        ]);
    }

    public function test_staff_can_open_detail_with_title_category_sender_and_reference(): void
    {
        $this->seed();
        $finance = User::where('username', 'finance')->first();
        $root = User::where('username', 'root')->first();
        $billing = Billing::query()->firstOrFail();

        $n = $this->staffNotification($finance, [
            'sender_id' => $root->id,
            ...NotificationPresenter::referenceFor($billing),
        ]);

        Sanctum::actingAs($finance);
        $this->getJson("/api/v1/notifications/{$n->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $n->id)
            ->assertJsonPath('data.title', 'Pembayaran Diverifikasi')
            ->assertJsonPath('data.category', 'payment')
            ->assertJsonPath('data.sender.id', $root->id)
            ->assertJsonPath('data.reference.resource', 'invoice')
            ->assertJsonPath('data.reference.id', (string) $billing->id)
            ->assertJsonPath('data.reference.available', true)
            ->assertJsonPath('data.is_read', false);
    }

    public function test_opening_detail_does_not_mutate_but_read_and_unread_toggle_and_sync(): void
    {
        $this->seed();
        $finance = User::where('username', 'finance')->first();
        $n = $this->staffNotification($finance);

        Sanctum::actingAs($finance);
        $this->getJson("/api/v1/notifications/{$n->id}")->assertOk();
        $this->assertSame('unread', $n->fresh()->read_status);

        $this->postJson("/api/v1/notifications/{$n->id}/read")->assertOk()->assertJsonPath('data.is_read', true);
        $this->assertNotNull($n->fresh()->read_at);
        // "Other device": an independent request sees the same state.
        $this->getJson("/api/v1/notifications/{$n->id}")->assertJsonPath('data.read_status', 'read');
        $this->getJson('/api/v1/notifications/unread-count')->assertOk();

        $this->postJson("/api/v1/notifications/{$n->id}/unread")->assertOk()->assertJsonPath('data.is_read', false);
        $this->assertNull($n->fresh()->read_at);
        $this->getJson("/api/v1/notifications/{$n->id}")->assertJsonPath('data.read_status', 'unread');
    }

    public function test_index_reports_unread_count_and_is_scoped_to_the_user(): void
    {
        $this->seed();
        $finance = User::where('username', 'finance')->first();
        $finance2 = User::where('username', 'finance2')->first();

        NotificationQueue::query()->delete();
        $this->staffNotification($finance);
        $this->staffNotification($finance, ['read_status' => 'read']);
        $this->staffNotification($finance2);

        Sanctum::actingAs($finance);
        $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.unread_count', 1);
        $this->getJson('/api/v1/notifications/unread-count')->assertJsonPath('data.unread_count', 1);
    }

    public function test_staff_cannot_open_or_toggle_another_users_or_a_residents_notification(): void
    {
        $this->seed();
        $finance = User::where('username', 'finance')->first();
        $finance2 = User::where('username', 'finance2')->first();

        $others = $this->staffNotification($finance2);
        $residentAddressed = NotificationQueue::factory()->unread()->create(['unit_id' => 'GA012', 'user_id' => null, 'recipient' => '0812000']);

        Sanctum::actingAs($finance);
        foreach ([$others, $residentAddressed] as $n) {
            $this->getJson("/api/v1/notifications/{$n->id}")->assertNotFound();
            $this->postJson("/api/v1/notifications/{$n->id}/read")->assertNotFound();
            $this->postJson("/api/v1/notifications/{$n->id}/unread")->assertNotFound();
        }
        $this->getJson('/api/v1/notifications/999999')->assertNotFound();
    }

    public function test_customer_accounts_cannot_use_the_staff_notification_endpoints(): void
    {
        $this->seed();
        $customer = User::where('username', 'customer')->first();
        $broadcast = NotificationQueue::factory()->unread()->create(['user_id' => null, 'recipient' => NotificationQueue::STAFF_RECIPIENT]);

        Sanctum::actingAs($customer);
        $this->getJson('/api/v1/notifications')->assertForbidden();
        $this->getJson('/api/v1/notifications/unread-count')->assertForbidden();
        $this->getJson("/api/v1/notifications/{$broadcast->id}")->assertForbidden();
        $this->postJson("/api/v1/notifications/{$broadcast->id}/read")->assertForbidden();
        $this->postJson('/api/v1/notifications/read-all')->assertForbidden();

        $this->assertSame('unread', $broadcast->fresh()->read_status);
    }

    public function test_reference_reports_unavailable_when_the_related_record_is_gone(): void
    {
        $this->seed();
        $finance = User::where('username', 'finance')->first();
        $billing = Billing::query()->firstOrFail();
        $n = $this->staffNotification($finance, NotificationPresenter::referenceFor($billing));

        $billing->delete(); // Billing uses SoftDeletes.

        Sanctum::actingAs($finance);
        $this->getJson("/api/v1/notifications/{$n->id}")
            ->assertOk()
            ->assertJsonPath('data.reference.resource', 'invoice')
            ->assertJsonPath('data.reference.available', false)
            ->assertJsonPath('data.reference.message', 'Tagihan terkait sudah tidak tersedia atau telah dihapus.');

        $hardGone = $this->staffNotification($finance, ['reference_type' => PaymentTransaction::class, 'reference_id' => '99999999']);
        $this->getJson("/api/v1/notifications/{$hardGone->id}")->assertJsonPath('data.reference.available', false);
    }

    public function test_notification_without_reference_or_with_unknown_type_still_has_a_detail(): void
    {
        $this->seed();
        $finance = User::where('username', 'finance')->first();
        $n = $this->staffNotification($finance, ['type' => 'some_new_type', 'reference_type' => 'App\\Models\\Nope', 'reference_id' => '1']);

        Sanctum::actingAs($finance);
        $this->getJson("/api/v1/notifications/{$n->id}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Some New Type')
            ->assertJsonPath('data.category', 'system')
            ->assertJsonPath('data.reference', null);
    }

    public function test_resident_inbox_excludes_staff_and_other_users_rows_even_on_the_same_unit(): void
    {
        $this->seed();
        $customer = User::where('username', 'customer')->first();
        $unitId = $customer->unit_id;
        $otherUser = User::where('username', 'finance')->first();

        $mine = NotificationQueue::factory()->unread()->create(['unit_id' => $unitId, 'user_id' => null, 'recipient' => '0812']);
        $staffBroadcast = NotificationQueue::factory()->unread()->create(['unit_id' => $unitId, 'user_id' => null, 'recipient' => 'staff', 'type' => 'emergency_alert']);
        $staffPersonal = NotificationQueue::factory()->unread()->create(['unit_id' => $unitId, 'user_id' => $otherUser->id, 'recipient' => (string) $otherUser->id]);
        $otherUnit = NotificationQueue::factory()->unread()->create(['unit_id' => 'AL001', 'user_id' => null, 'recipient' => '0813']);

        Sanctum::actingAs($customer);
        $ids = collect($this->getJson('/api/v1/resident/notifications?per_page=1000')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        foreach ([$staffBroadcast, $staffPersonal, $otherUnit] as $n) {
            $this->assertFalse($ids->contains($n->id));
            $this->getJson("/api/v1/resident/notifications/{$n->id}")->assertNotFound();
            $this->postJson("/api/v1/resident/notifications/{$n->id}/read")->assertNotFound();
            $this->postJson("/api/v1/resident/notifications/{$n->id}/unread")->assertNotFound();
        }

        $this->getJson("/api/v1/resident/notifications/{$mine->id}")->assertOk()->assertJsonPath('data.id', $mine->id);
        $this->postJson("/api/v1/resident/notifications/{$mine->id}/read")->assertOk();
        $this->postJson("/api/v1/resident/notifications/{$mine->id}/unread")->assertOk();
        $this->assertSame('unread', $mine->fresh()->read_status);

        $this->postJson('/api/v1/resident/notifications/read-all')->assertOk();
        $this->assertSame('read', $mine->fresh()->read_status);
        $this->assertSame('unread', $otherUnit->fresh()->read_status);
        $this->assertSame('unread', $staffBroadcast->fresh()->read_status);
    }

    public function test_uploading_manual_proof_notifies_verifiers_with_a_link_back_to_the_payment(): void
    {
        $this->seed();
        Storage::fake('public');
        $customer = User::where('username', 'customer')->first();
        $billing = Billing::where('unit_id', 'GA012')->where('status_id', '01')->whereNotNull('approved_at')->firstOrFail();

        Sanctum::actingAs($customer);
        $paymentId = $this->postJson("/api/v1/resident/invoices/{$billing->id}/payments", ['provider' => 'manual'])->assertCreated()->json('data.id');
        $payment = PaymentTransaction::findOrFail($paymentId);
        $this->postJson("/api/v1/resident/payments/{$payment->id}/manual-proof", [
            'sender_name' => 'Budi', 'sender_bank' => 'BCA', 'sender_account_number' => '123',
            'amount' => (string) $payment->total, 'manual_transfer_date' => now()->toDateString(),
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertOk();

        $verifier = User::permission('payments.verify')->firstOrFail();
        $row = NotificationQueue::where('user_id', $verifier->id)->where('type', 'payment_proof_uploaded')->latest('id')->firstOrFail();
        $this->assertSame(PaymentTransaction::class, $row->reference_type);
        $this->assertSame((string) $payment->id, $row->reference_id);
        $this->assertSame($customer->id, $row->sender_id);

        // Loket cannot verify, but must still see that a proof is waiting.
        $loket = User::role('loket')->firstOrFail();
        $this->assertFalse($loket->can('payments.verify'));
        $this->assertTrue(
            NotificationQueue::where('user_id', $loket->id)->where('type', 'payment_proof_uploaded')
                ->where('reference_id', (string) $payment->id)->exists()
        );
        // Collectors process payments in the field but are not part of the cashier alert.
        $collector = User::role('collector')->firstOrFail();
        $this->assertFalse(NotificationQueue::where('user_id', $collector->id)->where('type', 'payment_proof_uploaded')->exists());

        Sanctum::actingAs($verifier);
        $this->getJson("/api/v1/notifications/{$row->id}")
            ->assertOk()
            ->assertJsonPath('data.reference.resource', 'payment')
            ->assertJsonPath('data.reference.available', true)
            ->assertJsonPath('data.sender.name', $customer->name);
    }

    public function test_supervisor_detail_read_unread_and_read_all_respect_collector_scope(): void
    {
        $this->seed();
        $rina = User::where('username', 'supervisor.rina')->first();
        $mine = User::where('username', 'kolektor.budi')->first();
        $notMine = User::where('username', 'kolektor.dewi')->first();

        $make = fn (?int $collectorId, array $extra = []) => SupervisorNotification::query()->create([
            'category' => 'ptp_due', 'priority' => 'high', 'title' => 'T', 'description' => 'D',
            'related_collector_id' => $collectorId, 'read_status' => 'unread',
            'handled_status' => SupervisorNotification::HANDLED_OPEN, ...$extra,
        ]);
        $visible = $make($mine->id, ['reference_type' => PaymentTransaction::class, 'reference_id' => '99999999']);
        $hidden = $make($notMine->id);

        Sanctum::actingAs($rina);
        $this->getJson("/api/v1/supervisor-notifications/{$visible->id}")
            ->assertOk()
            ->assertJsonPath('data.message', 'D')
            ->assertJsonPath('data.related_collector.id', $mine->id)
            ->assertJsonPath('data.reference.available', false);
        $this->getJson("/api/v1/supervisor-notifications/{$hidden->id}")->assertNotFound();
        $this->postJson("/api/v1/supervisor-notifications/{$hidden->id}/unread")->assertNotFound();

        $this->postJson("/api/v1/supervisor-notifications/{$visible->id}/read")->assertOk();
        $this->assertSame('read', $visible->fresh()->read_status);
        $this->postJson("/api/v1/supervisor-notifications/{$visible->id}/unread")->assertOk();
        $this->assertSame('unread', $visible->fresh()->read_status);

        $this->postJson('/api/v1/supervisor-notifications/read-all')->assertOk();
        $this->assertSame('read', $visible->fresh()->read_status);
        $this->assertSame('unread', $hidden->fresh()->read_status);

        $this->getJson('/api/v1/supervisor-notifications')->assertOk()->assertJsonPath('meta.unread_count', 0);
    }

    public function test_gateway_transactions_are_listed_by_most_recent_update_first(): void
    {
        $this->seed();
        $finance = User::where('username', 'finance')->first();
        $billing = Billing::query()->firstOrFail();

        $make = function (string $number, $at) use ($billing) {
            $transaction = new PaymentTransaction([
                'transaction_number' => $number, 'invoice_number' => $number, 'unit_id' => $billing->unit_id,
                'payment_provider' => 'manual', 'status' => 'pending', 'subtotal' => 1000, 'tax' => 0, 'admin_fee' => 0, 'total' => 1000,
            ]);
            $transaction->created_at = $at;
            $transaction->updated_at = $at;
            $transaction->save();

            return $transaction;
        };
        $oldest = $make('ZZORD-OLD', now()->subDays(3));
        $middle = $make('ZZORD-MID', now()->subDays(2));
        $newest = $make('ZZORD-NEW', now()->subDay());

        Sanctum::actingAs($finance);
        $order = fn () => collect($this->getJson('/api/v1/payments/gateway/transactions?per_page=1000&search=ZZORD-')->assertOk()->json('data'))->pluck('transaction_number')->all();
        $this->assertSame(['ZZORD-NEW', 'ZZORD-MID', 'ZZORD-OLD'], $order());

        // The oldest transaction receives a new proof -> it must move to the top.
        $oldest->update(['status' => 'waiting_verification', 'manual_proof_uploaded_at' => now()]);
        $this->assertSame(['ZZORD-OLD', 'ZZORD-NEW', 'ZZORD-MID'], $order());
    }
}
