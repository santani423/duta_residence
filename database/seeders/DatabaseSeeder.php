<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Billing;
use App\Models\BillingStatus;
use App\Models\Cluster;
use App\Models\Customer;
use App\Models\CustomerStatus;
use App\Models\District;
use App\Models\NotificationQueue;
use App\Models\OccupancyStatus;
use App\Models\PaymentChannel;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\PropertyType;
use App\Models\Receipt;
use App\Models\Regency;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedPermissions();
        $this->seedLookupData();

        $root = $this->user('Root Administrator', 'root', 'root@grandduta.test', 'root');
        $backOffice = $this->user('Back Office Grand Duta', 'backoffice', 'backoffice@grandduta.test', 'back_office');
        $loket = $this->user('Loket Kasir', 'loket', 'loket@grandduta.test', 'loket');
        $cs = $this->user('Customer Service', 'cs', 'cs@grandduta.test', 'cs');

        $this->seedCustomersAndTransactions($root, $backOffice, $loket, $cs);
        $this->user('Budi Santoso', 'customer', 'customer@grandduta.test', 'customer', 'DO001');
    }

    private function seedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'customers.convert-property',
            'clusters.view', 'clusters.update-rate',
            'billings.view', 'billings.prepare', 'billings.prepare-special', 'billings.prepare-back', 'billings.approve', 'billings.update', 'billings.delete',
            'payments.view', 'payments.create', 'payments.process', 'payments.verify', 'payments.cancel', 'payments.refund',
            'installments.view', 'installments.create',
            'reversals.view', 'reversals.submit', 'reversals.approve',
            'reports.view', 'documents.generate',
            'users.view', 'users.create', 'users.update', 'users.delete', 'users.activate', 'users.reset-password',
            'audit-logs.view', 'audit.view',
            'payment-settings.view', 'payment-settings.update',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $roles = [
            'root' => $permissions,
            'back_office' => [
                'customers.view', 'customers.create', 'customers.update', 'customers.convert-property',
                'clusters.view', 'clusters.update-rate',
                'billings.view', 'billings.prepare', 'billings.prepare-special', 'billings.prepare-back', 'billings.approve', 'billings.update',
                'payments.view', 'payments.create', 'payments.process', 'payments.verify',
                'installments.view', 'installments.create',
                'reversals.view', 'reversals.submit', 'reversals.approve',
                'reports.view', 'documents.generate',
            ],
            'loket' => [
                'customers.view', 'clusters.view', 'billings.view',
                'payments.view', 'payments.process', 'payments.create',
                'installments.view', 'installments.create',
                'reversals.view', 'reversals.submit',
                'documents.generate',
            ],
            'cs' => ['customers.view', 'clusters.view', 'billings.view'],
            'customer' => [],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->forceFill(['is_system' => true, 'is_active' => true])->save();
            $role->syncPermissions(
                Permission::query()
                    ->where('guard_name', 'web')
                    ->whereIn('name', $rolePermissions)
                    ->get()
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedLookupData(): void
    {
        collect([
            ['id' => 'B', 'name' => 'Bangunan', 'description' => 'Unit bangunan siap huni'],
            ['id' => 'K', 'name' => 'Kavling Developer', 'description' => 'Kavling milik developer'],
            ['id' => 'P', 'name' => 'Kavling Pelanggan', 'description' => 'Kavling milik pelanggan'],
        ])->each(fn ($row) => PropertyType::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => '1', 'name' => 'Dihuni'], ['id' => '2', 'name' => 'Kosong']])
            ->each(fn ($row) => OccupancyStatus::updateOrCreate(['id' => $row['id']], $row));

        collect([
            ['id' => 'AK', 'name' => 'Aktif', 'description' => 'Pelanggan aktif'],
            ['id' => 'RK', 'name' => 'Rumah Kosong', 'description' => 'Unit kosong'],
            ['id' => 'TA', 'name' => 'Tidak Aktif', 'description' => 'Pelanggan tidak aktif'],
        ])->each(fn ($row) => CustomerStatus::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => '01', 'name' => 'Belum Bayar'], ['id' => '02', 'name' => 'Lunas']])
            ->each(fn ($row) => BillingStatus::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => 'C', 'name' => 'Cash'], ['id' => 'D', 'name' => 'Debit / Transfer']])
            ->each(fn ($row) => PaymentMethod::updateOrCreate(['id' => $row['id']], $row));

        collect([['id' => 'L', 'name' => 'Loket'], ['id' => 'M', 'name' => 'Manual Transfer'], ['id' => 'X', 'name' => 'Xendit'], ['id' => 'T', 'name' => 'Midtrans']])
            ->each(fn ($row) => PaymentChannel::updateOrCreate(['id' => $row['id']], $row));

        Regency::updateOrCreate(['id' => '3671'], ['name' => 'Kota Tangerang']);
        District::updateOrCreate(['id' => '367101'], ['regency_id' => '3671', 'name' => 'Ciledug']);
        District::updateOrCreate(['id' => '367102'], ['regency_id' => '3671', 'name' => 'Karang Tengah']);

        collect([
            ['id' => 'DO', 'name' => 'Dolomite', 'monthly_rate' => 350000],
            ['id' => 'GA', 'name' => 'Garnet', 'monthly_rate' => 300000],
            ['id' => 'JA', 'name' => 'Jade', 'monthly_rate' => 325000],
            ['id' => 'RU', 'name' => 'Ruby', 'monthly_rate' => 375000],
            ['id' => 'SA', 'name' => 'Sapphire', 'monthly_rate' => 400000],
            ['id' => 'EM', 'name' => 'Emerald', 'monthly_rate' => 450000],
            ['id' => 'TO', 'name' => 'Topaz', 'monthly_rate' => 300000],
            ['id' => 'AM', 'name' => 'Amethyst', 'monthly_rate' => 350000],
            ['id' => 'OP', 'name' => 'Opal', 'monthly_rate' => 325000],
            ['id' => 'AQ', 'name' => 'Aquamarine', 'monthly_rate' => 375000],
            ['id' => 'PE', 'name' => 'Pearl', 'monthly_rate' => 400000],
            ['id' => 'ON', 'name' => 'Onyx', 'monthly_rate' => 350000],
            ['id' => 'CI', 'name' => 'Citrine', 'monthly_rate' => 325000],
            ['id' => 'BE', 'name' => 'Beryl', 'monthly_rate' => 300000],
        ])->each(fn ($row) => Cluster::updateOrCreate(['id' => $row['id']], [...$row, 'is_active' => true]));
    }

    private function user(string $name, string $username, string $email, string $role, ?string $customerId = null): User
    {
        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'name' => $name,
                'email' => $email,
                'phone' => '08120000'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT),
                'customer_id' => $customerId,
                'password' => Hash::make('password'),
                'is_active' => true,
                'theme_preference' => 'system',
                'language_preference' => 'id',
                'notification_preferences' => [
                    'billing' => true,
                    'payments' => true,
                    'complaints' => true,
                    'maintenance' => true,
                    'documents' => true,
                    'announcements' => true,
                ],
            ]
        );
        $user->syncRoles([$role]);

        return $user;
    }

    private function seedCustomersAndTransactions(User $root, User $backOffice, User $loket, User $cs): void
    {
        $customers = collect([
            ['id' => 'DO001', 'name' => 'Budi Santoso', 'cluster_id' => 'DO', 'block' => 'A', 'lot_number' => '01', 'property_type_id' => 'B'],
            ['id' => 'GA012', 'name' => 'Sinta Prameswari', 'cluster_id' => 'GA', 'block' => 'B', 'lot_number' => '12', 'property_type_id' => 'B'],
            ['id' => 'JA009', 'name' => 'Rizky Hidayat', 'cluster_id' => 'JA', 'block' => 'C', 'lot_number' => '09', 'property_type_id' => 'K'],
            ['id' => 'RU021', 'name' => 'Maya Lestari', 'cluster_id' => 'RU', 'block' => 'D', 'lot_number' => '21', 'property_type_id' => 'P'],
        ])->map(function ($row) use ($backOffice) {
            return Customer::updateOrCreate(['id' => $row['id']], [
                ...$row,
                'phone' => '0812'.random_int(10000000, 99999999),
                'telephone' => '021'.random_int(1000000, 9999999),
                'id_card_address' => 'Grand Duta Residence',
                'district_id' => '367101',
                'building_area' => 90,
                'land_area' => 120,
                'email' => strtolower($row['id']).'@example.test',
                'handover_date' => now()->subYear()->toDateString(),
                'occupancy_id' => '1',
                'status_id' => 'AK',
                'is_penalty_eligible' => true,
                'is_discount_eligible' => false,
                'created_by' => $backOffice->id,
            ]);
        });

        foreach ($customers as $customer) {
            foreach ([now()->subMonth(), now()] as $period) {
                Billing::updateOrCreate(
                    ['customer_id' => $customer->id, 'year' => $period->year, 'month' => $period->month],
                    [
                        'amount' => $customer->cluster->monthly_rate,
                        'status_id' => '01',
                        'billing_type' => 'regular',
                        'approved_by' => $backOffice->id,
                        'approved_at' => now()->subDays(5),
                        'created_by' => $backOffice->id,
                    ]
                );
            }
        }

        $paidBilling = Billing::where('customer_id', 'DO001')->oldest()->first();
        $receipt = Receipt::updateOrCreate(
            ['number' => 'GD.'.now()->format('Y.m').'.000001'],
            [
                'customer_id' => 'DO001',
                'transaction_date' => now()->subDay(),
                'customer_name' => 'Budi Santoso',
                'cluster_name' => 'Dolomite',
                'block' => 'A',
                'lot_number' => '01',
                'total_billing' => $paidBilling->amount,
                'total_penalty' => 0,
                'grand_total' => $paidBilling->amount,
                'billing_count' => 1,
                'billing_periods' => sprintf('%04d-%02d', $paidBilling->year, $paidBilling->month),
                'loket_code' => 'L01',
                'cashier_name' => $loket->name,
                'payment_method_id' => 'C',
                'payment_channel_id' => 'L',
                'status' => 'paid',
                'created_by' => $loket->id,
            ]
        );
        $paidBilling->update(['status_id' => '02', 'paid_at' => now()->subDay(), 'receipt_number' => $receipt->number, 'processed_by' => $loket->id]);

        $manual = PaymentTransaction::updateOrCreate(
            ['invoice_number' => 'INV-MANUAL-000001'],
            [
                'transaction_number' => 'TRX-MANUAL-000001',
                'customer_id' => 'GA012',
                'subtotal' => 300000,
                'tax' => 0,
                'admin_fee' => 0,
                'total' => 300000,
                'currency' => 'IDR',
                'payment_provider' => 'manual',
                'payment_method' => 'transfer',
                'status' => 'waiting_verification',
                'manual_transfer_date' => now()->toDateString(),
                'manual_notes' => 'Sample transfer menunggu verifikasi',
                'created_by' => $cs->id,
            ]
        );
        $manual->billings()->sync(Billing::where('customer_id', 'GA012')->limit(1)->pluck('id'));

        AuditLog::create([
            'user_id' => $root->id,
            'user_name' => $root->name,
            'user_role' => 'root',
            'activity' => 'sample_seed_created',
            'module' => 'system',
            'action' => 'SEED',
            'status' => 'success',
            'description' => 'Sample data awal dibuat.',
        ]);

        NotificationQueue::create([
            'customer_id' => 'GA012',
            'type' => 'payment_manual_waiting_verification',
            'channel' => 'in_app',
            'recipient' => $backOffice->email,
            'message' => 'Pembayaran manual GA012 menunggu verifikasi.',
            'status' => 'pending',
            'read_status' => 'unread',
        ]);
    }
}
