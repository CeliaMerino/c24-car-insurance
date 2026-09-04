<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Quote\AnnualMileage;
use App\Domain\Quote\CarCategory;
use App\Domain\Quote\CoverageLevel;
use App\Domain\Quote\DateOfBirth;
use App\Domain\Quote\PostalCode;
use App\Domain\Quote\QuoteRequest;
use App\Domain\Quote\Usage;
use App\Domain\Shared\ReferenceDate;

final class QuoteRequestBuilder
{
    private string $dateOfBirth = '1990-05-14';
    private string $postalCode = '28013';
    private CarCategory $carCategory = CarCategory::Sedan;
    private Usage $usage = Usage::Private;
    private AnnualMileage $annualMileage = AnnualMileage::From5kTo15k;
    private bool $garage = true;
    private CoverageLevel $coverage = CoverageLevel::ThirdPartyPlus;
    private ReferenceDate $referenceDate;

    private function __construct()
    {
        $this->referenceDate = ReferenceDates::frozen();
    }

    public static function create(): self
    {
        return new self();
    }

    /** Urban low-mileage sedan — specs/04-providers.md section 6.1. */
    public static function vectorA(): self
    {
        return self::create();
    }

    /** Rural high-mileage commercial van — specs/04-providers.md section 6.2. */
    public static function vectorB(): self
    {
        return self::create()
            ->withDateOfBirth('1981-03-02')
            ->withPostalCode('15001')
            ->withCarCategory(CarCategory::Van)
            ->withUsage(Usage::Commercial)
            ->withAnnualMileage(AnnualMileage::Over30k)
            ->withGarage(false)
            ->withCoverage(CoverageLevel::Comprehensive);
    }

    public function withDateOfBirth(string $dateOfBirth): self
    {
        $clone = clone $this;
        $clone->dateOfBirth = $dateOfBirth;

        return $clone;
    }

    public function withPostalCode(string $postalCode): self
    {
        $clone = clone $this;
        $clone->postalCode = $postalCode;

        return $clone;
    }

    public function withCarCategory(CarCategory $carCategory): self
    {
        $clone = clone $this;
        $clone->carCategory = $carCategory;

        return $clone;
    }

    public function withUsage(Usage $usage): self
    {
        $clone = clone $this;
        $clone->usage = $usage;

        return $clone;
    }

    public function withAnnualMileage(AnnualMileage $annualMileage): self
    {
        $clone = clone $this;
        $clone->annualMileage = $annualMileage;

        return $clone;
    }

    public function withGarage(bool $garage): self
    {
        $clone = clone $this;
        $clone->garage = $garage;

        return $clone;
    }

    public function withCoverage(CoverageLevel $coverage): self
    {
        $clone = clone $this;
        $clone->coverage = $coverage;

        return $clone;
    }

    public function withReferenceDate(ReferenceDate $referenceDate): self
    {
        $clone = clone $this;
        $clone->referenceDate = $referenceDate;

        return $clone;
    }

    /**
     * The same request as `build()`, in the wire shape of
     * specs/05-api-contract.md section 2.1. This is what the simulator and the
     * API endpoint both receive, so vector data is written once.
     *
     * Unlike `build()` it applies no validation, which is what lets an age
     * boundary below 18 be expressed.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'date_of_birth' => $this->dateOfBirth,
            'postal_code' => $this->postalCode,
            'car_category' => $this->carCategory->value,
            'usage' => $this->usage->value,
            'annual_mileage' => $this->annualMileage->value,
            'garage' => $this->garage,
            'coverage' => $this->coverage->value,
        ];
    }

    public function build(): QuoteRequest
    {
        $reference = $this->referenceDate;

        return new QuoteRequest(
            DateOfBirth::fromString($this->dateOfBirth, $reference->year, $reference->month, $reference->day),
            PostalCode::fromString($this->postalCode),
            $this->carCategory,
            $this->usage,
            $this->annualMileage,
            $this->garage,
            $this->coverage,
        );
    }
}
