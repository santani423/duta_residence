<?php

namespace Database\Seeders;

use App\Models\Billing;
use App\Models\Customer;
use App\Models\Installment;
use App\Models\User;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    public function run(): void
    {
        $finance = User::where('username', 'finance')->first() ?: User::where('username', 'root')->first();
        $types = ['regular', 'security', 'cleaning', 'water', 'common-electricity', 'parking', 'maintenance', 'facility', 'special'];
        $skipCustomers = ['AL005'];

        Customer::with('cluster')
            ->where('status_id', '!=', 'RK')
            ->orderBy('id')
            ->chunk(100, function ($customers) use ($finance, $types, $skipCustomers) {
                foreach ($customers as $customer) {
                    if (in_array($customer->id, $skipCustomers, true)) {
                        continue;
                    }

                    for ($offset = 5; $offset >= 0; $offset--) {
                        $period = now()->subMonths($offset);
                        $amount = (float) $customer->cluster->monthly_rate + (($offset % 2) * 25000);
                        $status = '01';
                        $approvedAt = now()->subMonths($offset)->subDays(6);
                        $paidAt = null;
                        $penalty = 0;
                        $discount = $customer->is_discount_eligible ? 15000 : 0;
                        $notes = 'Approved dari demo seeder.';

                        if ($customer->id === 'AL001' || ($customer->cluster_id === 'OR' && $offset > 0) || (($offset + crc32($customer->id)) % 5 === 0 && ! in_array($customer->id, ['GA012', 'AL002', 'AL003', 'AL006', 'AL011'], true))) {
                            $status = '02';
                            $paidAt = now()->subMonths($offset)->addDays(2);
                        }

                        if ($customer->id === 'AL002' && $offset >= 2) {
                            $penalty = 35000;
                            $status = '01';
                            $paidAt = null;
                        }

                        if ($customer->id === 'AL011' && $offset === 1) {
                            $notes = 'Sebagian dibayar melalui installment.';
                        }

                        if ($customer->id === 'AL008' && $offset === 0) {
                            $approvedAt = null;
                            $notes = 'Draft/pending approval untuk simulasi.';
                        }

                        $billing = Billing::updateOrCreate(
                            ['customer_id' => $customer->id, 'year' => $period->year, 'month' => $period->month],
                            [
                                'amount' => $amount,
                                'penalty' => $penalty,
                                'discount' => $discount,
                                'status_id' => $status,
                                'is_penalty_eligible' => $customer->is_penalty_eligible,
                                'is_discount_eligible' => $customer->is_discount_eligible,
                                'billing_type' => $types[($offset + ord($customer->id[0])) % count($types)],
                                'approved_by' => $approvedAt ? $finance?->id : null,
                                'approved_at' => $approvedAt,
                                'approval_notes' => $notes,
                                'paid_at' => $paidAt,
                                'processed_by' => $paidAt ? $finance?->id : null,
                                'created_by' => $finance?->id,
                            ]
                        );

                        if ($customer->id === 'AL011' && $offset === 1) {
                            Installment::updateOrCreate(
                                ['customer_id' => $customer->id, 'payment_date' => now()->subDays(10)->toDateString()],
                                [
                                    'amount' => round(((float) $billing->amount + (float) $billing->penalty - (float) $billing->discount) / 2),
                                    'notes' => 'Pembayaran sebagian untuk invoice demo.',
                                    'allocated_to' => 'BIL-'.$billing->id,
                                    'created_by' => $finance?->id,
                                ]
                            );
                        }
                    }
                }
            });

        $ga = Customer::find('GA012');
        if ($ga) {
            $period = now();
            Billing::updateOrCreate(
                ['customer_id' => 'GA012', 'year' => $period->year, 'month' => $period->month],
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
