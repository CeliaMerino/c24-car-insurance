<?php

declare(strict_types=1);

namespace App\Simulator\Pricing;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Resolves a partner id from the route to its pricing engine. Engines are
 * discovered through the `simulator.pricing_engine` tag, so a fifth partner is
 * a new class and nothing else (specs/04-providers.md section 8).
 */
final class PricingEngineRegistry
{
    /** @var array<string, PricingEngine> */
    private array $engines = [];

    /**
     * @param iterable<PricingEngine> $engines
     */
    public function __construct(
        #[AutowireIterator('simulator.pricing_engine')]
        iterable $engines,
    ) {
        foreach ($engines as $engine) {
            $this->engines[$engine->partnerId()] = $engine;
        }
    }

    public function find(string $partnerId): ?PricingEngine
    {
        return $this->engines[$partnerId] ?? null;
    }

    /**
     * @return list<string>
     */
    public function partnerIds(): array
    {
        return array_keys($this->engines);
    }
}
