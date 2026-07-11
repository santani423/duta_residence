<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'residents.view', 'residents.create', 'residents.update', 'residents.delete',
            'units.view', 'units.create', 'units.update', 'units.delete', 'units.convert-property',
            'clusters.view', 'clusters.create', 'clusters.update-rate', 'clusters.delete',
            'billings.view', 'billings.prepare', 'billings.prepare-special', 'billings.prepare-back', 'billings.approve', 'billings.update', 'billings.delete',
            'payments.view', 'payments.create', 'payments.process', 'payments.verify', 'payments.cancel', 'payments.refund',
            'installments.view', 'installments.create',
            'reversals.view', 'reversals.submit', 'reversals.approve',
            'reports.view', 'documents.generate',
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.activate', 'users.reset-password',
            'audit-logs.view', 'audit.view',
            'payment-settings.view', 'payment-settings.update',
            'visits.view', 'visits.create', 'visits.update',
            'payment-promises.view', 'payment-promises.create', 'payment-promises.update',
            'resident-documents.view', 'resident-documents.create', 'resident-documents.update', 'resident-documents.delete', 'resident-documents.verify',
            'vehicles.view', 'vehicles.create', 'vehicles.update', 'vehicles.delete',
            'occupants.view', 'occupants.create', 'occupants.update', 'occupants.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'root' => $permissions,
            'super_admin' => $permissions,
            'admin_estate' => [
                'residents.view', 'residents.create', 'residents.update',
                'units.view', 'units.create', 'units.update', 'units.convert-property',
                'clusters.view', 'clusters.create', 'clusters.update-rate',
                'billings.view', 'billings.prepare', 'billings.prepare-special', 'billings.prepare-back', 'billings.approve',
                'payments.view', 'payments.create', 'payments.verify',
                'reports.view', 'documents.generate', 'users.view', 'audit-logs.view',
                'payment-settings.view', 'payment-settings.update',
                'visits.view', 'payment-promises.view',
                'resident-documents.view', 'resident-documents.create', 'resident-documents.update', 'resident-documents.delete', 'resident-documents.verify',
                'vehicles.view', 'vehicles.create', 'vehicles.update', 'vehicles.delete',
                'occupants.view', 'occupants.create', 'occupants.update', 'occupants.delete',
            ],
            'back_office' => [
                'residents.view', 'residents.create', 'residents.update',
                'units.view', 'units.create', 'units.update', 'units.convert-property',
                'clusters.view', 'clusters.create', 'clusters.update-rate',
                'billings.view', 'billings.prepare', 'billings.prepare-special', 'billings.prepare-back', 'billings.approve', 'billings.update',
                'payments.view', 'payments.create', 'payments.process', 'payments.verify',
                'installments.view', 'installments.create',
                'reversals.view', 'reversals.submit', 'reversals.approve',
                'reports.view', 'documents.generate',
                'resident-documents.view', 'resident-documents.create',
                'vehicles.view', 'vehicles.create', 'vehicles.update',
                'occupants.view', 'occupants.create', 'occupants.update',
            ],
            'finance' => [
                'residents.view', 'units.view', 'billings.view', 'billings.prepare', 'billings.approve',
                'payments.view', 'payments.create', 'payments.verify', 'payments.refund',
                'installments.view', 'installments.create', 'reports.view', 'documents.generate',
                'payment-settings.view',
                'payment-promises.view', 'payment-promises.create', 'payment-promises.update',
                'resident-documents.view', 'resident-documents.verify',
            ],
            'property_manager' => [
                'residents.view', 'units.view', 'clusters.view', 'billings.view', 'payments.view',
                'reports.view', 'documents.generate', 'users.view',
                'visits.view', 'payment-promises.view',
                'resident-documents.view', 'vehicles.view', 'occupants.view',
            ],
            'operations_staff' => ['residents.view', 'units.view', 'clusters.view', 'billings.view', 'documents.generate'],
            'security' => ['residents.view', 'units.view', 'clusters.view', 'vehicles.view', 'occupants.view'],
            'technician' => ['residents.view', 'units.view', 'clusters.view'],
            'vendor' => ['residents.view', 'units.view', 'clusters.view'],
            'loket' => [
                'residents.view', 'units.view', 'clusters.view', 'billings.view',
                'payments.view', 'payments.process', 'payments.create',
                'installments.view', 'installments.create',
                'reversals.view', 'reversals.submit',
                'documents.generate',
            ],
            'cs' => [
                'residents.view', 'residents.update', 'units.view', 'clusters.view', 'billings.view',
                'resident-documents.view', 'resident-documents.create',
                'occupants.view', 'occupants.create', 'occupants.update',
            ],
            'collector' => [
                'residents.view', 'units.view', 'clusters.view', 'billings.view',
                'payments.view', 'payments.process',
                'visits.view', 'visits.create', 'visits.update',
                'payment-promises.view', 'payment-promises.create', 'payment-promises.update',
            ],
            'customer' => [],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->forceFill(['is_system' => true, 'is_active' => true])->save();
            $role->syncPermissions(Permission::whereIn('name', $rolePermissions)->get());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
