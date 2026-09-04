<?php

declare(strict_types=1);

namespace App\Tests\Simulator\Pricing;

use App\Simulator\Quote\QuoteInput;
use App\Tests\Support\QuoteRequestBuilder;
use App\Tests\Support\ReferenceDates;
use App\Tests\Support\SimulatorPricingEngines;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * L2. The two mandatory regression vectors of specs/04-providers.md section 6,
 * pinned to a reference date of 2026-08-31.
 *
 * The ordering fully inverts between them: bastion moves from second-cheapest
 * to most expensive and dorsal from most expensive to cheapest. If a factor
 * table is edited carelessly, that inversion is what breaks first, so it is
 * asserted as well as the eight prices.
 */
final class PricingVectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function vectorPrices(): iterable
    {
        // 6.1 — urban low-mileage sedan.
        yield 'A celeris' => ['A', 'celeris', 47_800];
        yield 'A bastion' => ['A', 'bastion', 48_600];
        yield 'A aurum' => ['A', 'aurum', 56_900];
        yield 'A dorsal' => ['A', 'dorsal', 61_300];

        // 6.2 — rural high-mileage commercial van.
        yield 'B dorsal' => ['B', 'dorsal', 97_900];
        yield 'B celeris' => ['B', 'celeris', 105_800];
        yield 'B aurum' => ['B', 'aurum', 155_300];
        yield 'B bastion' => ['B', 'bastion', 192_400];
    }

    #[DataProvider('vectorPrices')]
    public function testPartnerQuotesTheSpecifiedPrice(string $vector, string $partnerId, int $expectedCents): void
    {
        $price = SimulatorPricingEngines::byId($partnerId)->priceCents(self::input($vector));

        self::assertSame($expectedCents, $price);
    }

    public function testTheOrderingInvertsBetweenTheTwoVectors(): void
    {
        self::assertSame(['celeris', 'bastion', 'aurum', 'dorsal'], self::cheapestFirst('A'));
        self::assertSame(['dorsal', 'celeris', 'aurum', 'bastion'], self::cheapestFirst('B'));
    }

    /**
     * @return list<string>
     */
    private static function cheapestFirst(string $vector): array
    {
        $input = self::input($vector);
        $prices = [];

        foreach (SimulatorPricingEngines::all() as $engine) {
            $prices[$engine->partnerId()] = $engine->priceCents($input);
        }

        asort($prices);

        return array_keys($prices);
    }

    private static function input(string $vector): QuoteInput
    {
        $builder = 'A' === $vector ? QuoteRequestBuilder::vectorA() : QuoteRequestBuilder::vectorB();

        return QuoteInput::fromPayload($builder->toPayload(), ReferenceDates::frozenSimulatorDate());
    }
}
