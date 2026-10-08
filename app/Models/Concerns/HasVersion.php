<?php

namespace App\Models\Concerns;

/**
 * Nomor versi optimistic-locking: naik setiap kali record berubah. Klien offline mengirim
 * `base_version`; bila tidak sama dengan versi server, perubahan diperlakukan sebagai konflik
 * dan tidak pernah menimpa data server.
 */
trait HasVersion
{
    public static function bootHasVersion(): void
    {
        // Default kolom di DB tidak dimuat ke model setelah insert; set eksplisit agar update
        // pertama pada instance yang sama menaikkan versi dari 1, bukan dari 0.
        static::creating(function ($model) {
            $model->version ??= 1;
        });

        static::updating(function ($model) {
            if ($model->isDirty() && ! $model->isDirty('version')) {
                $model->version = (int) $model->getOriginal('version', 0) + 1;
            }
        });
    }

    public function isVersion(?int $baseVersion): bool
    {
        return $baseVersion !== null && (int) $this->version === $baseVersion;
    }
}
