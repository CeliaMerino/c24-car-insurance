<?php

declare(strict_types=1);

namespace App\Tests\Domain\Offer;

use App\Domain\Campaign\Campaign;
use App\Domain\Offer\Money;
use App\Domain\Offer\Offer;
use App\Domain\Offer\PartnerId;
use App\Domain\Quote\CoverageLevel;
use App\Domain\Shared\ReferenceDate;
use App\Tests\Support\ReferenceDates;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OfferCampaignTest extends TestCase
{
    #[Test]
    public function applies_active_campaign_discount_to_matching_partner(): void
    {
        $offer = Offer::fromPartnerQuote(
            new PartnerId('aurum'),
            CoverageLevel::ThirdPartyPlus,
            new Money(56900),
        );

        $campaign = new Campaign(
            new PartnerId('aurum'),
            15,
            'CHECK24 pays 15%',
            new ReferenceDate(2026, 1, 1),
            new ReferenceDate(2026, 12, 31),
        );

        $discounted = $offer->withCampaign($campaign, ReferenceDates::frozen());

        self::assertSame(56900, $discounted->basePrice->cents());
        self::assertSame(48365, $discounted->finalPrice->cents());
        self::assertNotNull($discounted->discount);
        self::assertSame(15, $discounted->discount->percentage);
        self::assertSame('CHECK24 pays 15%', $discounted->discount->label);
    }

    #[Test]
    public function does_not_apply_expired_campaign(): void
    {
        $offer = Offer::fromPartnerQuote(
            new PartnerId('aurum'),
            CoverageLevel::ThirdPartyPlus,
            new Money(56900),
        );

        $campaign = new Campaign(
            new PartnerId('aurum'),
            15,
            'CHECK24 pays 15%',
            new ReferenceDate(2026, 1, 1),
            new ReferenceDate(2026, 8, 30),
        );

        $unchanged = $offer->withCampaign($campaign, ReferenceDates::frozen());

        self::assertSame(56900, $unchanged->finalPrice->cents());
        self::assertNull($unchanged->discount);
    }

    #[Test]
    public function does_not_apply_campaign_to_different_partner(): void
    {
        $offer = Offer::fromPartnerQuote(
            new PartnerId('celeris'),
            CoverageLevel::ThirdPartyPlus,
            new Money(47800),
        );

        $campaign = new Campaign(
            new PartnerId('aurum'),
            15,
            'CHECK24 pays 15%',
            new ReferenceDate(2026, 1, 1),
            new ReferenceDate(2026, 12, 31),
        );

        $unchanged = $offer->withCampaign($campaign, ReferenceDates::frozen());

        self::assertSame(47800, $unchanged->finalPrice->cents());
        self::assertNull($unchanged->discount);
    }
}
