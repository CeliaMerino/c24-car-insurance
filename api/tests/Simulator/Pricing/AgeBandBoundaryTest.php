<?php

declare(strict_types=1);

namespace App\Tests\Simulator\Pricing;

use App\Domain\Quote\CoverageLevel;
use App\Simulator\Quote\QuoteInput;
use App\Tests\Support\QuoteRequestBuilder;
use App\Tests\Support\ReferenceDates;
use App\Tests\Support\SimulatorPricingEngines;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * L2. The age band boundaries of specs/06-testing.md section 8: 17/18, 24/25,
 * 34/35, 54/55, 69/70.
 *
 * Each pair is one year apart across a band edge, and each date of birth is
 * chosen so that the customer either has or has not had their birthday by the
 * reference date of 2026-08-31. An off-by-one in a band, or a birthday
 * comparison that ignores the day, moves one of the two.
 *
 * The rest of the request is chosen so that every other factor is 1.00 and the
 * price is the base premium times the age factor alone. Celeris is the
 * exception: it is the only partner that discounts a standard region, so its
 * prices carry a further 0.98.
 *
 * 17 is included because the specification lists it, but the simulator prices
 * it rather than rejecting it. Refusing an underage customer is the API's rule
 * and is covered at L1; a partner never sees such a request.
 */
final class AgeBandBoundaryTest extends TestCase
{
    /** @var array<int, string> age at 2026-08-31 => date of birth */
    private const array DATE_OF_BIRTH = [
        17 => '2008-09-01',
        18 => '2008-08-31',
        24 => '2001-09-01',
        25 => '2001-08-31',
        34 => '1991-09-01',
        35 => '1991-08-31',
        54 => '1971-09-01',
        55 => '1971-08-31',
        69 => '1956-09-01',
        70 => '1956-08-31',
    ];

    /** @var array<string, array<int, int>> partner => age => premium in cents */
    private const array EXPECTED_CENTS = [
        // 420, banded 18–24 · 25–34 · 35–54 · 55–69 · 70+.
        'aurum' => [
            17 => 65_100, 18 => 65_100, 24 => 65_100,
            25 => 50_400, 34 => 50_400,
            35 => 42_000, 54 => 42_000,
            55 => 46_200, 69 => 46_200,
            70 => 56_700,
        ],
        // 360, banded 18–24 · 25–34 · 35+. Flat from 35 on.
        'bastion' => [
            17 => 50_400, 18 => 50_400, 24 => 50_400,
            25 => 41_400, 34 => 41_400,
            35 => 36_000, 54 => 36_000,
            55 => 36_000, 69 => 36_000,
            70 => 36_000,
        ],
        // 400 × 0.98 standard region, banded 18–24 · 25–34 · 35–54 · 55–69 · 70+.
        'celeris' => [
            17 => 74_500, 18 => 74_500, 24 => 74_500,
            25 => 51_000, 34 => 51_000,
            35 => 35_300, 54 => 35_300,
            55 => 41_200, 69 => 41_200,
            70 => 58_800,
        ],
        // 480, banded 18–24 · 25+. Flat from 25 on.
        'dorsal' => [
            17 => 60_000, 18 => 60_000, 24 => 60_000,
            25 => 48_000, 34 => 48_000,
            35 => 48_000, 54 => 48_000,
            55 => 48_000, 69 => 48_000,
            70 => 48_000,
        ],
    ];

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function ageBoundaries(): iterable
    {
        foreach (self::EXPECTED_CENTS as $partnerId => $pricesByAge) {
            foreach ($pricesByAge as $age => $expectedCents) {
                yield sprintf('%s at %d', $partnerId, $age) => [$partnerId, $age, $expectedCents];
            }
        }
    }

    #[DataProvider('ageBoundaries')]
    public function testAgeBandBoundary(string $partnerId, int $age, int $expectedCents): void
    {
        $price = SimulatorPricingEngines::byId($partnerId)->priceCents(self::inputAged($age));

        self::assertSame($expectedCents, $price);
    }

    private static function inputAged(int $age): QuoteInput
    {
        $payload = QuoteRequestBuilder::create()
            ->withDateOfBirth(self::DATE_OF_BIRTH[$age])
            ->withPostalCode('15001')
            ->withGarage(false)
            ->withCoverage(CoverageLevel::ThirdParty)
            ->toPayload();

        return QuoteInput::fromPayload($payload, ReferenceDates::frozenSimulatorDate());
    }
}
