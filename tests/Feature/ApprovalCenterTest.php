<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Billing;
use App\Models\Resident;
use App\Models\Reversal;
use App\Models\Unit;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApprovalCenterTest extends TestCase
{
    use RefreshDatabase;

    private function makeUnitWithArrears(float $amount = 500000): Unit
    {
        $resident = Resident::factory()->create();
        $unit = Unit::factory()->create([
            'resident_id' => $resident->id,
            'is_penalty_eligible' => true,
        ]);
        $finance = User::where('username', 'finance')->firstOrFail();

        Billing::query()->create([
            'unit_id' => $unit->id,
            'year' => now()->year,
            'month' => now()->month,
            'amount' => $amount,
            'status_id' => Billing::STATUS_UNPAID,
            'is_penalty_eligible' => true,
            'billing_type' => 'regular',
            'approved_by' => $finance->id,
            'approved_at' => now(),
            'created_by' => $finance->id,
        ]);

        return $unit->fresh();
    }

    private function makeSupervisor(): User
    {
        $supervisor = User::factory()->create(['is_active' => true]);
        $supervisor->assignRole('supervisor');

        return $supervisor;
    }

    /**
     * Looks up the approval row for a specific reversal by its precise (requestable_type,
     * requestable_id) link, never by "type" alone - $this->seed() also seeds unrelated
     * reversal/penalty_waiver/installment_plan/billing_adjustment approval rows via
     * SupervisorSeeder, so a generic ->where('type', ...)->first() would nondeterministically
     * grab someone else's row instead of the one this test just created.
     */
    private function approvalIdForReversal(string $receiptNumber): int
    {
        $reversal = Reversal::where('receipt_number', $receiptNumber)->latest('id')->firstOrFail();

        return ApprovalRequest::where('requestable_type', Reversal::class)->where('requestable_id', $reversal->id)->firstOrFail()->id;
    }

    public function test_reversal_submission_opens_a_linked_approval_request_and_supervisor_approval_applies_the_reversal(): void
    {
        $this->seed();
        $unit = $this->makeUnitWithArrears();
        $loket = User::where('username', 'loket')->firstOrFail();
        $billingId = Billing::where('unit_id', $unit->id)->first()->id;

        $receipt = app(PaymentService::class)->process($unit, [$billingId], ['payment_method_id' => 'C'], $loket->id);
        $this->assertSame(Billing::STATUS_PAID, Billing::find($billingId)->status_id);

        Sanctum::actingAs($loket);
        $this->postJson('/api/v1/reversals', [
            'receipt_number' => $receipt->number,
            'reason' => 'Kesalahan input kasir.',
        ])->assertCreated();

        $approvalId = $this->approvalIdForReversal($receipt->number);

        $supervisor = $this->makeSupervisor();
        Sanctum::actingAs($supervisor);
        $this->postJson("/api/v1/approval-requests/{$approvalId}/approve", [])->assertOk();

        $this->assertSame(Billing::STATUS_UNPAID, Billing::find($billingId)->status_id);
        $this->assertDatabaseHas('approval_requests', ['id' => $approvalId, 'status' => 'approved']);
        $this->assertDatabaseHas('reversals', ['receipt_number' => $receipt->number, 'status' => 'approved']);
    }

    public function test_approval_rejection_leaves_underlying_billing_untouched(): void
    {
        $this->seed();
        $unit = $this->makeUnitWithArrears();
        $loket = User::where('username', 'loket')->firstOrFail();
        $billingId = Billing::where('unit_id', $unit->id)->first()->id;

        $receipt = app(PaymentService::class)->process($unit, [$billingId], ['payment_method_id' => 'C'], $loket->id);

        Sanctum::actingAs($loket);
        $this->postJson('/api/v1/reversals', [
            'receipt_number' => $receipt->number,
            'reason' => 'Uji tolak.',
        ])->assertCreated();

        $approvalId = $this->approvalIdForReversal($receipt->number);

        $supervisor = $this->makeSupervisor();
        Sanctum::actingAs($supervisor);
        $this->postJson("/api/v1/approval-requests/{$approvalId}/reject", ['notes' => 'Bukti tidak cukup.'])->assertOk();

        $this->assertSame(Billing::STATUS_PAID, Billing::find($billingId)->status_id);
        $this->assertDatabaseHas('approval_requests', ['id' => $approvalId, 'status' => 'rejected']);
    }

    public function test_a_role_without_approvals_permission_cannot_approve(): void
    {
        $this->seed();
        $unit = $this->makeUnitWithArrears();
        $loket = User::where('username', 'loket')->firstOrFail();
        $billingId = Billing::where('unit_id', $unit->id)->first()->id;

        $receipt = app(PaymentService::class)->process($unit, [$billingId], ['payment_method_id' => 'C'], $loket->id);

        Sanctum::actingAs($loket);
        $this->postJson('/api/v1/reversals', [
            'receipt_number' => $receipt->number,
            'reason' => 'Uji tanpa izin.',
        ])->assertCreated();

        $approvalId = $this->approvalIdForReversal($receipt->number);

        $collector = User::factory()->create(['is_active' => true]);
        $collector->assignRole('collector');
        Sanctum::actingAs($collector);
        $this->postJson("/api/v1/approval-requests/{$approvalId}/approve", [])->assertForbidden();
    }

    public function test_installment_plan_submission_and_supervisor_approval_activates_it(): void
    {
        $this->seed();
        $unit = $this->makeUnitWithArrears();
        $billingId = Billing::where('unit_id', $unit->id)->first()->id;
        $loket = User::where('username', 'loket')->firstOrFail();

        Sanctum::actingAs($loket);
        $approvalId = $this->postJson('/api/v1/approval-requests/installment-plans', [
            'unit_id' => $unit->id,
            'billing_id' => $billingId,
            'total_outstanding' => 500000,
            'number_of_installments' => 5,
            'start_date' => now()->toDateString(),
            'reason' => 'Kesulitan ekonomi penghuni.',
        ])->assertCreated()->json('data.id');

        $supervisor = $this->makeSupervisor();
        Sanctum::actingAs($supervisor);
        $this->postJson("/api/v1/approval-requests/{$approvalId}/approve", [])->assertOk();

        $this->assertDatabaseHas('installment_plans', ['unit_id' => $unit->id, 'status' => 'active']);
    }

    public function test_billing_discount_adjustment_submission_and_approval_applies_discount(): void
    {
        $this->seed();
        $unit = $this->makeUnitWithArrears();
        $billing = Billing::where('unit_id', $unit->id)->first();
        $backOffice = User::where('username', 'back_office')->first() ?? User::where('username', 'root')->first();

        Sanctum::actingAs($backOffice);
        $approvalId = $this->postJson('/api/v1/approval-requests/billing-adjustments', [
            'billing_id' => $billing->id,
            'adjustment_type' => 'discount',
            'new_value' => 50000,
            'reason' => 'Kompensasi keluhan.',
        ])->assertCreated()->json('data.id');

        $supervisor = $this->makeSupervisor();
        Sanctum::actingAs($supervisor);
        $this->postJson("/api/v1/approval-requests/{$approvalId}/approve", [])->assertOk();

        $this->assertSame(50000.0, (float) $billing->fresh()->discount);
        $this->assertDatabaseHas('billing_adjustments', ['billing_id' => $billing->id, 'status' => 'approved']);
    }
}
