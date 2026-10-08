<?php

namespace App\Console\Commands;

use App\Models\CollectorReminder;
use App\Models\CollectorVisit;
use App\Models\PaymentPromise;
use App\Models\PaymentTransaction;
use App\Services\CollectionAccountService;
use App\Services\CollectionActivityService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Isi timeline penagihan dari data yang ada sebelum modul collection (visit, PTP, pembayaran,
 * pengingat WA). Idempoten - aman dijalankan berulang karena satu event per subjek.
 */
class BackfillCollectionActivities extends Command
{
    protected $signature = 'collection:backfill-activities
        {--since= : Hanya data yang dibuat sejak tanggal ini (Y-m-d)}
        {--skip-refresh : Jangan hitung ulang status akun setelah backfill}';

    protected $description = 'Isi timeline penagihan dari visit, PTP, pembayaran, dan pengingat WA yang sudah ada';

    public function handle(CollectionActivityService $activities, CollectionAccountService $accounts): int
    {
        $since = $this->option('since');
        $created = 0;
        $failed = 0;

        $sources = [
            'visit' => CollectorVisit::query(),
            'promise' => PaymentPromise::query(),
            'payment' => PaymentTransaction::query()->whereIn('status', ['paid', 'waiting_verification', 'rejected', 'revision_requested']),
            'reminder' => CollectorReminder::query(),
        ];

        foreach ($sources as $label => $query) {
            $count = 0;

            $query->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
                ->orderBy('id')
                ->chunkById(500, function ($models) use ($activities, &$count, &$failed) {
                    foreach ($models as $model) {
                        try {
                            $count += $activities->recordForSubject($model, null, refresh: false) ? 1 : 0;

                            // PTP yang sudah selesai juga dicatat event status akhirnya.
                            if ($model instanceof PaymentPromise && $model->status !== PaymentPromise::STATUS_PENDING) {
                                $count += $activities->recordForSubject($model, $model->status, refresh: false) ? 1 : 0;
                            }
                        } catch (Throwable $e) {
                            $failed++;
                            report($e);
                        }
                    }
                });

            $this->line("{$label}: {$count} aktivitas baru");
            $created += $count;
        }

        $this->info("Total aktivitas baru: {$created}".($failed ? ", gagal: {$failed} (lihat log)" : ''));

        if (! $this->option('skip-refresh')) {
            $this->info('Collection account states refreshed: '.$accounts->refreshAll());
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
