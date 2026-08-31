<?php

declare(strict_types=1);

namespace App\Domain\Comparison;

use App\Domain\Offer\Offer;
use App\Domain\Offer\PartnerId;

final readonly class PartnerOutcome
{
    public function __construct(
        public PartnerId $partnerId,
        public PartnerStatus $status,
        public int $durationMs,
        public ?Offer $offer,
    ) {
    }
}
