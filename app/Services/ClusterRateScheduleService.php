<?php

namespace App\Services;

use App\Models\Cluster;
use App\Models\ClusterRateSchedule;
use App\Models\NotificationQueue;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ClusterRateScheduleService
{
    public function __construct(private readonly AuditService $auditService) {}

    /**
     * Resolve the rate that was/will be effective for a cluster during a given billing period,
     * based on the schedule history rather than just the cluster's current cached rate.
     */
    public function rateForPeriod(Cluster $cluster, int $year, int $month): float
    {
        $periodStart = Carbon::create($year, $month, 1);

        $schedule = ClusterRateSchedule::query()
            ->where('cluster_id', $cluster->id)
            ->active()
            ->whereDate('effective_date', '<=', $periodStart)
            ->orderByDesc('effective_date')
            ->first();

        return (float) ($schedule->rate ?? $cluster->monthly_rate);
    }

    public function assertNoConflict(string $clusterId, string $effectiveDate, ?int $ignoreId = null): void
    {
        $exists = ClusterRateSchedule::query()
            ->where('cluster_id', $clusterId)
            ->whereDate('effective_date', $effectiveDate)
            ->when($ignoreId, fn ($q, $value) => $q->where('id', '!=', $value))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'effective_date' => ['Sudah ada jadwal tarif untuk cluster ini pada tanggal tersebut.'],
            ]);
        }
    }

    public function assertMutable(ClusterRateSchedule $schedule): void
    {
        if ($schedule->effective_date->lte(today())) {
            throw ValidationException::withMessages([
                'effective_date' => ['Jadwal tarif yang sudah berlaku tidak dapat diubah atau dihapus.'],
            ]);
        }
    }

    public function notifyScheduled(ClusterRateSchedule $schedule): void
    {
        $cluster = $schedule->cluster ?: Cluster::find($schedule->cluster_id);

        NotificationQueue::query()->create([
            'unit_id' => null,
            'user_id' => null,
            'type' => 'cluster_rate_scheduled',
            'channel' => 'in_app',
            'recipient' => 'staff',
            'message' => sprintf(
                'Tarif cluster %s akan berubah menjadi Rp%s mulai %s.',
                $cluster?->name ?? $schedule->cluster_id,
                number_format((float) $schedule->rate, 0, ',', '.'),
                $schedule->effective_date->format('d/m/Y')
            ),
            'read_status' => 'unread',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    private function notifyActivated(Cluster $cluster, ClusterRateSchedule $schedule): void
    {
        NotificationQueue::query()->create([
            'unit_id' => null,
            'user_id' => null,
            'type' => 'cluster_rate_activated',
            'channel' => 'in_app',
            'recipient' => 'staff',
            'message' => sprintf(
                'Tarif cluster %s telah berubah menjadi Rp%s mulai %s.',
                $cluster->name,
                number_format((float) $schedule->rate, 0, ',', '.'),
                $schedule->effective_date->format('d/m/Y')
            ),
            'read_status' => 'unread',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Sync a single cluster's cached monthly_rate to whatever schedule is currently effective.
     * Safe to call repeatedly (idempotent) - only writes/audits/notifies when the rate actually changes.
     */
    public function syncClusterRate(Cluster $cluster): bool
    {
        $due = ClusterRateSchedule::query()
            ->where('cluster_id', $cluster->id)
            ->active()
            ->whereDate('effective_date', '<=', today())
            ->orderByDesc('effective_date')
            ->first();

        if (! $due) {
            return false;
        }

        $changed = false;
        $oldRate = (float) $cluster->monthly_rate;

        if ((float) $due->rate !== $oldRate) {
            $cluster->forceFill(['monthly_rate' => $due->rate])->save();
            $this->auditService->log(
                'cluster_rate_activated',
                'clusters',
                'RATE_ACTIVATED',
                $cluster,
                ['monthly_rate' => $oldRate],
                ['monthly_rate' => (float) $due->rate],
                'success',
                "Tarif cluster {$cluster->name} otomatis diperbarui sesuai jadwal #{$due->id}."
            );
            $this->notifyActivated($cluster, $due);
            $changed = true;
        }

        if (! $due->activated_at) {
            $due->forceFill(['activated_at' => now()])->saveQuietly();
        }

        return $changed;
    }

    /**
     * Run across every cluster - this is what the scheduled command calls.
     */
    public function activateDueSchedules(): Collection
    {
        $activated = collect();

        Cluster::query()->each(function (Cluster $cluster) use ($activated) {
            if ($this->syncClusterRate($cluster)) {
                $activated->push($cluster->id);
            }
        });

        return $activated;
    }
}
