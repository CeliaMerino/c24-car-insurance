<?php

declare(strict_types=1);

namespace App\Domain\Comparison;

/**
 * State change reported by the circuit breaker so the handler can log and
 * count opens (specs/07-observability.md sections 3.1 and 4).
 */
enum CircuitBreakerTransition: string
{
    case Opened = 'open';
    case Closed = 'closed';
}
