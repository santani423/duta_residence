<?php

namespace App\Support;

use Illuminate\Http\Request;

class Pagination
{
    public const MAX_PER_PAGE = 100;

    /** `per_page` dari request, dibatasi 1..MAX_PER_PAGE (nilai tidak valid → default). */
    public static function perPage(Request $request, int $default = 15, int $max = self::MAX_PER_PAGE): int
    {
        $perPage = $request->integer('per_page', $default);

        if ($perPage < 1) {
            $perPage = $default;
        }

        return max(1, min($perPage, $max));
    }
}
