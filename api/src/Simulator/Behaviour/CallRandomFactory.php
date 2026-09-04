<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Builds the random source for one call, honouring `PARTNER_SIMULATION_SEED`
 * (specs/04-providers.md section 5.1).
 *
 * When the seed is set, the engine is seeded per call from the seed and the
 * call itself rather than once per process. The simulator serves each partner
 * call as a separate request across a pool of workers, so a generator held in a
 * service has no sequence to advance: it would be reconstructed from the seed
 * on every request, and every call would draw the same outcome. Keying the
 * engine on the call instead makes a given request reproducible no matter which
 * worker serves it or in what order the four partners are dispatched, which is
 * the property acceptance tests actually need.
 *
 * When the seed is unset the engine is seeded unpredictably, which is the dev
 * and demo mode where partner problems should be a surprise.
 */
final readonly class CallRandomFactory
{
    public function __construct(
        #[Autowire(env: 'default::PARTNER_SIMULATION_SEED')]
        private ?string $seed,
    ) {
    }

    public function forCall(string $callKey): CallRandom
    {
        if (null === $this->seed || '' === $this->seed) {
            return new CallRandom(new Randomizer(new Mt19937()));
        }

        return new CallRandom(new Randomizer(new Mt19937(crc32($this->seed.'|'.$callKey))));
    }
}
