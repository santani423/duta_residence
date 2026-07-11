<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        $module = fake()->randomElement(['auth', 'residents', 'billings', 'payments', 'complaints', 'maintenance', 'documents', 'payment-settings']);

        return [
            'user_id' => null,
            'user_name' => fake()->name(),
            'user_role' => fake()->randomElement(['root', 'back_office', 'finance', 'cs', 'customer']),
            'activity' => $module.'_'.fake()->randomElement(['created', 'updated', 'viewed', 'downloaded']),
            'module' => $module,
            'action' => fake()->randomElement(['CREATE', 'UPDATE', 'READ', 'DELETE', 'VERIFY', 'REJECT', 'LOGIN']),
            'http_method' => fake()->randomElement(['GET', 'POST', 'PUT', 'DELETE']),
            'endpoint' => '/api/v1/'.$module,
            'entity_type' => Str::studly(Str::singular($module)),
            'entity_id' => (string) fake()->numberBetween(1, 9999),
            'old_data' => ['status' => 'before'],
            'new_data' => ['status' => 'after'],
            'changed_fields' => ['status'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Seeded Demo Browser',
            'request_id' => (string) Str::uuid(),
            'status' => fake()->randomElement(['success', 'failed']),
            'description' => fake()->sentence(),
        ];
    }
}
