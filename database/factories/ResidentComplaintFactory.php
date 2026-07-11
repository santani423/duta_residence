<?php

namespace Database\Factories;

use App\Models\ResidentComplaint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentComplaint>
 */
class ResidentComplaintFactory extends Factory
{
    protected $model = ResidentComplaint::class;

    public function definition(): array
    {
        return [
            'unit_id' => 'AL001',
            'title' => fake()->sentence(5),
            'category' => fake()->randomElement(['Keamanan', 'Kebersihan', 'Kebisingan', 'Fasilitas', 'Parkir', 'Tetangga', 'Lingkungan', 'Administrasi', 'Tagihan', 'Lainnya']),
            'priority' => fake()->randomElement(['low', 'normal', 'high', 'urgent']),
            'description' => fake()->paragraph(),
            'status' => 'submitted',
        ];
    }

    public function submitted(): static { return $this->state(fn () => ['status' => 'submitted']); }
    public function inProgress(): static { return $this->state(fn () => ['status' => 'in_progress']); }
    public function resolved(): static { return $this->state(fn () => ['status' => 'resolved', 'closed_at' => now()->subDay()]); }
    public function closed(): static { return $this->state(fn () => ['status' => 'closed', 'closed_at' => now()->subDay()]); }
    public function rejected(): static { return $this->state(fn () => ['status' => 'rejected']); }
}
