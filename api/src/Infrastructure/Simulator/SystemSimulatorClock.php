<?php

declare(strict_types=1);

namespace App\Infrastructure\Simulator;

use App\Simulator\Clock\SimulatorClock;
use App\Simulator\Clock\SimulatorDate;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * The simulator's clock, here rather than in `src/Simulator` because
 * Infrastructure is the only layer allowed to construct a date
 * (specs/03-architecture.md section 2.3). The dependency points inward: the
 * simulator declares the interface and never learns of this class.
 */
#[AsAlias(SimulatorClock::class)]
final class SystemSimulatorClock implements SimulatorClock
{
    public function today(): SimulatorDate
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return new SimulatorDate(
            (int) $now->format('Y'),
            (int) $now->format('n'),
            (int) $now->format('j'),
        );
    }
}
