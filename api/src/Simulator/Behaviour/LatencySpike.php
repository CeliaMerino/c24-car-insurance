<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

final readonly class LatencySpike
{
    public function __construct(
        public float $probability,
        public LatencyRange $range,
    ) {
    }
}
