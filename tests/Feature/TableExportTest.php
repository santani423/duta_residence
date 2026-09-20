<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\PaymentScheme;
use App\Models\Resident;
use App\Models\Unit;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\TableExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TableExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function actAs(string $username): User
    {
        $user = User::where('username', $username)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    /** A unit with an approved, partly paid payment scheme, so every dataset has real rows to print. */
    private function unitWithScheme(): Unit
    {
        $unit = Unit::factory()->create(['resident_id' => Resident::factory()->create()->id, 'is_penalty_eligible' => true]);
        $finance = User::where('username', 'finance')->firstOrFail();
        $billings = collect([5, 4, 3])->map(function (int $offset) use ($unit, $finance) {
            $period = now()->startOfMonth()->subMonths($offset);

            return Billing::query()->create([
                'unit_id' => $unit->id, 'year' => $period->year, 'month' => $period->month, 'amount' => 500000,
                'status_id' => Billing::STATUS_UNPAID, 'is_penalty_eligible' => true, 'billing_type' => 'regular',
                'approved_by' => $finance->id, 'approved_at' => $period->copy()->addDays(2), 'created_by' => $finance->id,
            ]);
        });

        $this->actAs('loket');
        $id = $this->postJson('/api/v1/payment-schemes', [
            'unit_id' => $unit->id, 'billing_ids' => $billings->pluck('id')->all(), 'discount_type' => 'nominal',
            'discount_value' => 100000, 'penalty_reductions' => [$billings[0]->id => 10000], 'reason' => 'Uji export',
        ])->assertCreated()->json('data.id');
        $this->actAs('admin.estate');
        $this->postJson("/api/v1/payment-schemes/{$id}/approve")->assertOk();
        app(PaymentService::class)->process($unit, $billings->pluck('id')->all(), ['amount' => 400000, 'use_balance' => false], User::where('username', 'loket')->value('id'));

        return $unit;
    }

    private function datasetRequests(Unit $unit): array
    {
        return [
            'payment-schemes' => [],
            'reversals' => [],
            'installments' => [],
            'receivables' => [],
            'balance-reconciliation' => [],
            'balance-ledger' => ['unit_id' => $unit->id],
            'unit-outstanding' => ['unit_id' => $unit->id],
            'unit-billing-history' => ['unit_id' => $unit->id],
            'report-monthly' => ['year' => now()->year, 'month' => now()->month],
            'report-daily' => ['date' => now()->toDateString()],
            'report-cashier' => ['date' => now()->toDateString()],
        ];
    }

    public function test_every_dataset_downloads_a_real_pdf(): void
    {
        $unit = $this->unitWithScheme();
        $this->actAs('superadmin');

        foreach ($this->datasetRequests($unit) as $dataset => $query) {
            $response = $this->get("/api/v1/documents/tables/{$dataset}?".http_build_query($query));

            $response->assertOk();
            $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'), $dataset);
            $this->assertStringStartsWith('%PDF', $response->getContent(), $dataset);
        }
    }

    public function test_every_dataset_is_well_formed_rows_match_columns_and_footers_span_them(): void
    {
        $unit = $this->unitWithScheme();
        $user = $this->actAs('superadmin');
        $service = app(TableExportService::class);

        foreach ($this->datasetRequests($unit) as $dataset => $query) {
            $request = Request::create('/x', 'GET', $query);
            $request->setUserResolver(fn () => $user);
            $table = $service->build($dataset, $request);

            $this->assertNotEmpty($table['title'], $dataset);
            $this->assertNotEmpty($table['columns'], $dataset);
            foreach ($table['rows'] as $row) {
                $this->assertCount(count($table['columns']), $row, "{$dataset}: a row has the wrong number of cells");
                foreach ($row as $cell) {
                    $this->assertIsScalar($cell, $dataset);
                }
            }
            foreach ($table['footer'] as $footerRow) {
                $this->assertSame(count($table['columns']), array_sum(array_map(fn ($c) => $c['colspan'] ?? 1, $footerRow)), "{$dataset}: footer does not span the table");
            }
        }
    }

    public function test_payment_scheme_pdf_content_matches_the_list(): void
    {
        $unit = $this->unitWithScheme();
        $user = $this->actAs('superadmin');
        $scheme = PaymentScheme::query()->where('unit_id', $unit->id)->firstOrFail();

        $request = Request::create('/x', 'GET', ['unit_id' => $unit->id]);
        $request->setUserResolver(fn () => $user);
        $table = app(TableExportService::class)->build('payment-schemes', $request);

        $this->assertCount(1, $table['rows']);
        [, $unitId, , $months, $principal, $discount, $reduction, $total, $status, $payment] = $table['rows'][0];
        $this->assertSame($unit->id, $unitId);
        $this->assertSame('3 bulan', $months);
        $this->assertSame('Rp 1.500.000', $principal);
        $this->assertSame('Rp 100.000 (6,67%)', $discount);
        $this->assertSame('Rp 10.000', $reduction);
        $this->assertSame('Rp '.number_format((float) $scheme->final_amount, 0, ',', '.'), $total);
        $this->assertSame('Disetujui', $status);
        $this->assertSame('Dibayar sebagian (sisa Rp '.number_format((float) $scheme->final_amount - 400000, 0, ',', '.').')', $payment);
        // Footer sums the approved schemes only.
        $this->assertSame('Rp '.number_format((float) $scheme->final_amount, 0, ',', '.'), $table['footer'][0][4]['text']);
    }

    public function test_filters_narrow_the_pdf_like_they_do_the_list(): void
    {
        $unit = $this->unitWithScheme();
        $user = $this->actAs('superadmin');
        $service = app(TableExportService::class);
        $build = function (array $query) use ($service, $user) {
            $request = Request::create('/x', 'GET', $query);
            $request->setUserResolver(fn () => $user);

            return $service->build('payment-schemes', $request);
        };

        $this->assertCount(1, $build(['status' => 'approved', 'unit_id' => $unit->id])['rows']);
        $this->assertCount(0, $build(['status' => 'rejected', 'unit_id' => $unit->id])['rows']);
        $this->assertCount(0, $build(['search' => 'tidak-ada-orang-ini'])['rows']);
        $this->assertStringContainsString('Status: Ditolak', implode(' ', $build(['status' => 'rejected'])['meta']));
    }

    public function test_unit_datasets_need_a_unit_and_unknown_datasets_are_not_found(): void
    {
        $this->actAs('superadmin');

        $this->getJson('/api/v1/documents/tables/unit-outstanding')->assertStatus(422)->assertJsonValidationErrors('unit_id');
        $this->getJson('/api/v1/documents/tables/unit-billing-history')->assertStatus(422)->assertJsonValidationErrors('unit_id');
        $this->getJson('/api/v1/documents/tables/balance-ledger')->assertStatus(422)->assertJsonValidationErrors('unit_id');
        $this->getJson('/api/v1/documents/tables/tidak-ada')->assertStatus(404);
    }

    public function test_access_needs_documents_permission_and_the_module_permission(): void
    {
        // No documents.generate at all.
        $this->actAs('cs');
        $this->getJson('/api/v1/documents/tables/payment-schemes')->assertForbidden();

        // Has documents.generate (and billings) but not payment schemes.
        $this->actAs('ops1');
        $this->getJson('/api/v1/documents/tables/payment-schemes')->assertForbidden();
        $this->getJson('/api/v1/documents/tables/reversals')->assertForbidden();

        // Loket may print schemes and reversals, but not the finance reports.
        $this->actAs('loket');
        $this->get('/api/v1/documents/tables/payment-schemes')->assertOk();
        $this->get('/api/v1/documents/tables/reversals')->assertOk();
        $this->getJson('/api/v1/documents/tables/report-monthly')->assertForbidden();
    }

    public function test_resident_filter_narrows_receipts_and_transactions_pdfs(): void
    {
        $unit = $this->unitWithScheme();
        $this->actAs('superadmin');

        $this->get('/api/v1/documents/payment-receipts?resident_id='.$unit->resident_id)->assertOk();
        $this->get('/api/v1/documents/payment-transactions?resident_id='.$unit->resident_id)->assertOk();
        $this->get('/api/v1/documents/payment-receipts?resident_id=NOPE0000')->assertOk();
    }

    public function test_receipts_excel_only_contains_the_selected_numbers(): void
    {
        $unit = $this->unitWithScheme();
        $this->actAs('superadmin');

        foreach (['KW-SEL-1', 'KW-SEL-2', 'KW-SEL-3'] as $number) {
            \App\Models\Receipt::create([
                'number' => $number,
                'unit_id' => $unit->id,
                'transaction_date' => now(),
                'resident_name' => 'Penghuni '.$number,
                'cluster_name' => 'Cluster A',
                'block' => 'A',
                'lot_number' => '1',
                'total_billing' => 100000,
                'total_penalty' => 0,
                'billing_count' => 1,
                'billing_periods' => '2026-09',
                'grand_total' => 100000,
                'status' => 'success',
            ]);
        }

        $csv = $this->get('/api/v1/documents/payment-receipts-excel?numbers[]=KW-SEL-1&numbers[]=KW-SEL-3')
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('KW-SEL-1', $csv);
        $this->assertStringContainsString('KW-SEL-3', $csv);
        $this->assertStringNotContainsString('KW-SEL-2', $csv);

        $all = $this->get('/api/v1/documents/payment-receipts-excel')->streamedContent();
        $this->assertStringContainsString('KW-SEL-2', $all);
    }
}
