<?php

declare(strict_types=1);

namespace App\Domain\Quote;

final readonly class QuoteRequest
{
    public function __construct(
        public DateOfBirth $dateOfBirth,
        public PostalCode $postalCode,
        public CarCategory $carCategory,
        public Usage $usage,
        public AnnualMileage $annualMileage,
        public bool $garage,
        public CoverageLevel $coverage,
    ) {
    }
}
