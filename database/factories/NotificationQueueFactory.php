<?php

namespace Database\Factories;

use App\Models\NotificationQueue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationQueue>
 */
class NotificationQueueFactory extends Factory
{
    protected $model = NotificationQueue::class;

    public function definition(): array
    {
        return [
            'customer_id' => null,
            'user_id' => null,
            'type' => fake()->randomElement(['billing_new', 'billing_due_soon', 'payment_success', 'complaint_updated', 'maintenance_scheduled', 'announcement_estate']),
            'channel' => fake()->randomElement(['in_app', 'email', 'whatsapp']),
            'recipient' => fake()->safeEmail(),
            'message' => fake()->sentence(12),
            'read_status' => fake()->randomElement(['read', 'unread']),
            'status' => fake()->randomElement(['pending', 'sent', 'failed']),
            'attempts' => fake()->numberBetween(0, 3),
            'sent_at' => fake()->optional(0.7)->dateTimeBetween('-3 months', 'now'),
        ];
    }

    public function unread(): static
    {
        return $this->state(fn () => ['read_status' => 'unread']);
    }
}
