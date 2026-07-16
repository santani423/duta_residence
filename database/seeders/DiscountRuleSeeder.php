<?php

namespace Database\Seeders;

use App\Models\DiscountRule;
use App\Models\User;
use Illuminate\Database\Seeder;

class DiscountRuleSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('username', 'root')->first();

        collect([
            ['name' => 'Diskon Karyawan Estate', 'type' => DiscountRule::TYPE_FIXED, 'value' => 15000],
            ['name' => 'Diskon Awal Bulan', 'type' => DiscountRule::TYPE_PERCENTAGE, 'value' => 5],
        ])->each(fn (array $rule) => DiscountRule::query()->firstOrCreate(
            ['name' => $rule['name']],
            [
                'type' => $rule['type'],
                'value' => $rule['value'],
                'is_active' => true,
                'created_by' => $creator?->id,
            ]
        ));
    }
}
