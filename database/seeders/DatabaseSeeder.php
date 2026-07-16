<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $this->call([
                RolePermissionSeeder::class,
                EstateSeeder::class,
                AdminUserSeeder::class,
                PenaltyRuleSeeder::class,
                PaymentSettingSeeder::class,
                ResidentSeeder::class,
                UnitSeeder::class,
                BillingSeeder::class,
                PaymentSeeder::class,
                ComplaintSeeder::class,
                MaintenanceSeeder::class,
                NotificationSeeder::class,
                DocumentSeeder::class,
                AuditLogSeeder::class,
                HelpCenterSeeder::class,
            ]);
        });
    }
}
