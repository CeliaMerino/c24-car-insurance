<?php

declare(strict_types=1);

namespace App\Infrastructure\Simulator;

use App\Simulator\Clock\SimulatorClock;
use App\Simulator\Clock\SimulatorDate;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * The simulator's copy of the frozen test instant of specs/06-testing.md
 * section 2, so vector prices do not move with the wall clock.
 */
#[When(env: 'test')]
#[AsAlias(SimulatorClock::class)]
final class FrozenSimulatorClock implements SimulatorClock
{
    public function today(): SimulatorDate
    {
        return new SimulatorDate(2026, 8, 31);
    }
}
