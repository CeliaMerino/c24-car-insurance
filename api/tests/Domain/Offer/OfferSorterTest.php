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
        $cheap = $this->offer(new PartnerId('celeris'), 47800);
        $mid = $this->offer(new PartnerId('bastion'), 48600);
        $expensive = $this->offer(new PartnerId('aurum'), 56900);

        $sorted = OfferSorter::sortByFinalPrice([$expensive, $cheap, $mid]);

        self::assertSame(
            ['celeris', 'bastion', 'aurum'],
            array_map(static fn (Offer $offer): string => $offer->partnerId->value, $sorted),
        );
    }

    #[Test]
    public function tie_breaks_by_partner_name_alphabetically(): void
    {
        $aurum = $this->offer(new PartnerId('aurum'), 47800);
        $celeris = $this->offer(new PartnerId('celeris'), 47800);

        $sorted = OfferSorter::sortByFinalPrice([$celeris, $aurum]);

        self::assertSame('aurum', $sorted[0]->partnerId->value);
        self::assertSame('celeris', $sorted[1]->partnerId->value);
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
