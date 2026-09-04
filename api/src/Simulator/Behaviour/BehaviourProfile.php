<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

/**
 * How one partner misbehaves — specs/04-providers.md section 4. This is
 * configuration, unlike the factor tables, because it is the partner's
 * operational character rather than its underwriting.
 */
final readonly class BehaviourProfile
{
    /**
     * @param list<FailureMode> $failureModes mutually exclusive, drawn in order against one number
     */
    public function __construct(
        public string $partnerId,
        public LatencyRange $normalLatency,
        public ?LatencySpike $spike,
        public int $httpErrorStatus,
        public array $failureModes,
    ) {
    }
}
