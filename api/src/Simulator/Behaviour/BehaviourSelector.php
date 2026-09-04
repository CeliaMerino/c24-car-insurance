<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Decides what one call does: the probabilistic profile of
 * specs/04-providers.md section 4, overridden by the forced behaviour of
 * section 5.2 when one is set.
 */
final readonly class BehaviourSelector
{
    public function __construct(
        private CallRandomFactory $randomFactory,
        private ForcedBehaviourReader $forcedBehaviour,
        #[Autowire(env: 'int:PARTNER_FORCED_SLOW_MS')]
        private int $forcedSlowMs,
        #[Autowire(env: 'int:PARTNER_FORCED_TIMEOUT_MS')]
        private int $forcedTimeoutMs,
    ) {
    }

    public function decide(BehaviourProfile $profile, string $callKey): BehaviourDecision
    {
        $forced = $this->forcedBehaviour->forPartner($profile->partnerId);

        if (null !== $forced) {
            return $this->forced($profile, $forced);
        }

        $random = $this->randomFactory->forCall($callKey);

        return new BehaviourDecision(
            $this->drawLatencyMs($profile, $random),
            $this->drawOutcome($profile, $random),
            $profile->httpErrorStatus,
        );
    }

    private function forced(BehaviourProfile $profile, ForcedBehaviour $forced): BehaviourDecision
    {
        $status = $profile->httpErrorStatus;

        return match ($forced) {
            ForcedBehaviour::Ok => new BehaviourDecision(0, ResponseOutcome::Success, $status),
            ForcedBehaviour::Slow => new BehaviourDecision($this->forcedSlowMs, ResponseOutcome::Success, $status),
            ForcedBehaviour::Timeout => new BehaviourDecision($this->forcedTimeoutMs, ResponseOutcome::Success, $status),
            ForcedBehaviour::HttpError => new BehaviourDecision(0, ResponseOutcome::HttpError, $status),
            ForcedBehaviour::Malformed => new BehaviourDecision(0, ResponseOutcome::Malformed, $status),
            ForcedBehaviour::NonJson => new BehaviourDecision(0, ResponseOutcome::NonJson, $status),
            ForcedBehaviour::ConnectionError => new BehaviourDecision(0, ResponseOutcome::ConnectionError, $status),
        };
    }

    private function drawLatencyMs(BehaviourProfile $profile, CallRandom $random): int
    {
        $range = $profile->normalLatency;

        if (null !== $profile->spike && $random->draw() < CallRandom::scaled($profile->spike->probability)) {
            $range = $profile->spike->range;
        }

        return $random->intBetween($range->minMs, $range->maxMs);
    }

    private function drawOutcome(BehaviourProfile $profile, CallRandom $random): ResponseOutcome
    {
        $draw = $random->draw();
        $threshold = 0;

        foreach ($profile->failureModes as $mode) {
            $threshold += CallRandom::scaled($mode->probability);

            if ($draw < $threshold) {
                return $mode->outcome;
            }
        }

        return ResponseOutcome::Success;
    }
}
