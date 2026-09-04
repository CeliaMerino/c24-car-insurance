<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Shared\ReferenceDate;

interface Clock
{
    public function today(): ReferenceDate;

    /**
     * Monotonic milliseconds. Subtract two readings to get an elapsed duration.
     * The absolute value has no meaning.
     */
    public function monotonicMs(): int;
}
