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
                DiscountRuleSeeder::class,
                ClusterMapComponentTypeSeeder::class,
                PaymentSettingSeeder::class,
                ResidentSeeder::class,
                UnitSeeder::class,
                BillingSeeder::class,
                PaymentSeeder::class,
                ComplaintSeeder::class,
                CollectorSeeder::class,
                MaintenanceSeeder::class,
                NotificationSeeder::class,
                DocumentSeeder::class,
                AuditLogSeeder::class,
                HelpCenterSeeder::class,
                LandingCmsSeeder::class,
            ]);
        });
    }
}
