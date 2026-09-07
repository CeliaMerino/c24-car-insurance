<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\Clock;
use App\Domain\Shared\ReferenceDate;

final class FakeClock implements Clock
{
    public function __construct(
        private readonly ReferenceDate $today,
        private int $monotonicMs = 0,
        private readonly int $stepMs = 0,
    ) {
    }

    public function today(): ReferenceDate
    {
        return $this->today;
    }

    public function monotonicMs(): int
    {
        $now = $this->monotonicMs;
        $this->monotonicMs += $this->stepMs;

        return $now;
    }

    public function advanceMs(int $milliseconds): void
    {
        $this->monotonicMs += $milliseconds;
    }
}
