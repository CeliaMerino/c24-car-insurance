<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use App\Application\Port\Clock;
use App\Domain\Shared\ReferenceDate;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * Calendar date frozen at the test instant of specs/06-testing.md section 2.
 * Monotonic readings still come from the process so comparison duration is real.
 */
#[When(env: 'test')]
#[AsAlias(Clock::class)]
final class FrozenClock implements Clock
{
    public function today(): ReferenceDate
    {
        return new ReferenceDate(2026, 8, 31);
    }

    public function monotonicMs(): int
    {
        return (int) (hrtime(true) / 1_000_000);
    }
}
