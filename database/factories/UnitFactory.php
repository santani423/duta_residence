<?php

namespace Database\Factories;

use App\Models\Resident;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $type = fake()->randomElement([
            ['code' => 'B', 'building' => 36, 'land' => 72],
            ['code' => 'B', 'building' => 45, 'land' => 90],
            ['code' => 'B', 'building' => 60, 'land' => 120],
            ['code' => 'B', 'building' => 90, 'land' => 160],
            ['code' => 'B', 'building' => 120, 'land' => 200],
        ]);

        return [
            'id' => strtoupper(fake()->bothify('??###')),
            'resident_id' => Resident::factory(),
            'cluster_id' => 'AL',
            'block' => fake()->randomElement(['A', 'B', 'C', 'D', 'E']),
            'lot_number' => str_pad((string) fake()->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'property_type_id' => $type['code'],
            'building_area' => $type['building'],
            'land_area' => $type['land'],
            'handover_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
            'occupancy_id' => '1',
            'status_id' => 'AK',
            'is_penalty_eligible' => true,
            'is_discount_eligible' => fake()->boolean(15),
            'notes' => fake()->sentence(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status_id' => 'TA', 'occupancy_id' => '2']);
    }

    public function vacant(): static
    {
        return $this->state(fn () => ['status_id' => 'RK', 'occupancy_id' => '2', 'notes' => 'Unit kosong tersedia untuk dihuni.']);
    }

    public function renovated(): static
    {
        return $this->state(fn () => ['status_id' => 'RK', 'occupancy_id' => '2', 'notes' => 'Unit sedang direnovasi.']);
    }
}
