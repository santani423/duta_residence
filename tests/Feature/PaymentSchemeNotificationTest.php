<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\NotificationQueue;
use App\Models\PaymentScheme;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * End-to-end coverage for the payment-scheme notification pipeline: submission tells every
 * eligible approver, and each decision tells the scheme's own submitter back - and never
 * anyone else. Recipients are always resolved by permission/role (never a hardcoded id), which
 * these tests pin down by asserting on the exact set of usernames notified.
 */
class PaymentSchemeNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function unitWithArrears(): array
    {
        $unit = Unit::factory()->create(['resident_id' => Resident::factory()->create()->id, 'is_penalty_eligible' => true]);
        $finance = User::where('username', 'finance')->firstOrFail();

        $billings = collect([3, 2, 1])->map(function (int $offset) use ($unit, $finance) {
            $period = now()->startOfMonth()->subMonths($offset);

            return Billing::query()->create([
                'unit_id' => $unit->id, 'year' => $period->year, 'month' => $period->month,
                'amount' => 500000, 'status_id' => Billing::STATUS_UNPAID, 'is_penalty_eligible' => true,
                'billing_type' => 'regular', 'approved_by' => $finance->id, 'approved_at' => $period->copy()->addDays(2),
                'created_by' => $finance->id,
            ]);
        });

        return [$unit, $billings];
    }

    private function as(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(Unit $unit, $billings, array $overrides = []): array
    {
        return [
            'unit_id' => $unit->id,
            'billing_ids' => $billings->pluck('id')->all(),
            'discount_type' => 'nominal',
            'discount_value' => 100000,
            'reason' => 'Pelanggan minta keringanan',
            ...$overrides,
        ];
    }

    private function submitAs(string $username, Unit $unit, $billings, array $overrides = []): PaymentScheme
    {
        $this->as($username);
        $id = $this->postJson('/api/v1/payment-schemes', $this->payload($unit, $billings, $overrides))->assertCreated()->json('data.id');

        return PaymentScheme::findOrFail($id);
    }

    private function notificationsFor(string $username, string $type, PaymentScheme $scheme): Collection
    {
        $userId = User::where('username', $username)->value('id');

        return NotificationQueue::query()
            ->where('user_id', $userId)
            ->where('type', $type)
            ->where('reference_type', PaymentScheme::class)
            ->where('reference_id', (string) $scheme->id)
            ->get();
    }

    public function test_loket_submission_notifies_every_approver_but_not_loket_itself(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submitAs('loket', $unit, $billings);

        foreach (['admin.estate', 'admin.estate2', 'superadmin', 'root'] as $approver) {
            $rows = $this->notificationsFor($approver, 'payment_scheme_submitted', $scheme);
            $this->assertCount(1, $rows, "{$approver} should receive exactly one submission notification.");
            $this->assertStringContainsString((string) $scheme->id, $rows->first()->message);
            $this->assertStringContainsString('Loket Kasir', $rows->first()->message);
        }

        $this->assertCount(0, $this->notificationsFor('loket', 'payment_scheme_submitted', $scheme));
    }

    public function test_admin_submission_notifies_only_the_unlimited_approvers(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submitAs('admin.estate', $unit, $billings);

        foreach (['superadmin', 'root'] as $approver) {
            $this->assertCount(1, $this->notificationsFor($approver, 'payment_scheme_submitted', $scheme), "{$approver} should be notified when an Admin submits.");
        }

        // Another Admin has nothing to decide here - only Super Admin/root do.
        $this->assertCount(0, $this->notificationsFor('admin.estate2', 'payment_scheme_submitted', $scheme));
        $this->assertCount(0, $this->notificationsFor('admin.estate', 'payment_scheme_submitted', $scheme));
    }

    public function test_admin_approval_notifies_the_submitting_loket(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submitAs('loket', $unit, $billings);

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", ['notes' => 'ok'])->assertOk();

        $rows = $this->notificationsFor('loket', 'payment_scheme_approved', $scheme);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('disetujui', $rows->first()->message);
        $this->assertStringContainsString('Estate Admin Utama', $rows->first()->message);
        $this->assertSame(User::where('username', 'admin.estate')->value('id'), $rows->first()->sender_id);
    }

    public function test_admin_rejection_notifies_the_submitting_loket_with_reason(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submitAs('loket', $unit, $billings);

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/reject", ['notes' => 'Dokumen tidak lengkap'])->assertOk();

        $rows = $this->notificationsFor('loket', 'payment_scheme_rejected', $scheme);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('ditolak', $rows->first()->message);
        $this->assertStringContainsString('Dokumen tidak lengkap', $rows->first()->message);
    }

    public function test_super_admin_decision_on_an_over_limit_scheme_notifies_the_submitter(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submitAs('loket', $unit, $billings, ['discount_type' => 'percentage', 'discount_value' => 50]);

        $this->as('superadmin');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve")->assertOk();

        $rows = $this->notificationsFor('loket', 'payment_scheme_approved', $scheme);
        $this->assertCount(1, $rows);
        $this->assertStringContainsString('Super Admin', $rows->first()->message);
    }

    public function test_approving_via_the_generic_approval_center_endpoint_still_notifies_the_submitter(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submitAs('loket', $unit, $billings);

        $this->as('admin.estate');
        $this->postJson("/api/v1/approval-requests/{$scheme->approvalRequest->id}/approve")->assertOk();

        $this->assertCount(1, $this->notificationsFor('loket', 'payment_scheme_approved', $scheme));
    }

    public function test_auto_cancellation_notifies_the_submitter(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submitAs('loket', $unit, $billings);

        $this->as('loket');
        $this->postJson('/api/v1/payments/process', [
            'unit_id' => $unit->id, 'billing_ids' => [$billings->first()->id], 'amount' => 100000, 'use_balance' => false, 'payment_method_id' => 'C',
        ])->assertCreated();

        $this->assertCount(1, $this->notificationsFor('loket', 'payment_scheme_cancelled', $scheme->fresh()));
    }

    public function test_uninvolved_staff_never_receive_payment_scheme_notifications(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submitAs('loket', $unit, $billings);
        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve")->assertOk();

        foreach (['finance', 'cs', 'property.manager'] as $bystander) {
            $count = NotificationQueue::query()
                ->where('user_id', User::where('username', $bystander)->value('id'))
                ->where('reference_type', PaymentScheme::class)
                ->where('reference_id', (string) $scheme->id)
                ->count();
            $this->assertSame(0, $count, "{$bystander} should not receive any notification about this scheme.");
        }
    }

    public function test_notification_survives_refresh_and_marking_read_reduces_unread_count(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submitAs('loket', $unit, $billings);

        $this->as('admin.estate');
        $before = $this->getJson('/api/v1/notifications/unread-count')->assertOk()->json('data.unread_count');
        $this->assertGreaterThan(0, $before);

        // "Refresh" is just another read against the same, already-persisted rows.
        $list = $this->getJson('/api/v1/notifications')->assertOk()->json('data');
        $row = collect($list)->firstWhere('type', 'payment_scheme_submitted');
        $this->assertNotNull($row);
        $this->assertSame('unread', $row['read_status']);

        $this->postJson("/api/v1/notifications/{$row['id']}/read")->assertOk();

        $after = $this->getJson('/api/v1/notifications/unread-count')->assertOk()->json('data.unread_count');
        $this->assertSame($before - 1, $after);
    }

    public function test_notification_reference_points_to_the_payment_scheme_for_navigation(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submitAs('loket', $unit, $billings);

        $this->as('admin.estate');
        $row = $this->notificationsFor('admin.estate', 'payment_scheme_submitted', $scheme)->first();

        $data = $this->getJson("/api/v1/notifications/{$row->id}")->assertOk()->json('data');

        $this->assertSame('payment_scheme', $data['reference']['resource']);
        $this->assertSame((string) $scheme->id, $data['reference']['id']);
        $this->assertTrue($data['reference']['available']);
    }
}
