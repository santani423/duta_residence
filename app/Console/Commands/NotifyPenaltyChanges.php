<?php

namespace App\Console\Commands;

use App\Services\PenaltyNotificationService;
use Illuminate\Console\Command;

class NotifyPenaltyChanges extends Command
{
    protected $signature = 'billings:notify-penalty-changes';

    protected $description = 'Send a notification whenever an outstanding invoice crosses into a new overdue/penalty tier';

    public function handle(PenaltyNotificationService $service): int
    {
        $count = $service->notifyDueChanges();
        $this->info("Penalty tier notifications sent: {$count}");

        return self::SUCCESS;
    }
}
