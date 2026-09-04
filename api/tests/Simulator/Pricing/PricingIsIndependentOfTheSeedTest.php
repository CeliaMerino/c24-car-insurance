<?php

declare(strict_types=1);

namespace App\Tests\Simulator\Pricing;

use App\Simulator\Quote\QuoteInput;
use App\Tests\Support\QuoteRequestBuilder;
use App\Tests\Support\ReferenceDates;
use App\Tests\Support\SimulatorPricingEngines;
use PHPUnit\Framework\TestCase;

/**
 * L2. `PARTNER_SIMULATION_SEED` fixes latency and failure and must never touch
 * pricing (specs/04-providers.md section 5.1). A test that changes the seed and
 * sees a price move has found a bug (specs/06-testing.md section 2).
 */
final class PricingIsIndependentOfTheSeedTest extends TestCase
{
    private const string SEED = 'PARTNER_SIMULATION_SEED';

    public function testChangingTheSeedDoesNotMoveAPrice(): void
    {
        $input = self::vectorA();
        $expected = self::pricesFrom($input);

        foreach (['', '1', '20260831', '99999999'] as $seed) {
            $this->withSeed($seed);

            self::assertSame($expected, self::pricesFrom($input), sprintf('Seed "%s" moved a price.', $seed));
        }
    }

    public function testAPriceIsAPureFunctionOfTheRequest(): void
    {
        $payload = QuoteRequestBuilder::vectorB()->toPayload();
        $today = ReferenceDates::frozenSimulatorDate();

        $first = self::pricesFrom(QuoteInput::fromPayload($payload, $today));
        $second = self::pricesFrom(QuoteInput::fromPayload($payload, $today));

        self::assertSame($first, $second);
    }

    protected function tearDown(): void
    {
        unset($_ENV[self::SEED], $_SERVER[self::SEED]);

        parent::tearDown();
    }

    private function withSeed(string $seed): void
    {
        $_ENV[self::SEED] = $seed;
        $_SERVER[self::SEED] = $seed;
    }

    /**
     * @return array<string, int>
     */
    private static function pricesFrom(QuoteInput $input): array
    {
        $prices = [];

        foreach (SimulatorPricingEngines::all() as $engine) {
            $prices[$engine->partnerId()] = $engine->priceCents($input);
        }

        return $prices;
    }

    private static function vectorA(): QuoteInput
    {
        return QuoteInput::fromPayload(
            QuoteRequestBuilder::vectorA()->toPayload(),
            ReferenceDates::frozenSimulatorDate(),
        );
    }
}
