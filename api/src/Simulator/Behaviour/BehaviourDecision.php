<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

/**
 * What one call is going to do. Latency and outcome are drawn independently, so
 * a call may be slow and then fail.
 */
final readonly class BehaviourDecision
{
    public function __construct(
        public int $latencyMs,
        public ResponseOutcome $outcome,
        public int $httpErrorStatus,
    ) {
    }
}
