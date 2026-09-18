<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seeding runs against a remote host (see .env DB_HOST), so a single
        // multi-minute transaction spanning ~20 seeders - some doing hundreds of
        // individual round-trip queries, one (LandingCmsSeeder) even making outbound
        // HTTP calls - is exactly what a shared-hosting MySQL server kills for being
        // idle/long-running ("MySQL server has gone away", unrecoverable mid-rollback).
        // Every seeder below is already idempotent (updateOrCreate/firstOrCreate), so
        // cross-seeder atomicity isn't needed; each can commit its own work as it goes.
        try {
            DB::statement('SET SESSION wait_timeout=28800, SESSION net_read_timeout=120, SESSION net_write_timeout=120');
        } catch (\Throwable) {
            // Some hosts restrict SET SESSION for non-SUPER users - seeding still
            // works without it, just with the provider's default timeouts.
        }

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
            SupervisorSeeder::class,
            MaintenanceSeeder::class,
            NotificationSeeder::class,
            DocumentSeeder::class,
            AuditLogSeeder::class,
            HelpCenterSeeder::class,
            LandingCmsSeeder::class,
        ]);
    }
}
