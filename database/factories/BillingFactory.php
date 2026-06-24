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
            'customer_id' => 'AL001',
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
        return $this->state(fn () => [
            'status_id' => '02',
            'paid_at' => now()->subDays(fake()->numberBetween(1, 45)),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn () => ['status_id' => '01', 'approved_at' => now()->subDays(3), 'paid_at' => null]);
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status_id' => '01', 'approved_at' => null, 'approval_notes' => 'Menunggu approval finance.']);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status_id' => '01',
            'approved_at' => now()->subMonths(2),
            'year' => now()->subMonths(2)->year,
            'month' => now()->subMonths(2)->month,
            'penalty' => 25000,
        ]);
    }

    public function partiallyPaid(): static
    {
        return $this->state(fn () => [
            'status_id' => '01',
            'approved_at' => now()->subDays(20),
            'approval_notes' => 'Sebagian dibayar melalui cicilan.',
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status_id' => '01',
            'approved_at' => null,
            'approval_notes' => 'Dibatalkan pada skenario demo.',
            'amount' => 0,
        ]);
    }
}
