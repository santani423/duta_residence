<?php

namespace App\Console\Commands;

use App\Services\SupervisorNotificationGeneratorService;
use Illuminate\Console\Command;

class SupervisorGenerateNotifications extends Command
{
    protected $signature = 'supervisor:generate-notifications';

    protected $description = 'Scan operational tables and open Supervisor notifications for conditions requiring attention';

    public function handle(SupervisorNotificationGeneratorService $service): int
    {
        $count = $service->generate();
        $this->info("Supervisor notifications created: {$count}");

        return self::SUCCESS;
    }
}
