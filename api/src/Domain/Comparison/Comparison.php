<?php

declare(strict_types=1);

namespace App\Domain\Comparison;

use App\Domain\Offer\Offer;
use App\Domain\Quote\CoverageLevel;

final readonly class Comparison
{
    /**
     * @param list<Offer> $offers
     * @param list<PartnerOutcome> $partnerOutcomes
     */
    public function __construct(
        public string $comparisonId,
        public CoverageLevel $coverage,
        public int $durationMs,
        public array $offers,
        public array $partnerOutcomes,
    ) {
    }
}
