<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['Root Administrator', 'root', 'root@grandduta.test', 'root'],
            ['Super Admin', 'superadmin', 'superadmin@example.com', 'super_admin'],
            ['Estate Admin Utama', 'admin.estate', 'admin.estate@example.com', 'admin_estate'],
            ['Estate Admin Operasional', 'admin.estate2', 'admin.estate2@example.com', 'admin_estate'],
            ['Finance Grand Duta', 'finance', 'finance@example.com', 'finance'],
            ['Finance Collection', 'finance2', 'finance2@example.com', 'finance'],
            ['Property Manager', 'property.manager', 'property.manager@example.com', 'property_manager'],
            ['Property Manager Timur', 'property.manager2', 'property.manager2@example.com', 'property_manager'],
            ['Loket Kasir', 'loket', 'loket@grandduta.test', 'loket'],
            ['Customer Service', 'cs', 'cs@grandduta.test', 'cs'],
        ];

        foreach (range(1, 3) as $i) {
            $users[] = ["Staff Operasional {$i}", "ops{$i}", "ops{$i}@example.com", 'operations_staff'];
            $users[] = ["Security {$i}", "security{$i}", "security{$i}@example.com", 'security'];
            $users[] = ["Technician {$i}", "technician{$i}", "technician{$i}@example.com", 'technician'];
            $users[] = ["Customer Service {$i}", "cs{$i}", "cs{$i}@example.com", 'cs'];
        }

        foreach (['cleaning', 'security.vendor', 'garden', 'construction', 'waste'] as $vendor) {
            $users[] = ['Vendor '.str_replace('.', ' ', $vendor), $vendor, "{$vendor}@vendor.example.com", 'vendor'];
        }

        foreach ($users as [$name, $username, $email, $role]) {
            $user = User::updateOrCreate(['username' => $username], [
                'name' => $name,
                'email' => $email,
                'phone' => '0812'.str_pad((string) crc32($username), 10, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'is_active' => true,
                'theme_preference' => 'system',
                'language_preference' => 'id',
                'notification_preferences' => ['billing' => true, 'payments' => true, 'complaints' => true, 'maintenance' => true, 'documents' => true, 'announcements' => true],
                'last_login_at' => now()->subDays(rand(0, 20)),
                'last_login_ip' => '10.10.0.'.rand(2, 250),
            ]);
            $user->syncRoles([$role]);
        }
    }
}
