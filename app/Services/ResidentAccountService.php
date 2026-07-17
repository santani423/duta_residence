<?php

namespace App\Services;

use App\Models\Resident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResidentAccountService
{
    public function generateResidentId(): string
    {
        return DB::transaction(function () {
            $next = Resident::query()
                ->where('id', 'like', 'RS%')
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id) => preg_match('/^RS(\d{6})$/', $id, $matches) ? (int) $matches[1] : 0)
                ->max() + 1;

            return 'RS'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Setiap penghuni baru otomatis mendapat akun login (role customer).
     * unit_id akan kosong sampai Unit pertama penghuni ini dibuat — resident_id disimpan
     * di sini supaya UnitOwnershipSyncService bisa auto-link akun ini begitu Unit-nya tersedia.
     */
    public function createCustomerAccount(Resident $resident, AuditService $auditService, ?string $username = null): array
    {
        $username = $username ?: 'customer.'.strtolower($resident->id);
        $email = $resident->email && ! User::query()->where('email', $resident->email)->exists()
            ? $resident->email
            : null;
        $temporaryPassword = 'password';

        $user = User::query()->create([
            'name' => $resident->name,
            'username' => $username,
            'email' => $email,
            'phone' => $resident->phone,
            'resident_id' => $resident->id,
            'password' => Hash::make($temporaryPassword),
            'is_active' => true,
        ]);
        $user->assignRole('customer');

        $auditService->log('user_created', 'users', 'CREATE', $user, [], $user->toArray());

        return [
            'user_id' => $user->id,
            'username' => $user->username,
            'temporary_password' => $temporaryPassword,
        ];
    }
}
