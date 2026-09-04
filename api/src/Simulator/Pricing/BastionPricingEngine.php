<?php

declare(strict_types=1);

namespace App\Simulator\Pricing;

use App\Simulator\Quote\AnnualMileage;
use App\Simulator\Quote\CarCategory;
use App\Simulator\Quote\CoverageLevel;
use App\Simulator\Quote\QuoteInput;
use App\Simulator\Quote\Usage;

/**
 * Bastion Insurance — the cheap and blunt one. Lowest base premium, ignores
 * garage and region, loads heavily on vehicle and mileage, and has the steepest
 * coverage ladder of the four. Factor table: specs/04-providers.md section 3.2.
 */
final class BastionPricingEngine implements PricingEngine
{
    private const int BASE_EUROS = 360;

    /** Garage and region are ignored, so both are fixed at this. */
    private const float IGNORED = 1.00;

    public function partnerId(): string
    {
        return 'bastion';
    }

    public function priceCents(QuoteInput $input): int
    {
        return PremiumFormula::cents(self::BASE_EUROS, [
            $this->age($input->ageYears),
            $this->category($input->carCategory),
            $this->usage($input->usage),
            $this->mileage($input->annualMileage),
            self::IGNORED,
            self::IGNORED,
            $this->coverage($input->coverage),
        ]);
    }

    private function age(int $years): float
    {
        return match (true) {
            $years <= 24 => 1.40,
            $years <= 34 => 1.15,
            default => 1.00,
        };
    }

    private function category(CarCategory $category): float
    {
        return match ($category) {
            CarCategory::Compact => 0.90,
            CarCategory::Sedan => 1.00,
            CarCategory::Suv => 1.20,
            CarCategory::Van => 1.35,
        };
    }

    private function usage(Usage $usage): float
    {
        return match ($usage) {
            Usage::Private => 1.00,
            Usage::Commercial => 1.45,
        };
    }

    private function mileage(AnnualMileage $mileage): float
    {
        return match ($mileage) {
            AnnualMileage::Under5k => 0.85,
            AnnualMileage::From5kTo15k => 1.00,
            AnnualMileage::From15kTo30k => 1.20,
            AnnualMileage::Over30k => 1.40,
        };
    }

    private function coverage(CoverageLevel $coverage): float
    {
        return match ($coverage) {
            CoverageLevel::ThirdParty => 1.00,
            CoverageLevel::ThirdPartyPlus => 1.35,
            CoverageLevel::Comprehensive => 1.95,
        };
    }
}
