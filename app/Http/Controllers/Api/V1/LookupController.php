<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\District;
use App\Models\Regency;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class LookupController extends Controller
{
    use ApiResponse;

    public function regencies()
    {
        return $this->success(Regency::query()->orderBy('name')->get());
    }

    public function districts(Request $request)
    {
        return $this->success(District::query()
            ->when($request->query('regency_id'), fn ($q, $value) => $q->where('regency_id', $value))
            ->orderBy('name')
            ->get());
    }

    public function roles()
    {
        return $this->success(Role::query()->orderBy('name')->pluck('name'));
    }
}
