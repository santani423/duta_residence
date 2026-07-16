<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\Unit;
use App\Models\User;
use App\Services\PenaltyService;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $finance = User::where('username', 'finance')->first() ?: User::where('username', 'root')->first();
        $penaltyService = app(PenaltyService::class);
        $types = ['regular', 'security', 'cleaning', 'water', 'common-electricity', 'parking', 'maintenance', 'facility', 'special'];
        $skipUnits = ['AL005'];

        Unit::with('cluster')
            ->where('status_id', '!=', 'RK')
            ->orderBy('id')
            ->chunk(100, function ($units) use ($finance, $penaltyService, $types, $skipUnits) {
                foreach ($units as $unit) {
                    if (in_array($unit->id, $skipUnits, true)) {
                        continue;
                    }

                    for ($offset = 5; $offset >= 0; $offset--) {
                        $period = now()->subMonths($offset);
                        $amount = (float) $unit->cluster->monthly_rate + (($offset % 2) * 25000);
                        $status = Billing::STATUS_UNPAID;
                        $approvedAt = now()->subMonths($offset)->subDays(6);
                        $paidAt = null;
                        $principalPaid = 0;
                        $penaltyPaid = 0;
                        $discount = $unit->is_discount_eligible ? 15000 : 0;
                        $notes = 'Approved dari demo seeder.';
                        $cancelledAt = null;
                        $cancellationReason = null;

                        if ($unit->id === 'AL001' || ($unit->cluster_id === 'OR' && $offset > 0) || (($offset + crc32($unit->id)) % 5 === 0 && ! in_array($unit->id, ['GA012', 'AL002', 'AL003', 'AL006', 'AL010', 'AL011'], true))) {
                            $status = Billing::STATUS_PAID;
                            $paidAt = now()->subMonths($offset)->addDays(2);
                        }

                        // AL011 bulan sebelumnya: skenario pembayaran sebagian (baru dibayar pokoknya).
                        if ($unit->id === 'AL011' && $offset === 1) {
                            $status = Billing::STATUS_PARTIAL;
                            $principalPaid = round($amount / 2, 2);
                            $notes = 'Dibayar sebagian - sisa pokok dan denda masih tertunggak.';
                        }

                        // AL010 skenario tagihan dibatalkan (mis. unit pindah/renovasi sebelum jatuh tempo).
                        if ($unit->id === 'AL010' && $offset === 2) {
                            $status = Billing::STATUS_CANCELLED;
                            $cancelledAt = now()->subMonths($offset)->addDays(3);
                            $cancellationReason = 'Unit dalam renovasi, tagihan dibatalkan oleh admin estate.';
                            $notes = 'Dibatalkan untuk skenario demo.';
                        }

                        if ($unit->id === 'AL008' && $offset === 0) {
                            $approvedAt = null;
                            $notes = 'Draft/pending approval untuk simulasi.';
                        }

                        $penalty = 0;
                        if ($status === Billing::STATUS_PAID) {
                            $principalPaid = $amount - $discount;

                            // Hitung denda seolah tagihan masih outstanding pada tanggal pembayaran,
                            // lalu bekukan hasilnya - meniru urutan yang dipakai PaymentService::process().
                            $calcBilling = (new Billing([
                                'unit_id' => $unit->id, 'year' => $period->year, 'month' => $period->month,
                                'amount' => $amount, 'discount' => $discount, 'is_penalty_eligible' => $unit->is_penalty_eligible,
                                'status_id' => Billing::STATUS_UNPAID,
                            ]))->setRelation('unit', $unit);
                            $penalty = $penaltyService->calculatePenalty($calcBilling, $paidAt);
                            $penaltyPaid = $penalty;
                        }

                        $billing = Billing::updateOrCreate(
                            ['unit_id' => $unit->id, 'year' => $period->year, 'month' => $period->month],
                            [
                                'amount' => $amount,
                                'principal_paid' => $principalPaid,
                                'penalty' => $penalty,
                                'penalty_paid' => $penaltyPaid,
                                'discount' => $discount,
                                'status_id' => $status,
                                'is_penalty_eligible' => $unit->is_penalty_eligible,
                                'is_discount_eligible' => $unit->is_discount_eligible,
                                'billing_type' => $types[($offset + ord($unit->id[0])) % count($types)],
                                'approved_by' => $approvedAt ? $finance?->id : null,
                                'approved_at' => $approvedAt,
                                'approval_notes' => $notes,
                                'paid_at' => $paidAt,
                                'processed_by' => $paidAt ? $finance?->id : null,
                                'cancelled_at' => $cancelledAt,
                                'cancelled_by' => $cancelledAt ? $finance?->id : null,
                                'cancellation_reason' => $cancellationReason,
                                'created_by' => $finance?->id,
                            ]
                        );
                    }
                }
            });

        $ga = Unit::find('GA012');
        if ($ga) {
            $period = now();
            Billing::updateOrCreate(
                ['unit_id' => 'GA012', 'year' => $period->year, 'month' => $period->month],
                [
                    'amount' => (float) $ga->cluster->monthly_rate,
                    'penalty' => 0,
                    'discount' => 0,
                    'status_id' => '01',
                    'billing_type' => 'regular',
                    'approved_by' => $finance?->id,
                    'approved_at' => now()->subDays(5),
                    'approval_notes' => 'Tagihan wajib untuk PaymentFlowTest.',
                    'created_by' => $finance?->id,
                ]
            );
        }
    }
}
