<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\NotificationQueue;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $query = NotificationQueue::query()
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->when($request->query('read_status'), fn ($q, $value) => $q->where('read_status', $value));

        return $this->paginated($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function read(NotificationQueue $notification)
    {
        $notification->update(['read_status' => 'read']);

        return $this->success($notification, 'Notifikasi ditandai dibaca.');
    }

    public function readAll(Request $request)
    {
        NotificationQueue::query()
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $request->user()->id))
            ->update(['read_status' => 'read']);

        return $this->success(null, 'Semua notifikasi ditandai dibaca.');
    }
}
