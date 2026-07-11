<?php

namespace Database\Factories;

use App\Models\Resident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Resident>
 */
class ResidentFactory extends Factory
{
    protected $model = Resident::class;

    public function definition(): array
    {
        return [
            'id' => 'RS'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'name' => fake()->name(),
            'phone' => '08'.fake()->unique()->numerify('##########'),
            'telephone' => fake()->optional(0.35)->numerify('021#######'),
            'id_card_address' => fake()->streetAddress(),
            'district_id' => '367101',
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
