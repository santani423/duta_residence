<?php

namespace Database\Seeders;

use App\Models\PenaltyRule;
use App\Models\User;
use Illuminate\Database\Seeder;

class PenaltyRuleSeeder extends Seeder
{
    public function run(): void
    {
        $creator = User::where('username', 'root')->first();

        collect([
            [
                'name' => 'Bulan berjalan',
                'minimum_overdue_month' => 0,
                'maximum_overdue_month' => 0,
                'penalty_amount' => 0,
            ],
            [
                'name' => 'Tunggakan 1-2 bulan',
                'minimum_overdue_month' => 1,
                'maximum_overdue_month' => 2,
                'penalty_amount' => 15000,
            ],
            [
                'name' => 'Tunggakan 3 bulan atau lebih',
                'minimum_overdue_month' => 3,
                'maximum_overdue_month' => null,
                'penalty_amount' => 30000,
            ],
        ])->each(fn (array $rule) => PenaltyRule::query()->firstOrCreate(
            [
                'cluster_id' => null,
                'minimum_overdue_month' => $rule['minimum_overdue_month'],
            ],
            [
                'name' => $rule['name'],
                'maximum_overdue_month' => $rule['maximum_overdue_month'],
                'penalty_amount' => $rule['penalty_amount'],
                'is_active' => true,
                'effective_start_date' => '2020-01-01',
                'effective_end_date' => null,
                'created_by' => $creator?->id,
            ]
        ));
    }
}
