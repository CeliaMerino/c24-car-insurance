<?php

declare(strict_types=1);

namespace App\Simulator\Pricing;

use App\Simulator\Quote\CoverageLevel;
use App\Simulator\Quote\QuoteInput;
use App\Simulator\Quote\Region;
use App\Simulator\Quote\Usage;

/**
 * Dorsal Mutual — the flat-rate insurer. Highest base premium, ignores category
 * and mileage, and barely loads on age. Factor table:
 * specs/04-providers.md section 3.4.
 */
final class DorsalPricingEngine implements PricingEngine
{
    private const int BASE_EUROS = 480;

    /** Category and mileage are ignored, so both are fixed at this. */
    private const float IGNORED = 1.00;

    public function partnerId(): string
    {
        return 'dorsal';
    }

    public function priceCents(QuoteInput $input): int
    {
        return PremiumFormula::cents(self::BASE_EUROS, [
            $this->age($input->ageYears),
            self::IGNORED,
            $this->usage($input->usage),
            self::IGNORED,
            $this->garage($input->garage),
            $this->region($input->region),
            $this->coverage($input->coverage),
        ]);
    }

    private function age(int $years): float
    {
        return match (true) {
            $years <= 24 => 1.25,
            default => 1.00,
        };
    }

    private function usage(Usage $usage): float
    {
        return match ($usage) {
            Usage::Private => 1.00,
            Usage::Commercial => 1.20,
        };
    }

    private function garage(bool $garage): float
    {
        return $garage ? 0.95 : 1.00;
    }

    private function region(Region $region): float
    {
        return match ($region) {
            Region::HighDensity => 1.05,
            Region::Standard => 1.00,
        };
    }

    private function coverage(CoverageLevel $coverage): float
    {
        return match ($coverage) {
            CoverageLevel::ThirdParty => 1.00,
            CoverageLevel::ThirdPartyPlus => 1.28,
            CoverageLevel::Comprehensive => 1.70,
        };
    }
}
