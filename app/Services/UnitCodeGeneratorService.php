<?php

namespace App\Services;

use App\Models\Cluster;
use App\Models\Unit;

class UnitCodeGeneratorService
{
    /**
     * Kode unit baru = kode cluster diikuti nomor urut sampai total 5 karakter, mis. AX001 atau
     * D0001 (kolom units.id adalah primary key string(5)). Unit hasil impor sistem lama tetap
     * memakai kode angka (mis. 07001, lihat UnitSeeder) karena sudah menjadi username penghuni
     * dan nomor VA, jadi nomor urut di sini tidak melanjutkan kode angka tersebut.
     *
     * Harus dipanggil di dalam DB::transaction() yang sama dengan Unit::create()-nya.
     * Untuk mencegah dua request Add Unit bersamaan mendapat kode yang sama, kita kunci
     * baris cluster terkait (yang pasti selalu ada, tidak seperti baris unit yang mungkin
     * belum ada sama sekali untuk cluster baru) dengan SELECT ... FOR UPDATE, sehingga
     * request kedua menunggu sampai request pertama commit (unit-nya sudah tersimpan)
     * sebelum menghitung nomor urut berikutnya.
     */
    public function generate(string $clusterId): string
    {
        Cluster::query()->whereKey($clusterId)->lockForUpdate()->firstOrFail();

        $prefixLength = strlen($clusterId);
        $sequenceLength = 5 - $prefixLength;

        $lastSequence = Unit::withTrashed()
            ->where('cluster_id', $clusterId)
            ->where('id', 'like', $clusterId.str_repeat('_', $sequenceLength))
            ->get(['id'])
            ->map(fn (Unit $unit) => (int) substr($unit->id, $prefixLength))
            ->max();

        $nextSequence = ($lastSequence ?? 0) + 1;

        $maxSequence = (10 ** $sequenceLength) - 1;
        if ($nextSequence > $maxSequence) {
            throw new \RuntimeException("Kapasitas kode unit untuk cluster {$clusterId} sudah habis.");
        }

        return $clusterId.str_pad((string) $nextSequence, $sequenceLength, '0', STR_PAD_LEFT);
    }
}
