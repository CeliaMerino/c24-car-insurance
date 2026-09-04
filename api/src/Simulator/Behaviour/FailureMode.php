<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

final readonly class FailureMode
{
    public function __construct(
        public ResponseOutcome $outcome,
        public float $probability,
    ) {
    }
}
