<?php

declare(strict_types=1);

namespace App\Domain\Offer;

use App\Domain\Campaign\Campaign;
use App\Domain\Campaign\Discount;
use App\Domain\Quote\CoverageLevel;
use App\Domain\Shared\ReferenceDate;

final readonly class Offer
{
    public function __construct(
        public PartnerId $partnerId,
        public CoverageLevel $coverage,
        public Money $basePrice,
        public Money $finalPrice,
        public ?Discount $discount,
    ) {
    }

    public static function fromPartnerQuote(PartnerId $partnerId, CoverageLevel $coverage, Money $basePrice): self
    {
        return new self($partnerId, $coverage, $basePrice, $basePrice, null);
    }

    public function withCampaign(?Campaign $campaign, ReferenceDate $on): self
    {
        if ($campaign === null || !$campaign->isActiveAt($on)) {
            return new self(
                $this->partnerId,
                $this->coverage,
                $this->basePrice,
                $this->basePrice,
                null,
            );
        }

        if ($campaign->partnerId !== $this->partnerId) {
            return $this;
        }

        $finalPrice = $this->basePrice->applyDiscountPercentage($campaign->percentage);

        return new self(
            $this->partnerId,
            $this->coverage,
            $this->basePrice,
            $finalPrice,
            new Discount($campaign->percentage, $campaign->label),
        );
    }
}
