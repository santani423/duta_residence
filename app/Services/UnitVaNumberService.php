<?php

namespace App\Services;

use App\Models\PaymentGatewaySetting;
use App\Models\Unit;
use Illuminate\Validation\ValidationException;

class UnitVaNumberService
{
    /**
     * Nomor VA lengkap = kode bank + kode perusahaan (Pengaturan Payment Gateway) + 9 digit yang
     * diinput di form. Prefix selalu diambil dari pengaturan, bukan dari klien. Nomor VA unik per unit.
     *
     * @throws ValidationException
     */
    public function compose(string $suffix, ?Unit $unit = null): string
    {
        $length = PaymentGatewaySetting::VA_SUFFIX_LENGTH;

        if (! preg_match('/^\d{'.$length.'}$/', $suffix)) {
            throw ValidationException::withMessages(['va_suffix' => ["Nomor VA harus berupa {$length} digit angka."]]);
        }

        $prefix = PaymentGatewaySetting::current()->vaPrefix();

        if ($prefix === '') {
            throw ValidationException::withMessages([
                'va_suffix' => ['Kode bank dan kode perusahaan VA belum diatur di Pengaturan Payment Gateway.'],
            ]);
        }

        $vaNumber = $prefix.$suffix;

        if ($this->isTaken($vaNumber, $unit?->id)) {
            throw ValidationException::withMessages([
                'va_suffix' => ['Nomor virtual account ini sudah digunakan oleh unit lain. Silakan gunakan nomor lain.'],
            ]);
        }

        return $vaNumber;
    }

    public function isTaken(string $vaNumber, ?string $exceptUnitId = null): bool
    {
        return Unit::query()
            ->where('va_number', $vaNumber)
            ->when($exceptUnitId, fn ($query) => $query->where('id', '!=', $exceptUnitId))
            ->exists();
    }
}
