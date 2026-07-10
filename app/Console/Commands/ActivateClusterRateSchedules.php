<?php

namespace App\Console\Commands;

use App\Services\ClusterRateScheduleService;
use Illuminate\Console\Command;

class ActivateClusterRateSchedules extends Command
{
    protected $signature = 'clusters:activate-rate-schedules';

    protected $description = 'Activate any cluster rate schedules whose effective date has arrived and sync cluster monthly rates';

    public function handle(ClusterRateScheduleService $service): int
    {
        $activated = $service->activateDueSchedules();

        if ($activated->isEmpty()) {
            $this->info('No cluster rate changes due.');
        } else {
            $this->info('Activated rate schedule for cluster(s): '.$activated->implode(', '));
        }

        return self::SUCCESS;
    }
}
