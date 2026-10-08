<?php

namespace App\Observers;

use App\Models\CollectorVisit;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Services\CollectionAccountRefreshQueue;
use App\Services\CollectionActivityService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Mencatat visit, PTP, pembayaran, dan pengingat WA ke timeline penagihan - dari endpoint mana
 * pun entitas itu dibuat (endpoint lama maupun /collector/*), sehingga timeline selalu lengkap.
 *
 * Refresh cache status akun digabung lewat CollectionAccountRefreshQueue (sekali per unit per
 * request), karena entitas ini juga dibuat massal oleh seeder/proses batch.
 *
 * Berjalan SETELAH commit dan tidak pernah melempar error: kegagalan timeline tidak boleh
 * membatalkan pembayaran atau aksi bisnis yang sudah sah. Error dilaporkan ke log.
 */
class CollectionTimelineObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(
        private readonly CollectionActivityService $activityService,
        private readonly CollectionAccountRefreshQueue $refreshQueue,
    ) {}

    public function created(Model $model): void
    {
        $this->safely(function () use ($model) {
            $this->activityService->recordForSubject($model, null, refresh: false);
            $this->refreshQueue->push($model->unit_id);
        });
    }

    public function updated(Model $model): void
    {
        $this->safely(function () use ($model) {
            if ($model instanceof PaymentTransaction) {
                // Event pembayaran (submitted/paid/rejected/...) diturunkan dari status barunya.
                if ($model->wasChanged('status')) {
                    $this->activityService->recordForSubject($model, null, refresh: false);
                }
            } elseif (($event = $this->updateEvent($model)) !== null) {
                $this->activityService->recordForSubject($model, $event, refresh: false);
            }

            // Perubahan lain (nominal/tanggal PTP, dsb.) tetap memengaruhi status akun.
            $this->refreshQueue->push($model->unit_id);
        });
    }

    /** Event timeline untuk update visit/PTP, atau null bila update tidak perlu dicatat. */
    private function updateEvent(Model $model): ?string
    {
        if ($model instanceof CollectorVisit) {
            if ($model->wasChanged('lifecycle') && in_array($model->lifecycle, [CollectorVisit::LIFECYCLE_COMPLETED, CollectorVisit::LIFECYCLE_FAILED, CollectorVisit::LIFECYCLE_CANCELLED], true)) {
                return $model->lifecycle;
            }

            return $model->wasChanged(['status', 'result_code']) ? 'result_'.($model->result_code ?? $model->status) : null;
        }

        if ($model instanceof PaymentPromise) {
            return $model->wasChanged('status') && $model->status !== PaymentPromise::STATUS_PENDING ? $model->status : null;
        }

        return null;
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
