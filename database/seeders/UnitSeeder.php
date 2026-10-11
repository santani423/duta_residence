<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class UnitSeeder extends Seeder
{
    /**
     * Akun customer legacy dengan username "customer" (bukan customer.{kode unit}),
     * dipakai sebagai akun uji loket/pembayaran.
     */
    public const LEGACY_CUSTOMER_UNIT = '07012';

    /**
     * Unit diambil dari database/data/pelanggan.csv (lihat PelangganData). Ditulis secara
     * bulk (upsert per 500 baris) karena jumlahnya ~1.400 unit dan seeder dijalankan ke
     * database remote - updateOrCreate per baris terlalu lambat.
     */
    public function run(): void
    {
        $creator = User::where('username', 'admin.estate')->first() ?: User::where('username', 'root')->first();
        $now = now();
        $units = collect(PelangganData::units());

        $rows = $units->map(fn (array $unit) => [
            'id' => $unit['id'],
            // VA per unit: deterministik dari kode unit yang sudah unik.
            'va_number' => '8801'.str_pad($unit['id'], 6, '0', STR_PAD_LEFT),
            'resident_id' => $unit['resident']['id'] ?? null,
            'cluster_id' => $unit['cluster_id'],
            'block' => $unit['block'],
            'lot_number' => $unit['lot_number'],
            'property_type_id' => $unit['property_type_id'],
            'building_area' => $unit['building_area'],
            'land_area' => $unit['land_area'],
            'handover_date' => $unit['handover_date'],
            'occupancy_id' => $unit['occupancy_id'],
            'status_id' => $unit['status_id'],
            'is_penalty_eligible' => true,
            'is_discount_eligible' => false,
            'created_by' => $creator?->id,
            'updated_by' => $creator?->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->values();

        foreach ($rows->chunk(500) as $chunk) {
            Unit::upsert($chunk->all(), ['id'], [
                'resident_id', 'cluster_id', 'block', 'lot_number', 'property_type_id', 'building_area',
                'land_area', 'handover_date', 'occupancy_id', 'status_id', 'updated_by', 'updated_at',
            ]);
        }

        $this->seedCustomerAccounts($units->filter(fn (array $unit) => $unit['resident'] && $unit['status_id'] !== 'RK'));
    }

    /**
     * Setiap unit berpemilik mendapat akun customer (username customer.{kode unit},
     * password awal "password"), sama seperti akun yang dibuat ResidentAccountService.
     */
    private function seedCustomerAccounts($units): void
    {
        $password = Hash::make('password');
        $now = now();
        $takenEmails = User::query()->whereNotNull('email')->pluck('email')->flip();
        $preferences = json_encode([
            'billing' => true,
            'payments' => true,
            'complaints' => true,
            'maintenance' => true,
            'documents' => true,
            'announcements' => true,
        ]);

        $rows = $units->map(function (array $unit) use ($password, $now, &$takenEmails, $preferences) {
            $resident = $unit['resident'];
            $username = $unit['id'] === self::LEGACY_CUSTOMER_UNIT ? 'customer' : 'customer.'.$unit['id'];
            $email = $resident['email'] && ! isset($takenEmails[$resident['email']]) ? $resident['email'] : null;
            if ($email) {
                $takenEmails[$email] = true;
            }

            return [
                'username' => $username,
                'name' => $resident['name'],
                'email' => $email,
                'phone' => $resident['phone'],
                'unit_id' => $unit['id'],
                'resident_id' => $resident['id'],
                'password' => $password,
                'is_active' => $unit['status_id'] === 'AK',
                'theme_preference' => 'system',
                'language_preference' => 'id',
                'notification_preferences' => $preferences,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        })->values();

        foreach ($rows->chunk(500) as $chunk) {
            User::upsert($chunk->all(), ['username'], [
                'name', 'email', 'phone', 'unit_id', 'resident_id', 'is_active', 'updated_at',
            ]);
        }

        $role = Role::findByName('customer', 'web');
        $userIds = User::query()->whereIn('username', $rows->pluck('username'))->pluck('id');
        foreach ($userIds->chunk(500) as $chunk) {
            DB::table(config('permission.table_names.model_has_roles'))->insertOrIgnore(
                $chunk->map(fn ($id) => [
                    'role_id' => $role->id,
                    'model_type' => (new User)->getMorphClass(),
                    'model_id' => $id,
                ])->values()->all()
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
