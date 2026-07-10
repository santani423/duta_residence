<?php

namespace Database\Factories;

use App\Models\MaintenanceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceRequest>
 */
class MaintenanceRequestFactory extends Factory
{
    protected $model = MaintenanceRequest::class;

    public function definition(): array
    {
        return [
            'unit_id' => 'AL001',
            'unit_label' => 'Cluster Alamanda A/001',
            'category' => fake()->randomElement(['Plumbing', 'Electrical', 'AC', 'Atap bocor', 'Pintu dan jendela', 'Jalan lingkungan', 'Lampu jalan', 'Saluran air', 'Fasilitas umum', 'Lainnya']),
            'description' => fake()->paragraph(),
            'urgency' => fake()->randomElement(['low', 'normal', 'high', 'emergency']),
            'preferred_schedule' => now()->addDays(fake()->numberBetween(1, 14)),
            'status' => 'submitted',
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['status' => 'scheduled', 'technician_schedule' => now()->addDays(2), 'technician_name' => 'Teknisi Demo']);
    }

    public function assigned(): static
    {
        return $this->state(fn () => ['status' => 'assigned', 'technician_schedule' => now()->addDay(), 'technician_name' => 'Teknisi Demo']);
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => 'in_progress', 'technician_name' => 'Teknisi Demo']);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'technician_name' => 'Teknisi Demo',
            'technician_notes' => 'Pekerjaan selesai dan area sudah dibersihkan.',
            'completed_at' => now()->subDay(),
            'rating' => fake()->numberBetween(4, 5),
            'rating_notes' => 'Pengerjaan rapi.',
        ]);
    }
}
