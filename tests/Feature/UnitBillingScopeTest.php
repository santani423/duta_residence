<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\Receipt;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\EstateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class UnitBillingScopeTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Unit $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstateSeeder::class);

        $this->unit = Unit::factory()->create(['cluster_id' => 'AL']);
        $this->other = Unit::factory()->create(['cluster_id' => 'AL']);

        foreach (['billings.view', 'payments.view'] as $permission) {
            Permission::findOrCreate($permission);
        }
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo(['billings.view', 'payments.view']);
        Sanctum::actingAs($user);
    }

    private function seedBillings(): void
    {
        // Lintas tahun & status untuk unit yang dipilih.
        Billing::factory()->create(['unit_id' => $this->unit->id, 'year' => 2023, 'month' => 3, 'amount' => 100000, 'status_id' => Billing::STATUS_UNPAID]);
        Billing::factory()->create(['unit_id' => $this->unit->id, 'year' => 2024, 'month' => 6, 'amount' => 100000, 'status_id' => Billing::STATUS_PARTIAL, 'principal_paid' => 40000]);
        Billing::factory()->create(['unit_id' => $this->unit->id, 'year' => 2025, 'month' => 1, 'amount' => 100000, 'status_id' => Billing::STATUS_PAID, 'principal_paid' => 100000]);
        Billing::factory()->create(['unit_id' => $this->unit->id, 'year' => 2026, 'month' => 2, 'amount' => 100000, 'status_id' => Billing::STATUS_UNPAID]);
        Billing::factory()->create(['unit_id' => $this->unit->id, 'year' => 2026, 'month' => 3, 'amount' => 100000, 'status_id' => Billing::STATUS_CANCELLED]);
        // Milik unit lain - tidak boleh bocor.
        Billing::factory()->create(['unit_id' => $this->other->id, 'year' => 2023, 'month' => 3, 'amount' => 999000, 'status_id' => Billing::STATUS_UNPAID]);
    }

    public function test_outstanding_list_includes_all_years_and_only_unpaid_or_partial(): void
    {
        $this->seedBillings();

        $rows = $this->getJson("/api/v1/billings?unit_id={$this->unit->id}&outstanding=1&per_page=50")->assertOk()->json('data');

        $this->assertEqualsCanonicalizing(
            [[2023, 3], [2024, 6], [2026, 2]],
            collect($rows)->map(fn ($row) => [$row['year'], $row['month']])->all(),
        );
        $this->assertSame([$this->unit->id], collect($rows)->pluck('unit_id')->unique()->values()->all());
    }

    public function test_unit_filter_ignores_letter_case_and_surrounding_spaces(): void
    {
        $this->seedBillings();
        $typed = rawurlencode(' '.strtolower($this->unit->id).' ');

        $rows = $this->getJson("/api/v1/billings?unit_id={$typed}&outstanding=1&per_page=50")->assertOk()->json('data');

        $this->assertCount(3, $rows);
        $this->assertSame([$this->unit->id], collect($rows)->pluck('unit_id')->unique()->values()->all());
    }

    public function test_blank_unit_filter_lists_every_unit(): void
    {
        $this->seedBillings();

        foreach (['', '%20%20'] as $blank) {
            $rows = $this->getJson("/api/v1/billings?unit_id={$blank}&outstanding=1&per_page=50")->assertOk()->json('data');

            $this->assertCount(4, $rows);
            $this->assertEqualsCanonicalizing([$this->unit->id, $this->other->id], collect($rows)->pluck('unit_id')->unique()->values()->all());
        }
    }

    public function test_history_list_includes_every_status_and_year_for_the_unit_only(): void
    {
        $this->seedBillings();

        $rows = $this->getJson("/api/v1/billings?unit_id={$this->unit->id}&per_page=50")->assertOk()->json('data');

        $this->assertCount(5, $rows);
        $this->assertEqualsCanonicalizing(['01', '02', '03', '04'], collect($rows)->pluck('status_id')->unique()->values()->all());
        $this->assertSame([$this->unit->id], collect($rows)->pluck('unit_id')->unique()->values()->all());

        $paid = collect($rows)->firstWhere('status_id', '02');
        $partial = collect($rows)->firstWhere('status_id', '03');
        $this->assertEquals(100000, $paid['penalty_detail']['total_paid']);
        $this->assertEquals(0, $paid['penalty_detail']['outstanding_principal']);
        $this->assertEquals(40000, $partial['penalty_detail']['total_paid']);
        $this->assertEquals(60000, $partial['penalty_detail']['outstanding_principal']);
    }

    public function test_summary_totals_span_all_years_and_follow_unit_filter(): void
    {
        $this->seedBillings();

        $data = $this->getJson("/api/v1/billings/summary?unit_id={$this->unit->id}&outstanding=1")->assertOk()->json('data');

        $this->assertSame(3, $data['invoice_count']);
        $this->assertEquals(100000 + 60000 + 100000, $data['total_principal_outstanding']);
        $this->assertSame(1, $data['unit_count']);
    }

    public function test_outstanding_filter_is_not_year_restricted_without_explicit_year(): void
    {
        $this->seedBillings();

        $this->getJson('/api/v1/billings?outstanding=1&per_page=50')->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/v1/billings?outstanding=1&year=2023&per_page=50')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_receipt_and_transaction_lists_are_scoped_to_unit(): void
    {
        foreach ([$this->unit, $this->other] as $unit) {
            Receipt::create([
                'number' => 'KW-'.$unit->id, 'unit_id' => $unit->id, 'transaction_date' => now(),
                'resident_name' => 'X', 'cluster_name' => 'AL', 'block' => 'A', 'lot_number' => '1',
                'total_billing' => 1, 'total_penalty' => 0, 'grand_total' => 1, 'billing_count' => 1,
            ]);
        }

        $rows = $this->getJson("/api/v1/payments/receipts?unit_id={$this->unit->id}")->assertOk()->json('data');

        $this->assertSame([$this->unit->id], collect($rows)->pluck('unit_id')->all());
        $this->getJson("/api/v1/payments/gateway/transactions?unit_id={$this->unit->id}")->assertOk();
    }
}
