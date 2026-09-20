<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Billing;
use App\Models\PaymentScheme;
use App\Models\PaymentSchemeItem;
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

    public function test_admin_can_change_the_loket_request_while_approving_and_both_versions_are_kept(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings, ['discount_value' => 100000, 'penalty_reduction' => 0]);
        $requestedFinal = (float) $scheme->final_amount;
        [$first, $second] = $billings->all();
        // Read before approving: afterwards the penalty of an approved scheme is frozen at its final value.
        $firstPenalty = app(PenaltyService::class)->calculateInvoiceTotal($first->fresh())['outstanding_penalty'];
        $totalPenalty = app(PenaltyService::class)->calculateUnitOutstanding($unit->id)['total_penalty_outstanding'];
        $expectedReduction = $firstPenalty + 5000;

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'notes' => 'Diskon dikurangi, denda dihapus sebagian',
            'adjustments' => [
                'discount_type' => 'nominal',
                'discount_value' => 60000,
                'penalty_reductions' => [$first->id => $firstPenalty, $second->id => 5000],
            ],
        ])->assertOk();

        $scheme->refresh();
        $expectedFinal = 1500000 - 60000 + $totalPenalty - $expectedReduction;
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $scheme->status);
        $this->assertEqualsWithDelta(60000, (float) $scheme->principal_discount, 0.001);
        $this->assertEqualsWithDelta($expectedReduction, (float) $scheme->penalty_reduction, 0.001);
        $this->assertEqualsWithDelta($expectedFinal, (float) $scheme->final_amount, 0.001);
        // The billings carry the ADJUSTED terms, not the loket's.
        $this->assertEqualsWithDelta(60000, (float) $billings->sum(fn ($b) => $b->fresh()->discount), 0.001);
        $this->assertEqualsWithDelta($expectedFinal, $this->totalDue($billings), 0.001);
        // The loket's original request is preserved for audit, and who adjusted is recorded.
        $this->assertSame(User::where('username', 'admin.estate')->value('id'), $scheme->adjusted_by);
        $this->assertNotNull($scheme->adjusted_at);
        $this->assertEqualsWithDelta(100000, $scheme->requested_snapshot['principal_discount'], 0.001);
        $this->assertEqualsWithDelta($requestedFinal, $scheme->requested_snapshot['final_amount'], 0.001);
        $this->assertCount(3, $scheme->requested_snapshot['items']);
        $this->assertEqualsWithDelta($expectedFinal, (float) $scheme->approvalRequest->fresh()->amount, 0.001);
        $this->assertSame(ApprovalRequest::STATUS_APPROVED, $scheme->approvalRequest->fresh()->status);
    }

    public function test_approving_with_unchanged_terms_is_not_marked_as_adjusted(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings, ['discount_value' => 100000]);

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'adjustments' => ['discount_type' => 'nominal', 'discount_value' => 100000],
        ])->assertOk();

        $scheme->refresh();
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $scheme->status);
        $this->assertNull($scheme->adjusted_at);
        $this->assertNull($scheme->requested_snapshot);
    }

    public function test_adjustment_breaking_the_admin_limit_or_the_penalty_cap_changes_nothing(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings, ['discount_value' => 100000]);
        $penalty = app(PenaltyService::class)->calculateInvoiceTotal($billings->first()->fresh())['outstanding_penalty'];

        $this->as('admin.estate');
        // 50% is above the Admin's 30% cap -> refused and rolled back.
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'adjustments' => ['discount_type' => 'percentage', 'discount_value' => 50],
        ])->assertStatus(422)->assertJsonValidationErrors('discount');
        // Reduction above that bill's own penalty -> refused.
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'adjustments' => ['discount_type' => 'nominal', 'discount_value' => 0, 'penalty_reductions' => [$billings->first()->id => $penalty + 1]],
        ])->assertStatus(422);

        $scheme->refresh();
        $this->assertSame(PaymentScheme::STATUS_PENDING, $scheme->status);
        $this->assertEqualsWithDelta(100000, (float) $scheme->principal_discount, 0.001);
        $this->assertNull($scheme->adjusted_at);
        $this->assertSame(0.0, (float) $billings->first()->fresh()->discount);
    }

    public function test_admin_can_preview_an_adjustment_of_a_pending_scheme_but_loket_cannot(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings, ['discount_value' => 100000]);

        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/preview-adjustment", [
            'adjustments' => ['discount_type' => 'nominal', 'discount_value' => 40000],
        ])->assertForbidden();

        $this->as('admin.estate');
        $data = $this->postJson("/api/v1/payment-schemes/{$scheme->id}/preview-adjustment", [
            'adjustments' => ['discount_type' => 'nominal', 'discount_value' => 40000],
        ])->assertOk()->json('data');

        $this->assertEqualsWithDelta(40000, $data['principal_discount'], 0.001);
        $this->assertCount(3, $data['items']);
        // Nothing stored.
        $this->assertEqualsWithDelta(100000, (float) $scheme->fresh()->principal_discount, 0.001);
    }

    public function test_loket_cannot_smuggle_adjustments_through_approve(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings);

        $this->as('loket');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", ['adjustments' => ['discount_type' => 'nominal', 'discount_value' => 1500000]])->assertForbidden();
        $this->assertSame(PaymentScheme::STATUS_PENDING, $scheme->fresh()->status);
    }

    public function test_a_stale_scheme_is_cancelled_even_when_admin_tries_to_adjust_it(): void
    {
        [$unit, $billings] = $this->unitWithArrears([2, 1]);
        $scheme = $this->submit($unit, $billings);

        Carbon::setTestNow(now()->addMonths(3));
        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", ['adjustments' => ['discount_type' => 'nominal', 'discount_value' => 10000]])->assertStatus(422);

        $this->assertSame(PaymentScheme::STATUS_CANCELLED, $scheme->fresh()->status);
    }

    public function test_admin_can_reject_a_single_month_and_approve_the_rest(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        [$may, $june, $july] = $billings->all();
        $scheme = $this->submit($unit, $billings, ['discount_value' => 150000]);
        $requested = (float) $scheme->final_amount;
        $penalty = fn ($b) => app(PenaltyService::class)->calculateInvoiceTotal($b->fresh())['outstanding_penalty'];
        $junePenalty = $penalty($june);
        $keptPenalty = $penalty($may) + $penalty($july);

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'notes' => 'Bulan Juni tidak memenuhi syarat',
            'adjustments' => ['discount_type' => 'nominal', 'discount_value' => 100000, 'rejected_billing_ids' => [$june->id]],
        ])->assertOk();

        $scheme->refresh();
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $scheme->status);
        // Totals cover only the two remaining months.
        $this->assertEqualsWithDelta(1000000, (float) $scheme->original_principal, 0.001);
        $this->assertEqualsWithDelta(100000, (float) $scheme->principal_discount, 0.001);
        $this->assertEqualsWithDelta(900000 + $keptPenalty, (float) $scheme->final_amount, 0.001);
        $this->assertSame(PaymentSchemeItem::STATUS_REJECTED, $scheme->items()->where('billing_id', $june->id)->value('status'));
        $this->assertSame(2, $scheme->includedItems()->count());
        // May and July carry the scheme; June is an ordinary bill again, untouched.
        $this->assertSame($scheme->id, $may->fresh()->payment_scheme_id);
        $this->assertSame($scheme->id, $july->fresh()->payment_scheme_id);
        $this->assertNull($june->fresh()->payment_scheme_id);
        $this->assertSame(0.0, (float) $june->fresh()->discount);
        $this->assertNull($june->fresh()->penalty_fixed);
        $this->assertEqualsWithDelta(500000 + $junePenalty, app(PenaltyService::class)->calculateInvoiceTotal($june->fresh())['total_outstanding'], 0.001);
        // The loket's original 3-month request is kept.
        $this->assertEqualsWithDelta(1500000, $scheme->requested_snapshot['original_principal'], 0.001);
        $this->assertEqualsWithDelta($requested, $scheme->requested_snapshot['final_amount'], 0.001);
        $this->assertCount(3, $scheme->requested_snapshot['items']);
        $this->assertEqualsWithDelta((float) $scheme->final_amount, (float) $scheme->approvalRequest->fresh()->amount, 0.001);

        // June is not bound to the scheme: it can be paid alone, but May + July must go together.
        $loket = User::where('username', 'loket')->firstOrFail();
        try {
            app(PaymentService::class)->process($unit, [$may->id], ['use_balance' => false], $loket->id);
            $this->fail('May alone should be refused: it belongs to the scheme with July.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('billing_ids', $e->errors());
        }
        $receipt = app(PaymentService::class)->process($unit, [$june->id], ['use_balance' => false], $loket->id);
        $this->assertEqualsWithDelta(500000 + $junePenalty, (float) $receipt->grand_total, 0.001);
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $scheme->fresh()->status);
    }

    public function test_a_rejected_month_can_be_requested_again_in_a_new_scheme(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $june = $billings[1];
        $scheme = $this->submit($unit, $billings);
        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'adjustments' => ['discount_type' => 'nominal', 'discount_value' => 0, 'rejected_billing_ids' => [$june->id]],
        ])->assertOk();

        $again = $this->submit($unit, collect([$june]), ['discount_value' => 20000]);

        $this->assertSame(PaymentScheme::STATUS_PENDING, $again->status);
    }

    public function test_admin_cannot_reject_every_month_or_a_month_outside_the_scheme(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings->take(2));
        $outside = $billings->last();

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'adjustments' => ['discount_type' => 'nominal', 'discount_value' => 0, 'rejected_billing_ids' => $billings->take(2)->pluck('id')->all()],
        ])->assertStatus(422)->assertJsonValidationErrors('rejected_billing_ids');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'adjustments' => ['discount_type' => 'nominal', 'discount_value' => 0, 'rejected_billing_ids' => [$outside->id]],
        ])->assertStatus(422)->assertJsonValidationErrors('rejected_billing_ids');

        $scheme->refresh();
        $this->assertSame(PaymentScheme::STATUS_PENDING, $scheme->status);
        $this->assertSame(2, $scheme->includedItems()->count());
        $this->assertNull($scheme->adjusted_at);
    }

    public function test_preview_adjustment_excludes_rejected_months_and_percentage_applies_to_the_remaining_principal(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings);

        $this->as('admin.estate');
        $data = $this->postJson("/api/v1/payment-schemes/{$scheme->id}/preview-adjustment", [
            'adjustments' => ['discount_type' => 'percentage', 'discount_value' => 10, 'rejected_billing_ids' => [$billings[1]->id]],
        ])->assertOk()->json('data');

        $this->assertCount(2, $data['items']);
        $this->assertEqualsWithDelta(1000000, $data['original_principal'], 0.001);
        $this->assertEqualsWithDelta(100000, $data['principal_discount'], 0.001);
        $this->assertNotContains($billings[1]->id, collect($data['items'])->pluck('billing_id')->all());
        $this->assertSame(3, $scheme->fresh()->includedItems()->count());
    }

    private function overLimitScheme(): array
    {
        [$unit, $billings] = $this->unitWithArrears();
        // 50% is above the default Admin cap of 30%.
        $scheme = $this->submit($unit, $billings, ['discount_type' => 'percentage', 'discount_value' => 50]);

        return [$unit, $billings, $scheme];
    }

    public function test_admin_cannot_reject_or_approve_as_is_a_scheme_above_the_admin_limit(): void
    {
        [, , $scheme] = $this->overLimitScheme();

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/reject", ['notes' => 'Terlalu besar'])
            ->assertStatus(422)->assertJsonValidationErrors('status');
        $this->postJson("/api/v1/approval-requests/{$scheme->approvalRequest->id}/reject", ['notes' => 'Terlalu besar'])
            ->assertStatus(422);
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'adjustments' => ['discount_type' => 'percentage', 'discount_value' => 50],
        ])->assertStatus(422)->assertJsonValidationErrors('discount');

        $scheme->refresh();
        $this->assertSame(PaymentScheme::STATUS_PENDING, $scheme->status);
    }

    public function test_admin_can_bring_an_over_limit_scheme_down_to_the_limit_and_approve_it(): void
    {
        [$unit, $billings, $scheme] = $this->overLimitScheme();

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'adjustments' => ['discount_type' => 'percentage', 'discount_value' => 30],
        ])->assertOk();

        $scheme->refresh();
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $scheme->status);
        $this->assertEqualsWithDelta(450000, (float) $scheme->principal_discount, 0.001); // 30% x Rp1.500.000
        $this->assertEqualsWithDelta(750000, $scheme->requested_snapshot['principal_discount'], 0.001);
    }

    public function test_super_admin_sees_an_over_limit_scheme_right_away_and_can_approve_it_as_is(): void
    {
        [, , $scheme] = $this->overLimitScheme();

        // No hand-over step: it is in Super Admin's list the moment the loket submits it, flagged as above the Admin limit.
        $this->as('superadmin');
        $row = $this->getJson('/api/v1/payment-schemes?status=pending')->assertOk()->json('data.0');
        $this->assertSame($scheme->id, $row['id']);
        $this->assertTrue($row['exceeds_admin_limit']);
        $this->assertFalse($row['viewer_limited']);
        $this->assertEquals(30, $row['admin_limit_percent']);

        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'adjustments' => ['discount_type' => 'percentage', 'discount_value' => 50],
        ])->assertOk();

        $scheme->refresh();
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $scheme->status);
        $this->assertEqualsWithDelta(750000, (float) $scheme->principal_discount, 0.001);
        $this->assertSame(User::where('username', 'superadmin')->value('id'), $scheme->decided_by);
        $this->assertNull($scheme->adjusted_at);
    }

    public function test_super_admin_can_reject_an_over_limit_scheme(): void
    {
        [, , $scheme] = $this->overLimitScheme();

        $this->as('superadmin');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/reject", ['notes' => 'Tidak disetujui'])->assertOk();

        $this->assertSame(PaymentScheme::STATUS_REJECTED, $scheme->fresh()->status);
    }

    public function test_admin_can_still_reject_a_scheme_within_the_limit_and_there_is_no_forward_endpoint(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $within = $this->submit($unit, $billings, ['discount_type' => 'percentage', 'discount_value' => 10]);

        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$within->id}/escalate")->assertStatus(404);
        $this->postJson("/api/v1/payment-schemes/{$within->id}/reject", ['notes' => 'Tidak sesuai'])->assertOk();

        $this->assertSame(PaymentScheme::STATUS_REJECTED, $within->fresh()->status);
    }

    public function test_what_counts_as_over_the_limit_follows_the_super_admin_setting(): void
    {
        [, , $scheme] = $this->overLimitScheme();

        $this->as('admin.estate');
        $this->getJson("/api/v1/payment-schemes/{$scheme->id}")->assertOk()
            ->assertJsonPath('data.admin_limit_percent', 30)->assertJsonPath('data.exceeds_admin_limit', true);

        // Super Admin raises the cap to 60%: the same 50% request is now within the Admin's reach.
        \App\Models\DiscountSetting::current()->update(['maximum_admin_discount' => 60]);

        $list = $this->getJson('/api/v1/payment-schemes?status=pending')->assertOk()->json('data.0');
        $this->assertEquals(60, $list['admin_limit_percent']);
        $this->assertFalse($list['exceeds_admin_limit']);
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'adjustments' => ['discount_type' => 'percentage', 'discount_value' => 50],
        ])->assertOk();
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $scheme->fresh()->status);
    }

    private function approvedScheme(array $submitOverrides = []): array
    {
        [$unit, $billings] = $this->unitWithArrears();
        $scheme = $this->submit($unit, $billings, ['discount_value' => 100000, 'penalty_reduction' => 10000, ...$submitOverrides]);
        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve")->assertOk();

        return [$unit, $billings, $scheme->refresh()];
    }

    private function progress(PaymentScheme $scheme): array
    {
        return $this->getJson("/api/v1/payment-schemes/{$scheme->id}")->assertOk()->json('data');
    }

    public function test_loket_can_pay_an_approved_scheme_in_full_and_the_scheme_shows_it_as_paid(): void
    {
        [$unit, $billings, $scheme] = $this->approvedScheme();

        $this->as('loket');
        $before = $this->progress($scheme);
        $this->assertSame('unpaid', $before['payment_status']);
        $this->assertEqualsWithDelta((float) $scheme->final_amount, $before['outstanding_amount'], 0.001);
        $this->assertEqualsWithDelta(0, $before['paid_amount'], 0.001);

        // The loket screen loads the unit's outstanding bills; those in the scheme carry its id.
        $found = $this->getJson('/api/v1/payments/search?unit_id='.$unit->id)->assertOk()->json('data.billings');
        $this->assertSame([$scheme->id], collect($found)->pluck('payment_scheme_id')->unique()->values()->all());
        $this->assertEqualsWithDelta((float) $scheme->final_amount, collect($found)->sum('penalty_detail.total_outstanding'), 0.001);

        $receipt = $this->postJson('/api/v1/payments/process', [
            'unit_id' => $unit->id,
            'billing_ids' => $billings->pluck('id')->all(),
            'use_balance' => false,
            'payment_method_id' => 'C',
        ])->assertCreated()->json('data');

        // The customer pays exactly the scheme's final amount.
        $this->assertEqualsWithDelta((float) $scheme->final_amount, (float) $receipt['grand_total'], 0.001);
        $after = $this->progress($scheme);
        $this->assertSame('paid', $after['payment_status']);
        $this->assertEqualsWithDelta(0, $after['outstanding_amount'], 0.001);
        $this->assertEqualsWithDelta((float) $scheme->final_amount, $after['paid_amount'], 0.001);
        $this->assertSame(PaymentScheme::STATUS_APPROVED, $after['status']);
    }

    public function test_paying_only_some_of_a_scheme_is_refused_and_a_partial_amount_is_shown_as_partial(): void
    {
        [$unit, $billings, $scheme] = $this->approvedScheme();

        $this->as('loket');
        $this->postJson('/api/v1/payments/process', [
            'unit_id' => $unit->id, 'billing_ids' => [$billings->first()->id], 'payment_method_id' => 'C',
        ])->assertStatus(422)->assertJsonValidationErrors('billing_ids');

        // All scheme bills selected, but only part of the amount tendered.
        $this->postJson('/api/v1/payments/process', [
            'unit_id' => $unit->id, 'billing_ids' => $billings->pluck('id')->all(),
            'amount' => 500000, 'use_balance' => false, 'payment_method_id' => 'C',
        ])->assertCreated();

        $mid = $this->progress($scheme);
        $this->assertSame('partial', $mid['payment_status']);
        $this->assertEqualsWithDelta(500000, $mid['paid_amount'], 0.001);
        $this->assertEqualsWithDelta((float) $scheme->final_amount - 500000, $mid['outstanding_amount'], 0.001);

        // The rest is paid later from what the loket screen still lists (bills already settled are not offered again).
        $remaining = collect($this->getJson('/api/v1/payments/search?unit_id='.$unit->id)->assertOk()->json('data.billings'))->pluck('id')->all();
        $this->assertNotEmpty($remaining);
        $this->postJson('/api/v1/payments/process', [
            'unit_id' => $unit->id, 'billing_ids' => $remaining, 'use_balance' => false, 'payment_method_id' => 'C',
        ])->assertCreated();
        $this->assertSame('paid', $this->progress($scheme)['payment_status']);
    }

    public function test_only_approved_schemes_have_a_payment_status(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        $pending = $this->submit($unit, $billings);

        $this->as('loket');
        $row = $this->progress($pending);
        $this->assertNull($row['payment_status']);
        $this->assertNull($row['outstanding_amount']);
    }

    public function test_a_month_rejected_by_admin_is_not_part_of_the_scheme_payment_status(): void
    {
        [$unit, $billings] = $this->unitWithArrears();
        [$may, $june, $july] = $billings->all();
        $scheme = $this->submit($unit, $billings, ['discount_value' => 0]);
        $this->as('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$scheme->id}/approve", [
            'adjustments' => ['discount_type' => 'nominal', 'discount_value' => 0, 'rejected_billing_ids' => [$june->id]],
        ])->assertOk();

        $this->as('loket');
        $this->postJson('/api/v1/payments/process', [
            'unit_id' => $unit->id, 'billing_ids' => [$may->id, $july->id], 'use_balance' => false, 'payment_method_id' => 'C',
        ])->assertCreated();

        // June is still owed, but the scheme itself is fully paid.
        $this->assertSame('paid', $this->progress($scheme)['payment_status']);
        $this->assertSame(Billing::STATUS_UNPAID, $june->fresh()->status_id);
    }
}
