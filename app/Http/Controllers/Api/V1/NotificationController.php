<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\NotificationQueue;
use App\Services\NotificationPresenter;
use Illuminate\Http\Request;

/**
 * Staff/collector notification inbox. Residents have their own scoped endpoints under
 * /resident/notifications; customers are rejected here so a resident session can never
 * read (or mark read) staff-wide broadcasts.
 */
class NotificationController extends Controller
{
    use ApiResponse;

    public function index(Request $request, NotificationPresenter $presenter)
    {
        $this->assertStaff($request);

        $base = NotificationQueue::query()->forStaff($request->user());
        $unreadCount = (clone $base)->unread()->count();

        $paginator = $base
            ->when($request->query('read_status'), fn ($q, $value) => $q->where('read_status', $value))
            ->when($request->query('type'), fn ($q, $value) => $q->where('type', $value))
            ->with('sender:id,name')
            ->latest()->latest('id')
            ->paginate($request->integer('per_page', 15));

        $paginator->setCollection($paginator->getCollection()->map(fn (NotificationQueue $n) => $presenter->queue($n)));

        return $this->paginated($paginator, extraMeta: ['unread_count' => $unreadCount]);
    }

    public function unreadCount(Request $request)
    {
        $this->assertStaff($request);

        return $this->success(['unread_count' => NotificationQueue::query()->forStaff($request->user())->unread()->count()]);
    }

    public function show(Request $request, NotificationQueue $notification, NotificationPresenter $presenter)
    {
        $this->authorizeNotification($request, $notification);

        return $this->success($presenter->queue($notification));
    }

    public function read(Request $request, NotificationQueue $notification, NotificationPresenter $presenter)
    {
        $this->authorizeNotification($request, $notification);
        $notification->markAsRead();

        return $this->success($presenter->queue($notification), 'Notifikasi ditandai dibaca.');
    }

    public function unread(Request $request, NotificationQueue $notification, NotificationPresenter $presenter)
    {
        $this->authorizeNotification($request, $notification);
        $notification->markAsUnread();

        return $this->success($presenter->queue($notification), 'Notifikasi ditandai belum dibaca.');
    }

    public function readAll(Request $request)
    {
        $this->assertStaff($request);

        NotificationQueue::query()->forStaff($request->user())->unread()
            ->update(['read_status' => 'read', 'read_at' => now()]);

        return $this->success(null, 'Semua notifikasi ditandai dibaca.');
    }

    private function assertStaff(Request $request): void
    {
        abort_if($request->user()->hasRole('customer'), 403, 'Notifikasi staf tidak dapat diakses oleh akun penghuni.');
    }

    /** 404 (not 403) for other people's rows so ids can't be probed for existence. */
    private function authorizeNotification(Request $request, NotificationQueue $notification): void
    {
        $this->assertStaff($request);

        abort_unless(
            NotificationQueue::query()->forStaff($request->user())->whereKey($notification->getKey())->exists(),
            404
        );
    }
}
