<?php

namespace App\Services;

/**
 * Menampung unit yang cache collection account-nya perlu di-refresh selama satu request/command,
 * lalu me-refresh sekali secara batch di akhir siklus (app terminating). Dipakai untuk perubahan
 * massal (mis. approve tagihan bulanan) agar tidak me-refresh unit yang sama berkali-kali.
 */
class CollectionAccountRefreshQueue
{
    /** @var array<string, true> */
    private array $pending = [];

    public function __construct(private readonly CollectionAccountService $accountService) {}

    public function push(string $unitId): void
    {
        $this->pending[$unitId] = true;
    }

    public function flush(): int
    {
        if ($this->pending === []) {
            return 0;
        }

        $unitIds = array_keys($this->pending);
        $this->pending = [];

        return $this->accountService->refreshMany($unitIds);
    }
}
