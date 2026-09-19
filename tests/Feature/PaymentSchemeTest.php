<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Billing;
use App\Models\PaymentScheme;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\PenaltyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentSchemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Unit with approved monthly bills (Rp500.000 each). Default: 3, 4 and 5 months overdue (top
     * penalty tier); pass [2, 1] for the lower 1-2 month tier, which rises after the month rolls over.
     */
    private function unitWithArrears(array $offsets = [5, 4, 3]): array
    {
        $unit = Unit::factory()->create(['resident_id' => Resident::factory()->create()->id, 'is_penalty_eligible' => true]);
        $finance = User::where('username', 'finance')->firstOrFail();

        $billings = collect($offsets)->map(function (int $offset) use ($unit, $finance) {
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
            'penalty_reduction' => 0,
            'reason' => 'Pelanggan minta keringanan',
            ...$overrides,
        ];
    }

    private function submit(Unit $unit, $billings, array $overrides = []): PaymentScheme
    {
        $this->as('loket');
        $id = $this->postJson('/api/v1/payment-schemes', $this->payload($unit, $billings, $overrides))->assertCreated()->json('data.id');

        return PaymentScheme::findOrFail($id);
    }

    private function totalDue($billings): float
    {
        $service = app(PenaltyService::class);

        return round($billings->sum(fn ($b) => $service->calculateInvoiceTotal($b->fresh())['total_outstanding']), 2);
    }

    public function test_preview_shows_original_discount_penalty_reduction_and_final_amount(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $penalty = app(PenaltyService::class)->calculateUnitOutstanding($unit->id)['total_penalty_outstanding'];
        $this->assertGreaterThan(0, $penalty);
        $this->as('loket');

        $data = $this->postJson('/api/v1/payment-schemes/preview', $this->payload($unit, $billings, ['penalty_reduction' => 10000]))
            ->assertOk()->json('data');

        $this->assertSame(1500000.0, (float) $data['original_principal']);
        $this->assertSame(100000.0, (float) $data['principal_discount']);
        $this->assertSame((float) $penalty, (float) $data['original_penalty']);
        $this->assertSame(10000.0, (float) $data['penalty_reduction']);
        $this->assertEqualsWithDelta(1500000 - 100000 + $penalty - 10000, $data['final_amount'], 0.001);
        // Shares add up exactly, even when they do not divide evenly.
        $this->assertEqualsWithDelta(100000, collect($data['items'])->sum('principal_discount'), 0.001);
        $this->assertEqualsWithDelta(10000, collect($data['items'])->sum('penalty_reduction'), 0.001);
        $this->assertSame(0, PaymentScheme::count());
    }

    public function test_discount_cannot_exceed_outstanding_principal_or_penalty_reduction_exceed_penalty(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $this->as('loket');

        $this->postJson('/api/v1/payment-schemes/preview', $this->payload($unit, $billings, ['discount_value' => 1500001]))
            ->assertStatus(422)->assertJsonValidationErrors('discount_value');
        $this->postJson('/api/v1/payment-schemes/preview', $this->payload($unit, $billings, ['penalty_reduction' => 99999999]))
            ->assertStatus(422)->assertJsonValidationErrors('penalty_reduction');
    }

    public function test_counter_officer_submits_pending_scheme_with_approval_request_and_nothing_is_applied_yet(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings);

        $this->assertSame(PaymentScheme::STATUS_PENDING, $scheme->status);
        $this->assertSame(3, $scheme->items()->count());
        $this->assertSame(ApprovalRequest::STATUS_SUBMITTED, $scheme->approvalRequest->status);
        $this->assertSame(0.0, (float) $billings->first()->fresh()->discount);
        $this->assertNull($billings->first()->fresh()->payment_scheme_id);
    }

    public function test_admin_approval_applies_discount_and_penalty_and_combines_bills_into_one_obligation(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $penalty = app(PenaltyService::class)->calculateUnitOutstanding($unit->id)['total_penalty_outstanding'];
        $scheme = $this->submit($unit, $billings, ['penalty_reduction' => 10000]);
        $expected = 1500000 - 100000 + $penalty - 10000;

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", ['notes' => 'ok'])->assertOk();

        $scheme->refresh();
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $scheme->status);
        $this->assertSame(ApprovalRequest::STATUS_APPROVED, $scheme->approvalRequest->status);
        $this->assertEqualsWithDelta($expected, $this->totalDue($billings), 0.001);
        $this->assertEqualsWithDelta($expected, (float) $scheme->final_amount, 0.001);
        $this->assertSame([$scheme->id], $billings->map(fn ($b) => $b->fresh()->payment_scheme_id)->unique()->all());
        // Original amounts stay on the scheme items for audit.
        $this->assertEqualsWithDelta(1500000, (float) $scheme->items()->sum('original_principal'), 0.001);
        $this->assertEqualsWithDelta($penalty, (float) $scheme->items()->sum('original_penalty'), 0.001);
    }

    public function test_approved_penalty_stays_frozen_when_the_month_rolls_over(): void
    {
        [$unit, $billings] = $this->unitWithArrears([2, 1]);
        $scheme = $this->submit($unit, $billings, ['penalty_reduction' => 5000]);
        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve")->assertOk();
        $before = $this->totalDue($billings);

        Carbon::setTestNow(now()->addMonths(3));

        $this->assertEqualsWithDelta($before, $this->totalDue($billings), 0.001);

        // Without a scheme the same bills would now sit in the higher tier.
        $unfrozen = $billings->map(fn ($b) => tap($b->fresh(), fn ($x) => $x->penalty_fixed = null))
            ->sum(fn ($b) => app(PenaltyService::class)->calculateInvoiceTotal($b)['total_outstanding']);
        $this->assertGreaterThan($before, $unfrozen);
    }

    public function test_rejected_scheme_changes_nothing(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $before = $this->totalDue($billings);
        $scheme = $this->submit($unit, $billings);

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/reject", ['notes' => 'Tidak memenuhi syarat'])->assertOk();

        $this->assertSame(PaymentScheme::STATUS_REJECTED, $scheme->fresh()->status);
        $this->assertSame($before, $this->totalDue($billings));
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve")->assertStatus(422);
    }

    public function test_duplicate_active_scheme_for_the_same_bills_is_refused_until_the_first_ends(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $first = $this->submit($unit, $billings);

        $this->as('loket');
        $this->postJson('/api/v1/payment-schemes', $this->payload($unit, $billings->take(1)))->assertStatus(422)->assertJsonValidationErrors('billing_ids');

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$first->id}/approve")->assertOk();
        $this->as('loket');
        $this->postJson('/api/v1/payment-schemes', $this->payload($unit, $billings))->assertStatus(422);

        $this->postJson('/api/v1/payment-schemes/preview', $this->payload($unit, $billings))->assertStatus(422);
    }

    public function test_new_request_is_allowed_after_rejection(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $first = $this->submit($unit, $billings);
        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$first->id}/reject", ['notes' => 'x'])->assertOk();

        $second = $this->submit($unit, $billings, ['discount_value' => 50000]);

        $this->assertSame(PaymentScheme::STATUS_PENDING, $second->status);
        $this->assertSame(PaymentScheme::STATUS_REJECTED, $first->fresh()->status);
    }

    public function test_payment_on_a_related_bill_cancels_the_pending_scheme_automatically(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings);

        app(PaymentService::class)->process($unit, [$billings->first()->id], ['amount' => 100000], User::where('username', 'loket')->firstOrFail()->id);

        $scheme->refresh();
        $this->assertSame(PaymentScheme::STATUS_CANCELLED, $scheme->status);
        $this->assertNotNull($scheme->cancelled_at);
        $this->assertSame(ApprovalRequest::STATUS_CANCELLED, $scheme->approvalRequest->status);

        // A cancelled scheme can no longer be approved, and nothing was applied.
        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve")->assertStatus(422);
        $this->assertSame(0.0, (float) $billings->last()->fresh()->discount);
    }

    public function test_payment_on_an_unrelated_bill_does_not_cancel_the_scheme(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $other = Billing::query()->create([
            'unit_id' => $unit->id, 'year' => now()->year, 'month' => now()->month, 'amount' => 300000,
            'status_id' => Billing::STATUS_UNPAID, 'is_penalty_eligible' => true, 'billing_type' => 'special',
            'approved_at' => now(), 'created_by' => User::where('username', 'finance')->firstOrFail()->id,
        ]);
        $scheme = $this->submit($unit, $billings);

        app(PaymentService::class)->process($unit, [$other->id], [], User::where('username', 'loket')->firstOrFail()->id);

        $this->assertSame(PaymentScheme::STATUS_PENDING, $scheme->fresh()->status);
    }

    public function test_new_request_after_cancellation_uses_the_latest_billing_condition(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $old = $this->submit($unit, $billings);
        app(PaymentService::class)->process($unit, [$billings->first()->id], ['amount' => 200000, 'use_balance' => false], User::where('username', 'loket')->firstOrFail()->id);
        $this->assertSame(PaymentScheme::STATUS_CANCELLED, $old->fresh()->status);

        $fresh = $this->submit($unit, $billings);

        $outstanding = app(PenaltyService::class)->calculateUnitOutstanding($unit->id);
        $this->assertEqualsWithDelta($outstanding['total_principal_outstanding'], (float) $fresh->original_principal, 0.001);
        $this->assertLessThan((float) $old->original_principal, (float) $fresh->original_principal);
    }

    public function test_scheme_whose_billing_changed_without_a_hook_is_cancelled_when_approval_is_attempted(): void
    {
        [$unit, $billings] = $this->unitWithArrears([2, 1]);
        $scheme = $this->submit($unit, $billings);

        // Month rolls over -> higher penalty tier -> the calculation the scheme was based on is outdated.
        Carbon::setTestNow(now()->addMonths(3));
        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve")->assertStatus(422);

        $this->assertSame(PaymentScheme::STATUS_CANCELLED, $scheme->fresh()->status);
        $this->assertSame(ApprovalRequest::STATUS_CANCELLED, $scheme->approvalRequest->fresh()->status);
    }

    public function test_bills_of_an_approved_scheme_must_be_paid_together(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings);
        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve")->assertOk();
        $loket = User::where('username', 'loket')->firstOrFail();

        try {
            app(PaymentService::class)->process($unit, [$billings->first()->id], [], $loket->id);
            $this->fail('Partial scheme payment should be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('billing_ids', $e->errors());
        }

        $receipt = app(PaymentService::class)->process($unit, $billings->pluck('id')->all(), ['use_balance' => false], $loket->id);

        $this->assertEqualsWithDelta((float) $scheme->final_amount, (float) $receipt->grand_total, 0.001);
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $scheme->fresh()->status);
    }

    public function test_only_admin_can_decide_and_supervisor_cannot_use_the_approval_center_shortcut(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings);

        $this->as('loket');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve")->assertForbidden();

        $supervisor = User::role('supervisor')->firstOrFail();
        Sanctum::actingAs($supervisor);
        $this->postJson("/api/v1/approval-requests/{$scheme->approvalRequest->id}/approve")->assertForbidden();
        $this->assertSame(PaymentScheme::STATUS_PENDING, $scheme->fresh()->status);

        $this->as('admin.estate');
        $this->postJson("/api/v1/approval-requests/{$scheme->approvalRequest->id}/approve")->assertOk();
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $scheme->fresh()->status);
    }

    public function test_admin_discount_limit_applies_at_approval_but_super_admin_is_unlimited(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings, ['discount_type' => 'percentage', 'discount_value' => 50]);

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve")->assertStatus(422)->assertJsonValidationErrors('discount');
        $this->assertSame(PaymentScheme::STATUS_PENDING, $scheme->fresh()->status);

        $this->as('superadmin');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve")->assertOk();
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $scheme->fresh()->status);
    }

    public function test_list_includes_unit_resident_items_and_supports_search_and_status_filter(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings);

        $this->as('loket');
        $row = $this->getJson('/api/v1/payment-schemes?search='.$unit->resident->name.'&status=pending')
            ->assertOk()->assertJsonPath('data.0.id', $scheme->id)->json('data.0');

        $this->assertSame($unit->resident->name, $row['unit']['resident']['name']);
        $this->assertCount(3, $row['items']);
        $this->getJson('/api/v1/payment-schemes?status=approved')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/payment-schemes?search=tidak-ada-orang-ini')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_loket_can_find_a_unit_by_id_and_load_its_outstanding_bills_for_the_form(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $this->as('loket');

        $this->getJson('/api/v1/units?per_page=20&search='.$unit->id)->assertOk()->assertJsonPath('data.0.id', $unit->id)->assertJsonPath('data.0.resident.name', $unit->resident->name);

        $data = $this->postJson('/api/v1/payment-schemes/preview', $this->payload($unit, $billings))->assertOk()->json('data');
        $this->assertCount(3, $data['items']);

        $found = $this->getJson('/api/v1/payments/search?unit_id='.$unit->id)->assertOk()->json('data');
        $this->assertCount(3, $found['billings']);
        $this->assertArrayHasKey('penalty_detail', $found['billings'][0]);
        $this->assertArrayHasKey('payment_scheme_id', $found['billings'][0]);
    }

    public function test_penalty_reduction_can_be_given_per_billing(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $this->as('loket');
        $penalty = fn ($b) => app(PenaltyService::class)->calculateInvoiceTotal($b->fresh())['outstanding_penalty'];
        [$first, $second, $third] = $billings->all();

        $data = $this->postJson('/api/v1/payment-schemes/preview', $this->payload($unit, $billings, [
            'discount_value' => 0,
            'penalty_reduction' => null,
            'penalty_reductions' => [$first->id => $penalty($first), $second->id => 10000],
        ]))->assertOk()->json('data');

        $byBilling = collect($data['items'])->keyBy('billing_id');
        $this->assertEqualsWithDelta($penalty($first), $byBilling[$first->id]['penalty_reduction'], 0.001);
        $this->assertSame(0.0, (float) $byBilling[$first->id]['final_penalty']);
        $this->assertEqualsWithDelta(10000, $byBilling[$second->id]['penalty_reduction'], 0.001);
        $this->assertSame(0.0, (float) $byBilling[$third->id]['penalty_reduction']);
        $this->assertEqualsWithDelta($penalty($first) + 10000, $data['penalty_reduction'], 0.001);

        // Submitting stores exactly the per-billing amounts.
        $id = $this->postJson('/api/v1/payment-schemes', $this->payload($unit, $billings, [
            'discount_value' => 0, 'penalty_reduction' => null, 'penalty_reductions' => [$second->id => 10000],
        ]))->assertCreated()->json('data.id');
        $scheme = PaymentScheme::findOrFail($id);
        $this->assertEqualsWithDelta(10000, (float) $scheme->penalty_reduction, 0.001);
        $this->assertEqualsWithDelta(10000, (float) $scheme->items()->where('billing_id', $second->id)->value('penalty_reduction'), 0.001);
        $this->assertEqualsWithDelta(0, (float) $scheme->items()->where('billing_id', $first->id)->value('penalty_reduction'), 0.001);
    }

    public function test_per_billing_penalty_reduction_cannot_exceed_that_billings_penalty_or_target_unselected_bills(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $this->as('loket');
        $other = Billing::query()->create([
            'unit_id' => $unit->id, 'year' => now()->year, 'month' => now()->month, 'amount' => 300000,
            'status_id' => Billing::STATUS_UNPAID, 'billing_type' => 'special', 'approved_at' => now(),
            'created_by' => User::where('username', 'finance')->firstOrFail()->id,
        ]);
        $penalty = app(PenaltyService::class)->calculateInvoiceTotal($billings->first()->fresh())['outstanding_penalty'];

        $this->postJson('/api/v1/payment-schemes/preview', $this->payload($unit, $billings, [
            'penalty_reductions' => [$billings->first()->id => $penalty + 1],
        ]))->assertStatus(422)->assertJsonValidationErrors('penalty_reductions.'.$billings->first()->id);

        $this->postJson('/api/v1/payment-schemes/preview', $this->payload($unit, $billings, [
            'penalty_reductions' => [$other->id => 1000],
        ]))->assertStatus(422)->assertJsonValidationErrors('penalty_reductions');
    }
}
