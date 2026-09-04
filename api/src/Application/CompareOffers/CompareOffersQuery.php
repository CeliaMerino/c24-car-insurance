<?php

declare(strict_types=1);

namespace App\Application\CompareOffers;

use App\Domain\Quote\QuoteRequest;

final readonly class CompareOffersQuery
{
    public function __construct(
        public QuoteRequest $request,
        public string $comparisonId,
    ) {
    }
}
