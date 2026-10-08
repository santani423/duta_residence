<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

/**
 * Monitoring akun penagihan lintas collector & opsi collector (sesuai scope).
 * Stub Stage A — diisi Stage B.
 */
class CollectionMonitoringController extends Controller
{
    use ApiResponse;

    public function accounts(Request $request)
    {
        return $this->error('Belum diimplementasikan.', 501);
    }

    public function collectorOptions(Request $request)
    {
        return $this->error('Belum diimplementasikan.', 501);
    }
}
