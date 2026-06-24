<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = AuditLog::query()
            ->with('user')
            ->when($request->query('search'), fn ($q, $value) => $q->where(fn ($inner) => $inner
                ->where('activity', 'like', "%{$value}%")
                ->orWhere('description', 'like', "%{$value}%")
                ->orWhere('endpoint', 'like', "%{$value}%")))
            ->when($request->query('user_id'), fn ($q, $value) => $q->where('user_id', $value))
            ->when($request->query('role'), fn ($q, $value) => $q->where('user_role', 'like', "%{$value}%"))
            ->when($request->query('module'), fn ($q, $value) => $q->where('module', $value))
            ->when($request->query('action'), fn ($q, $value) => $q->where('action', $value))
            ->when($request->query('status'), fn ($q, $value) => $q->where('status', $value))
            ->when($request->query('ip_address'), fn ($q, $value) => $q->where('ip_address', $value))
            ->when($request->query('date_from'), fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($request->query('date_to'), fn ($q, $value) => $q->whereDate('created_at', '<=', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }
}
