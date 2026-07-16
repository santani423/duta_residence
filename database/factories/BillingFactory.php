<?php

namespace Database\Factories;

use App\Models\Billing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Billing>
 */
class BillingFactory extends Factory
{
    protected $model = Billing::class;

    public function definition(): array
    {
        $period = fake()->dateTimeBetween('-10 months', '+1 month');
        $amount = fake()->randomElement([300000, 325000, 350000, 375000, 400000, 450000, 550000]);

        return [
            'unit_id' => 'AL001',
            'year' => (int) $period->format('Y'),
            'month' => (int) $period->format('m'),
            'amount' => $amount,
            'penalty' => 0,
            'discount' => 0,
            'status_id' => '01',
            'is_penalty_eligible' => true,
            'is_discount_eligible' => false,
            'billing_type' => fake()->randomElement(['regular', 'security', 'cleaning', 'water', 'parking', 'maintenance', 'special']),
            'approved_at' => now()->subDays(fake()->numberBetween(3, 60)),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => Billing::STATUS_PAID,
            'principal_paid' => (float) ($attributes['amount'] ?? 0) - (float) ($attributes['discount'] ?? 0),
            'penalty_paid' => (float) ($attributes['penalty'] ?? 0),
            'paid_at' => now()->subDays(fake()->numberBetween(1, 45)),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn () => ['status_id' => Billing::STATUS_UNPAID, 'approved_at' => now()->subDays(3), 'paid_at' => null]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status_id' => Billing::STATUS_UNPAID, 'approved_at' => null, 'approval_notes' => 'Menunggu approval finance.']);
    }

    /**
     * 2 bulan menunggak - jatuh pada tier denda Rp15.000 (1-2 bulan) secara dinamis.
     */
    public function overdue(): static
    {
        return $this->state(fn () => [
            'status_id' => Billing::STATUS_UNPAID,
            'approved_at' => now()->subMonths(2),
            'year' => now()->subMonths(2)->year,
            'month' => now()->subMonths(2)->month,
        ]);
    }

    public function partiallyPaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status_id' => Billing::STATUS_PARTIAL,
            'principal_paid' => round((float) ($attributes['amount'] ?? 0) / 2, 2),
            'approved_at' => now()->subDays(20),
            'approval_notes' => 'Sebagian dibayar - sisa pokok dan denda masih tertunggak.',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status_id' => Billing::STATUS_CANCELLED,
            'approved_at' => null,
            'approval_notes' => 'Dibatalkan pada skenario demo.',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Dibatalkan pada skenario demo.',
        ]);
    }
}
