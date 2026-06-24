<?php

namespace App\Models\Concerns;

trait HasStringPrimaryKey
{
    public function initializeHasStringPrimaryKey(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
