<?php

namespace App\Support;

use App\Models\Cluster;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;

/**
 * Filter lokasi/pemilik unit yang dipakai bersama oleh semua daftar Unit & Tagihan (tagihan,
 * riwayat tagihan, piutang, cicilan, pembayaran, kuitansi, skema pembayaran, surat penagihan,
 * rekonsiliasi saldo) beserta ekspornya, supaya Cluster/Blok/Unit/Customer/Alamat berperilaku
 * sama di setiap halaman dan PDF/Excel selalu berisi baris yang sama dengan tabel di layar.
 */
final class UnitFilters
{
    public const KEYS = ['cluster_id', 'block', 'unit_id', 'resident_id', 'customer', 'address'];

    /** Hanya filter yang benar-benar diisi (string kosong/spasi diabaikan). */
    public static function from(Request|array $source, array $except = []): array
    {
        $values = $source instanceof Request ? $source->query() : $source;
        $filters = [];

        foreach (array_diff(self::KEYS, $except) as $key) {
            $value = $values[$key] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value !== null && $value !== '' && ! is_array($value)) {
                $filters[$key] = (string) $value;
            }
        }

        return $filters;
    }

    /**
     * Batasi query apa pun yang punya kolom ID unit ke unit yang cocok dengan filter. Memakai
     * subquery `whereIn` agar bisa dipakai baik oleh Eloquent maupun DB::table().
     */
    public static function apply(EloquentBuilder|QueryBuilder $query, Request|array $source, string $column = 'unit_id', array $except = []): EloquentBuilder|QueryBuilder
    {
        $filters = self::from($source, $except);

        if ($filters === []) {
            return $query;
        }

        return $query->whereIn($column, Unit::query()->matchingFilters($filters)->select('units.id'));
    }

    /** Label filter aktif untuk baris keterangan di PDF. */
    public static function describe(Request|array $source): array
    {
        $filters = self::from($source);

        return [
            'Cluster' => isset($filters['cluster_id'])
                ? (Cluster::query()->find($filters['cluster_id'])?->name ?? $filters['cluster_id'])
                : null,
            'Blok' => $filters['block'] ?? null,
            'Unit' => $filters['unit_id'] ?? null,
            'Customer' => $filters['customer'] ?? null,
            'Alamat' => $filters['address'] ?? null,
        ];
    }
}
