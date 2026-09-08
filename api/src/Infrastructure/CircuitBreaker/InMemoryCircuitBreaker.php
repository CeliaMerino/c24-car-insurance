<?php

declare(strict_types=1);

namespace App\Infrastructure\CircuitBreaker;

use App\Application\Port\CircuitBreaker;
use App\Application\Port\Clock;
use App\Domain\Comparison\CircuitBreakerTransition;
use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\PartnerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * In-memory circuit breaker (specs/03-architecture.md section 3.5).
 *
 * This state is per FrankenPHP worker. Each worker keeps its own counters, so
 * with N workers a partner opens after roughly 3N failures overall and
 * different workers disagree about its state. Production requires shared
 * state in Redis.
 */
#[AsAlias(CircuitBreaker::class)]
final class InMemoryCircuitBreaker implements CircuitBreaker
{
    private const int CLOSED = 0;
    private const int OPEN = 1;
    private const int HALF_OPEN = 2;

    /** @var array<string, int> */
    private array $consecutiveFailures = [];

    /** @var array<string, int> */
    private array $state = [];

    /** @var array<string, int> monotonic ms at which the circuit opened */
    private array $openedAtMs = [];

    public function __construct(
        private readonly Clock $clock,
        #[Autowire('%env(int:CIRCUIT_BREAKER_THRESHOLD)%')]
        private readonly int $failureThreshold,
        #[Autowire('%env(int:CIRCUIT_BREAKER_COOLDOWN_S)%')]
        private readonly int $cooldownSeconds,
    ) {
    }

    public function callablePartners(array $partnerIds): array
    {
        $callable = [];

        foreach ($partnerIds as $partnerId) {
            if ($this->isCallable($partnerId)) {
                $callable[] = $partnerId;
            }
        }

        return $callable;
    }

    public function recordOutcome(PartnerId $partnerId, PartnerStatus $status): ?CircuitBreakerTransition
    {
        if (PartnerStatus::Skipped === $status) {
            return null;
        }

        $key = $partnerId->value;

        if (PartnerStatus::Ok === $status) {
            $previous = $this->state[$key] ?? self::CLOSED;
            unset($this->consecutiveFailures[$key], $this->state[$key], $this->openedAtMs[$key]);

            if (self::CLOSED !== $previous) {
                return CircuitBreakerTransition::Closed;
            }

            return null;
        }

        $wasHalfOpen = self::HALF_OPEN === ($this->state[$key] ?? self::CLOSED);
        $alreadyOpen = self::OPEN === ($this->state[$key] ?? self::CLOSED);
        $this->consecutiveFailures[$key] = ($this->consecutiveFailures[$key] ?? 0) + 1;

        if ($wasHalfOpen || $this->consecutiveFailures[$key] >= $this->failureThreshold) {
            $this->state[$key] = self::OPEN;
            $this->openedAtMs[$key] = $this->clock->monotonicMs();

            if (!$alreadyOpen) {
                return CircuitBreakerTransition::Opened;
            }
        }

        return null;
    }

    private function isCallable(PartnerId $partnerId): bool
    {
        $key = $partnerId->value;
        $state = $this->state[$key] ?? self::CLOSED;

        if (self::CLOSED === $state) {
            return true;
        }

        if (self::HALF_OPEN === $state) {
            return true;
        }

        $elapsedMs = $this->clock->monotonicMs() - $this->openedAtMs[$key];
        if ($elapsedMs >= $this->cooldownSeconds * 1000) {
            $this->state[$key] = self::HALF_OPEN;

            return true;
        }

        return false;
    }
}
