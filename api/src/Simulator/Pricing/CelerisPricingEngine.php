<?php

declare(strict_types=1);

namespace App\Simulator\Pricing;

use App\Simulator\Quote\AnnualMileage;
use App\Simulator\Quote\CarCategory;
use App\Simulator\Quote\CoverageLevel;
use App\Simulator\Quote\QuoteInput;
use App\Simulator\Quote\Region;
use App\Simulator\Quote\Usage;

/**
 * Celeris Seguros — the age specialist. The most aggressive age curve of the
 * four, the only partner that discounts below its base in the 35–54 band, and
 * the only one that discounts standard regions. Factor table:
 * specs/04-providers.md section 3.3.
 */
final class CelerisPricingEngine implements PricingEngine
{
    private const int BASE_EUROS = 400;

    public function partnerId(): string
    {
        return 'celeris';
    }

    public function priceCents(QuoteInput $input): int
    {
        return PremiumFormula::cents(self::BASE_EUROS, [
            $this->age($input->ageYears),
            $this->category($input->carCategory),
            $this->usage($input->usage),
            $this->mileage($input->annualMileage),
            $this->garage($input->garage),
            $this->region($input->region),
            $this->coverage($input->coverage),
        ]);
    }

    private function age(int $years): float
    {
        return match (true) {
            $years <= 24 => 1.90,
            $years <= 34 => 1.30,
            $years <= 54 => 0.90,
            $years <= 69 => 1.05,
            default => 1.50,
        };
    }

    private function category(CarCategory $category): float
    {
        return match ($category) {
            CarCategory::Compact => 0.95,
            CarCategory::Sedan => 1.00,
            CarCategory::Suv => 1.10,
            CarCategory::Van => 1.20,
        };
    }

    private function usage(Usage $usage): float
    {
        return match ($usage) {
            Usage::Private => 1.00,
            Usage::Commercial => 1.25,
        };
    }

    private function mileage(AnnualMileage $mileage): float
    {
        return match ($mileage) {
            AnnualMileage::Under5k => 0.92,
            AnnualMileage::From5kTo15k => 1.00,
            AnnualMileage::From15kTo30k => 1.12,
            AnnualMileage::Over30k => 1.25,
        };
    }

    private function garage(bool $garage): float
    {
        return $garage ? 0.90 : 1.00;
    }

    private function region(Region $region): float
    {
        return match ($region) {
            Region::HighDensity => 1.18,
            Region::Standard => 0.98,
        };
    }

    private function coverage(CoverageLevel $coverage): float
    {
        return match ($coverage) {
            CoverageLevel::ThirdParty => 1.00,
            CoverageLevel::ThirdPartyPlus => 1.25,
            CoverageLevel::Comprehensive => 1.60,
        };
    }
}
