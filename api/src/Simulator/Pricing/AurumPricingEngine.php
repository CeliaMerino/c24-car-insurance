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
 * Aurum Direct — the complete underwriter. Uses every factor and weights
 * nothing extremely. Factor table: specs/04-providers.md section 3.1.
 */
final class AurumPricingEngine implements PricingEngine
{
    private const int BASE_EUROS = 420;

    public function partnerId(): string
    {
        return 'aurum';
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
            $years <= 24 => 1.55,
            $years <= 34 => 1.20,
            $years <= 54 => 1.00,
            $years <= 69 => 1.10,
            default => 1.35,
        };
    }

    private function category(CarCategory $category): float
    {
        return match ($category) {
            CarCategory::Compact => 0.95,
            CarCategory::Sedan => 1.00,
            CarCategory::Suv => 1.15,
            CarCategory::Van => 1.25,
        };
    }

    private function usage(Usage $usage): float
    {
        return match ($usage) {
            Usage::Private => 1.00,
            Usage::Commercial => 1.30,
        };
    }

    private function mileage(AnnualMileage $mileage): float
    {
        return match ($mileage) {
            AnnualMileage::Under5k => 0.90,
            AnnualMileage::From5kTo15k => 1.00,
            AnnualMileage::From15kTo30k => 1.15,
            AnnualMileage::Over30k => 1.30,
        };
    }

    private function garage(bool $garage): float
    {
        return $garage ? 0.93 : 1.00;
    }

    private function region(Region $region): float
    {
        return match ($region) {
            Region::HighDensity => 1.12,
            Region::Standard => 1.00,
        };
    }

    private function coverage(CoverageLevel $coverage): float
    {
        return match ($coverage) {
            CoverageLevel::ThirdParty => 1.00,
            CoverageLevel::ThirdPartyPlus => 1.30,
            CoverageLevel::Comprehensive => 1.75,
        };
    }
}
