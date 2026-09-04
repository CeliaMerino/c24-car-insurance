<?php

declare(strict_types=1);

namespace App\Infrastructure\Clock;

use App\Application\Port\Clock;
use App\Domain\Shared\ReferenceDate;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\WhenNot;

/**
 * Process time. Infrastructure is the only layer allowed to construct a date
 * (specs/03-architecture.md section 2.3).
 */
#[WhenNot(env: 'test')]
#[AsAlias(Clock::class)]
final class SystemClock implements Clock
{
    public function today(): ReferenceDate
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return new ReferenceDate(
            (int) $now->format('Y'),
            (int) $now->format('n'),
            (int) $now->format('j'),
        );
    }

    public function monotonicMs(): int
    {
        return (int) (hrtime(true) / 1_000_000);
    }
}
