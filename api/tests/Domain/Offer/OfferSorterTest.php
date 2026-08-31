<?php

declare(strict_types=1);

namespace App\Tests\Domain\Offer;

use App\Domain\Offer\Money;
use App\Domain\Offer\Offer;
use App\Domain\Offer\OfferSorter;
use App\Domain\Offer\PartnerId;
use App\Domain\Quote\CoverageLevel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OfferSorterTest extends TestCase
{
    #[Test]
    public function sorts_by_final_price_ascending(): void
    {
        $cheap = $this->offer(PartnerId::Celeris, 47800);
        $mid = $this->offer(PartnerId::Bastion, 48600);
        $expensive = $this->offer(PartnerId::Aurum, 56900);

        $sorted = OfferSorter::sortByFinalPrice([$expensive, $cheap, $mid]);

        self::assertSame(
            [PartnerId::Celeris, PartnerId::Bastion, PartnerId::Aurum],
            array_map(static fn (Offer $offer): PartnerId => $offer->partnerId, $sorted),
        );
    }

    #[Test]
    public function tie_breaks_by_partner_name_alphabetically(): void
    {
        $aurum = $this->offer(PartnerId::Aurum, 47800);
        $celeris = $this->offer(PartnerId::Celeris, 47800);

        $sorted = OfferSorter::sortByFinalPrice([$celeris, $aurum]);

        self::assertSame(PartnerId::Aurum, $sorted[0]->partnerId);
        self::assertSame(PartnerId::Celeris, $sorted[1]->partnerId);
    }

    private function offer(PartnerId $partnerId, int $finalCents): Offer
    {
        $price = new Money($finalCents);

        return new Offer(
            $partnerId,
            CoverageLevel::ThirdPartyPlus,
            $price,
            $price,
            null,
        );
    }
}
