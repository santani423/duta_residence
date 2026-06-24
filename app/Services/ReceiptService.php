<?php

namespace App\Services;

use App\Models\Receipt;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    public function generateReceiptNumber(): string
    {
        return DB::transaction(function () {
            $prefix = config('grandduta.receipt_prefix', 'GD');
            $year = now()->format('Y');
            $month = now()->format('m');
            $base = "{$prefix}.{$year}.{$month}.";

            $last = Receipt::query()
                ->where('number', 'like', "{$base}%")
                ->lockForUpdate()
                ->orderByDesc('number')
                ->value('number');

            $next = $last ? ((int) substr($last, -6)) + 1 : 1;

            return $base.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
        });
    }
}
