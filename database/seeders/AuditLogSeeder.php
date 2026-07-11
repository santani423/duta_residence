<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Billing;
use App\Models\Resident;
use App\Models\ResidentComplaint;
use App\Models\MaintenanceRequest;
use App\Models\PaymentGatewaySetting;
use App\Models\PaymentTransaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::with('roles')->get();
        $events = [
            ['auth', 'LOGIN', 'login_success'],
            ['auth', 'LOGIN_FAILED', 'login_failed'],
            ['auth', 'LOGOUT', 'logout'],
            ['residents', 'CREATE', 'resident_created'],
            ['residents', 'UPDATE', 'resident_updated'],
            ['units', 'CREATE', 'unit_created'],
            ['units', 'UPDATE', 'unit_updated'],
            ['users', 'ACTIVATE', 'user_activated'],
            ['users', 'DEACTIVATE', 'user_deactivated'],
            ['users', 'ROLE_UPDATE', 'role_changed'],
            ['billings', 'CREATE', 'billing_created'],
            ['payments', 'CREATE', 'payment_created'],
            ['payments', 'VERIFY', 'manual_payment_verified'],
            ['payments', 'REJECT', 'manual_payment_rejected'],
            ['complaints', 'CREATE', 'complaint_created'],
            ['complaints', 'UPDATE_STATUS', 'complaint_status_updated'],
            ['maintenance', 'CREATE', 'maintenance_created'],
            ['maintenance', 'ASSIGN', 'technician_assigned'],
            ['documents', 'UPLOAD', 'document_uploaded'],
            ['documents', 'DOWNLOAD', 'document_downloaded'],
            ['payment-settings', 'UPDATE', 'payment_gateway_settings_updated'],
        ];

        foreach (range(1, 420) as $i) {
            $user = $users[$i % max($users->count(), 1)] ?? null;
            [$module, $action, $activity] = $events[$i % count($events)];
            [$entityType, $entityId] = $this->entity($module);

            AuditLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'user_role' => $user?->roles?->pluck('name')->implode(','),
                'activity' => $activity,
                'module' => $module,
                'action' => $action,
                'http_method' => in_array($action, ['LOGIN', 'LOGIN_FAILED'], true) ? 'POST' : ['GET', 'POST', 'PUT', 'DELETE'][$i % 4],
                'endpoint' => '/api/v1/'.str_replace('_', '-', $module),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_data' => $action === 'CREATE' ? [] : ['status' => 'before', 'amount' => 100000 + $i],
                'new_data' => ['status' => 'after', 'amount' => 125000 + $i],
                'changed_fields' => ['status', 'amount'],
                'ip_address' => '10.20.'.($i % 20).'.'.(($i % 200) + 10),
                'user_agent' => 'Seeded Demo Browser / Windows',
                'request_id' => (string) Str::uuid(),
                'status' => $activity === 'login_failed' ? 'failed' : 'success',
                'description' => "Audit demo {$activity} untuk pengujian dashboard dan filter.",
                'created_at' => now()->subHours($i),
                'updated_at' => now()->subHours($i),
            ]);
        }

        $admin = User::where('username', 'superadmin')->first();
        $setting = PaymentGatewaySetting::first();
        AuditLog::create([
            'user_id' => $admin?->id,
            'user_name' => $admin?->name,
            'user_role' => 'super_admin',
            'activity' => 'payment_gateway_settings_updated',
            'module' => 'payment-settings',
            'action' => 'UPDATE',
            'http_method' => 'PUT',
            'endpoint' => '/api/v1/admin/settings/payment-gateway',
            'entity_type' => PaymentGatewaySetting::class,
            'entity_id' => (string) $setting?->id,
            'old_data' => ['active_gateway' => 'xendit'],
            'new_data' => ['active_gateway' => 'manual', 'mode' => 'sandbox'],
            'changed_fields' => ['active_gateway', 'mode'],
            'ip_address' => '10.10.0.5',
            'user_agent' => 'Seeded Demo Browser / Windows',
            'request_id' => (string) Str::uuid(),
            'status' => 'success',
            'description' => 'Perubahan payment gateway tercatat untuk skenario wajib.',
        ]);
    }

    private function entity(string $module): array
    {
        return match ($module) {
            'residents' => [Resident::class, (string) Resident::inRandomOrder()->value('id')],
            'units' => [Unit::class, (string) Unit::inRandomOrder()->value('id')],
            'billings' => [Billing::class, (string) Billing::inRandomOrder()->value('id')],
            'payments' => [PaymentTransaction::class, (string) PaymentTransaction::inRandomOrder()->value('id')],
            'complaints' => [ResidentComplaint::class, (string) ResidentComplaint::inRandomOrder()->value('id')],
            'maintenance' => [MaintenanceRequest::class, (string) MaintenanceRequest::inRandomOrder()->value('id')],
            'payment-settings' => [PaymentGatewaySetting::class, (string) PaymentGatewaySetting::query()->value('id')],
            default => [User::class, (string) User::inRandomOrder()->value('id')],
        };
    }
}
