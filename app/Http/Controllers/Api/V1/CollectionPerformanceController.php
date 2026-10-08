<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

/**
 * Ranking performa collector per periode.
 * Stub Stage A — diisi Stage B.
 */
class CollectionPerformanceController extends Controller
{
    use ApiResponse;

    public function ranking(Request $request)
    {
        return $this->error('Belum diimplementasikan.', 501);
    }
}
