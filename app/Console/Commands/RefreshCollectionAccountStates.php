<?php

namespace App\Console\Commands;

use App\Services\CollectionAccountService;
use Illuminate\Console\Command;

class RefreshCollectionAccountStates extends Command
{
    protected $signature = 'collection:refresh-account-states {--unit=* : Hanya unit tertentu}';

    protected $description = 'Hitung ulang cache status penagihan (outstanding, aging, prioritas) per unit';

    public function handle(CollectionAccountService $service): int
    {
        $units = array_filter((array) $this->option('unit'));

        $count = $units
            ? $service->refreshMany($units)
            : $service->refreshAll();

        $this->info("Collection account states refreshed: {$count}");

        return self::SUCCESS;
    }
}
