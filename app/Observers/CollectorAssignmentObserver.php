<?php

namespace App\Observers;

use App\Models\CollectorAssignment;
use App\Services\CollectionAccountRefreshQueue;
use App\Services\CollectorAssignmentService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Throwable;

/**
 * Perubahan assignment collector (buat, reassign, revoke, ubah tanggal) mengubah collector utama
 * akun penagihan. Semua unit yang tercakup scope saat ini DAN scope sebelum perubahan didorong
 * ke CollectionAccountRefreshQueue supaya `collection_account_states.collector_id` langsung
 * mengikuti, tanpa menunggu refresh terjadwal. Tidak pernah menggagalkan request.
 */
class CollectorAssignmentObserver implements ShouldHandleEventsAfterCommit
{
    private const TRACKED = [
        'collector_id', 'scope_type', 'cluster_id', 'block', 'unit_id', 'resident_id',
        'is_active', 'status', 'start_date', 'end_date',
    ];

    private const SCOPE_FIELDS = ['scope_type', 'cluster_id', 'block', 'unit_id', 'resident_id'];

    public function __construct(
        private readonly CollectionAccountRefreshQueue $queue,
        private readonly CollectorAssignmentService $assignments,
    ) {}

    public function saved(CollectorAssignment $assignment): void
    {
        if (! $assignment->wasRecentlyCreated && ! $assignment->wasChanged(self::TRACKED)) {
            return;
        }

        $this->push($assignment);
    }

    public function deleted(CollectorAssignment $assignment): void
    {
        $this->push($assignment);
    }

    private function push(CollectorAssignment $assignment): void
    {
        try {
            $current = $assignment->only(self::SCOPE_FIELDS);
            // Handler berjalan setelah commit (original sudah tersinkron), jadi nilai sebelum
            // perubahan diambil dari getPrevious(), dengan getOriginal() sebagai cadangan.
            $previous = array_intersect_key(
                array_merge($current, $assignment->getOriginal(), $assignment->getPrevious()),
                array_flip(self::SCOPE_FIELDS)
            );

            $unitIds = $this->assignments->unitIdsForScope($current);
            if ($previous != $current) {
                $unitIds = array_merge($unitIds, $this->assignments->unitIdsForScope($previous));
            }

            foreach (array_unique($unitIds) as $unitId) {
                $this->queue->push((string) $unitId);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
