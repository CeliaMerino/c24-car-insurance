<?php

declare(strict_types=1);

namespace App\Simulator\Clock;

/**
 * The simulator's own clock.
 *
 * It cannot use `App\Application\Port\Clock`: the simulator stands in for a
 * third party and imports nothing from Domain or Application
 * (specs/03-architecture.md section 2.1). The implementation lives in
 * Infrastructure, which is the only layer allowed to construct a date.
 */
interface SimulatorClock
{
    public function today(): SimulatorDate;
}
