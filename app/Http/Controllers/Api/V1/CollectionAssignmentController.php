<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

/**
 * Penugasan massal: preview cakupan, bulk assign, dan daftar unit belum ditugaskan.
 * Stub Stage A — diisi Stage B.
 */
class CollectionAssignmentController extends Controller
{
    use ApiResponse;

    public function preview(Request $request)
    {
        return $this->error('Belum diimplementasikan.', 501);
    }

    public function bulk(Request $request)
    {
        return $this->error('Belum diimplementasikan.', 501);
    }

    public function unassignedUnits(Request $request)
    {
        return $this->error('Belum diimplementasikan.', 501);
    }
}
