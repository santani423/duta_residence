<?php

namespace App\Observers;

use App\Models\Billing;
use App\Services\CollectionAccountRefreshQueue;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

/**
 * Perubahan tagihan (approve, pembayaran, reversal, diskon, pembatalan) mengubah outstanding
 * akun penagihan. Refresh ditunda & digabung per request lewat CollectionAccountRefreshQueue,
 * karena approve tagihan bulanan bisa menyentuh ribuan tagihan sekaligus.
 */
class BillingCollectionObserver implements ShouldHandleEventsAfterCommit
{
    private const TRACKED = [
        'status_id', 'amount', 'discount', 'principal_paid', 'penalty_paid',
        'penalty_waived_amount', 'penalty_fixed', 'approved_at', 'cancelled_at',
    ];

    public function __construct(private readonly CollectionAccountRefreshQueue $queue) {}

    public function created(Billing $billing): void
    {
        $this->push($billing);
    }

    public function updated(Billing $billing): void
    {
        if ($billing->wasChanged(self::TRACKED)) {
            $this->push($billing);
        }
    }

    public function deleted(Billing $billing): void
    {
        $this->push($billing);
    }

    private function push(Billing $billing): void
    {
        try {
            $this->queue->push($billing->unit_id);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
