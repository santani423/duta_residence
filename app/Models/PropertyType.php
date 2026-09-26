<?php

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Model;

class PropertyType extends Model
{
    use HasStringPrimaryKey;

    public const BANGUNAN = 'B';

    public const KAVLING = 'K';

    public const RUKO = 'R';

    /**
     * Kode lama yang sudah digabung: 'P' (Kavling Penghuni) kini menjadi 'K' (Kavling).
     * Dinormalisasi di input API supaya klien lama tetap berfungsi.
     */
    public const LEGACY_ALIASES = ['P' => self::KAVLING];

    public $timestamps = false;

    protected $fillable = ['id', 'name', 'description'];

    public static function normalizeId(?string $id): ?string
    {
        return $id === null ? null : (self::LEGACY_ALIASES[$id] ?? $id);
    }
}
