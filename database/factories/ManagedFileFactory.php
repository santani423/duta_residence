<?php

namespace Database\Factories;

use App\Models\ManagedFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManagedFile>
 */
class ManagedFileFactory extends Factory
{
    protected $model = ManagedFile::class;

    public function definition(): array
    {
        $extension = fake()->randomElement(['pdf', 'jpg', 'png']);

        return [
            'original_filename' => fake()->slug().'.'.$extension,
            'stored_filename' => fake()->uuid().'.'.$extension,
            'path' => 'dummy/'.fake()->slug().'.'.$extension,
            'disk' => 'public',
            'mime_type' => $extension === 'pdf' ? 'application/pdf' : 'image/'.$extension,
            'extension' => $extension,
            'size' => fake()->numberBetween(50_000, 2_500_000),
            'uploaded_by' => null,
            'entity_type' => null,
            'entity_id' => null,
        ];
    }
}
