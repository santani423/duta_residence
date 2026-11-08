<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Rentang tanggal dashboard (`start` & `end`, format YYYY-MM-DD, keduanya inklusif).
 * Tanpa parameter: dari awal bulan ini sampai hari ini.
 */
final class DashboardDateRange
{
    /** @return array{0: Carbon, 1: Carbon} */
    public static function fromRequest(Request $request): array
    {
        $validated = $request->validate([
            'start' => ['nullable', 'date_format:Y-m-d'],
            'end' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $start = Carbon::createFromFormat('Y-m-d', $validated['start'] ?? now()->format('Y-m-01'))->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $validated['end'] ?? today()->format('Y-m-d'))->startOfDay();

        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);
        }

        return [$start, $end];
    }
}
