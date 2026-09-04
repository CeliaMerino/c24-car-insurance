<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

use InvalidArgumentException;

final readonly class LatencyRange
{
    public function __construct(
        public int $minMs,
        public int $maxMs,
    ) {
        if ($minMs < 0) {
            throw new InvalidArgumentException('Latency cannot be negative.');
        }

        if ($maxMs < $minMs) {
            throw new InvalidArgumentException('Latency range ends before it starts.');
        }
    }
}
