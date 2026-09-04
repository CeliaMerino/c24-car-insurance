<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Simulator\Pricing\AurumPricingEngine;
use App\Simulator\Pricing\BastionPricingEngine;
use App\Simulator\Pricing\CelerisPricingEngine;
use App\Simulator\Pricing\DorsalPricingEngine;
use App\Simulator\Pricing\PricingEngine;
use App\Simulator\Pricing\PricingEngineRegistry;

final class SimulatorPricingEngines
{
    /**
     * @return list<PricingEngine>
     */
    public static function all(): array
    {
        return [
            new AurumPricingEngine(),
            new BastionPricingEngine(),
            new CelerisPricingEngine(),
            new DorsalPricingEngine(),
        ];
    }

    public static function byId(string $partnerId): PricingEngine
    {
        $engine = (new PricingEngineRegistry(self::all()))->find($partnerId);

        if (null === $engine) {
            throw new \InvalidArgumentException(sprintf('No pricing engine for partner "%s".', $partnerId));
        }

        return $engine;
    }
}
