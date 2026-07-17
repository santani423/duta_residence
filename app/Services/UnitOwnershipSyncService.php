<?php

namespace App\Services;

use App\Models\Unit;
use App\Models\User;

class UnitOwnershipSyncService
{
    /**
     * Jaga agar akun login customer (users.unit_id) selalu mencerminkan kepemilikan/penyewaan
     * Unit yang sebenarnya (units.resident_id = pemilik, units.tenant_resident_id = penyewa)
     * setiap kali Unit dibuat/pemiliknya/penyewanya diubah:
     *   1) Lepaskan akun customer mana pun yang unit_id-nya masih menunjuk ke Unit ini
     *      padahal penghuninya sudah bukan pemilik maupun penyewa unit ini lagi (dipindah
     *      ke unit lain milik/sewaan mereka sendiri jika masih ada, atau dikosongkan jika tidak ada).
     *   2) Tautkan/perbarui akun customer milik pemilik DAN penyewa (jika ada) ke Unit ini,
     *      supaya masing-masing langsung bisa melihat data unitnya di portal tanpa langkah
     *      manual tambahan.
     * Tanpa ini, halaman Admin (yang selalu query langsung dari units.resident_id/tenant_resident_id)
     * dan halaman Customer (yang bergantung pada users.unit_id) bisa jadi tidak sinkron.
     */
    public function sync(Unit $unit, AuditService $auditService): void
    {
        $validResidentIds = array_filter([$unit->resident_id, $unit->tenant_resident_id]);

        User::query()
            ->role('customer')
            ->where('unit_id', $unit->id)
            ->where(fn ($q) => $q->whereNull('resident_id')->orWhereNotIn('resident_id', $validResidentIds))
            ->get()
            ->each(function (User $stale) use ($auditService) {
                $old = $stale->toArray();
                $replacementUnitId = $stale->resident_id
                    ? Unit::query()
                        ->where('resident_id', $stale->resident_id)
                        ->orWhere('tenant_resident_id', $stale->resident_id)
                        ->value('id')
                    : null;
                $stale->forceFill(['unit_id' => $replacementUnitId])->save();
                $auditService->log('user_unlinked_from_unit', 'users', 'UPDATE', $stale, $old, $stale->toArray());
            });

        foreach ($validResidentIds as $residentId) {
            $account = User::query()->role('customer')->where('resident_id', $residentId)->first();

            if ($account && $account->unit_id !== $unit->id) {
                $old = $account->toArray();
                $account->forceFill(['unit_id' => $unit->id])->save();
                $auditService->log('user_linked_to_unit', 'users', 'UPDATE', $account, $old, $account->toArray());
            }
        }
    }
}
