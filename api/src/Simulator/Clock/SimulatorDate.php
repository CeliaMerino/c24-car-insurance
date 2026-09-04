<?php

declare(strict_types=1);

namespace App\Simulator\Clock;

final readonly class SimulatorDate
{
    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
    }
}
