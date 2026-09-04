<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the `simulated_partners` configuration of specs/04-providers.md
 * section 7 into typed profiles, failing loudly on anything it does not
 * recognise.
 */
final class BehaviourProfileRegistry
{
    private const int DEFAULT_HTTP_ERROR_STATUS = 503;

    /** @var array<string, BehaviourProfile> */
    private array $profiles = [];

    /**
     * @param array<string, mixed> $configuredPartners
     */
    public function __construct(
        #[Autowire('%simulator.partners%')]
        array $configuredPartners,
    ) {
        foreach ($configuredPartners as $partnerId => $config) {
            if (!is_array($config)) {
                throw new InvalidArgumentException(sprintf('Partner "%s" must be configured as a mapping.', $partnerId));
            }

            $this->profiles[$partnerId] = self::profile($partnerId, $config);
        }
    }

    public function find(string $partnerId): ?BehaviourProfile
    {
        return $this->profiles[$partnerId] ?? null;
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private static function profile(string $partnerId, array $config): BehaviourProfile
    {
        return new BehaviourProfile(
            $partnerId,
            self::range($config['latency_ms'] ?? null, $partnerId, 'latency_ms'),
            self::spike($config['spike'] ?? null, $partnerId),
            self::status($config['http_error_status'] ?? null, $partnerId),
            self::failureModes($config['failures'] ?? null, $partnerId),
        );
    }

    private static function range(mixed $value, string $partnerId, string $key): LatencyRange
    {
        if (!is_array($value) || !isset($value[0], $value[1]) || !is_int($value[0]) || !is_int($value[1])) {
            throw new InvalidArgumentException(sprintf('Partner "%s" needs "%s" as a pair of integers.', $partnerId, $key));
        }

        return new LatencyRange($value[0], $value[1]);
    }

    private static function spike(mixed $value, string $partnerId): ?LatencySpike
    {
        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Partner "%s" needs "spike" as null or a mapping.', $partnerId));
        }

        return new LatencySpike(
            self::probability($value['probability'] ?? null, $partnerId, 'spike.probability'),
            self::range($value['latency_ms'] ?? null, $partnerId, 'spike.latency_ms'),
        );
    }

    private static function status(mixed $value, string $partnerId): int
    {
        if (null === $value) {
            return self::DEFAULT_HTTP_ERROR_STATUS;
        }

        if (!is_int($value) || $value < 400 || $value > 599) {
            throw new InvalidArgumentException(sprintf('Partner "%s" needs "http_error_status" as a 4xx or 5xx code.', $partnerId));
        }

        return $value;
    }

    /**
     * @return list<FailureMode>
     */
    private static function failureModes(mixed $value, string $partnerId): array
    {
        if (null === $value) {
            return [];
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Partner "%s" needs "failures" as a mapping.', $partnerId));
        }

        $modes = [];
        $total = 0.0;

        /** @var mixed $probability */
        foreach ($value as $name => $probability) {
            $outcome = is_string($name) ? ResponseOutcome::tryFrom($name) : null;

            if (null === $outcome || ResponseOutcome::Success === $outcome) {
                throw new InvalidArgumentException(sprintf('Partner "%s" has an unknown failure mode "%s".', $partnerId, (string) $name));
            }

            $total += $probability = self::probability($probability, $partnerId, 'failures.'.$name);
            $modes[] = new FailureMode($outcome, $probability);
        }

        if ($total > 1.0) {
            throw new InvalidArgumentException(sprintf('Partner "%s" fails more than all of the time.', $partnerId));
        }

        return $modes;
    }

    private static function probability(mixed $value, string $partnerId, string $key): float
    {
        if (!is_float($value) && !is_int($value)) {
            throw new InvalidArgumentException(sprintf('Partner "%s" needs "%s" as a number.', $partnerId, $key));
        }

        $probability = (float) $value;

        if ($probability < 0.0 || $probability > 1.0) {
            throw new InvalidArgumentException(sprintf('Partner "%s" needs "%s" between 0 and 1.', $partnerId, $key));
        }

        return $probability;
    }
}
