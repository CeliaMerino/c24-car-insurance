<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Campaign;

use App\Domain\Offer\PartnerId;
use App\Infrastructure\Campaign\ConfigCampaignRepository;
use App\Tests\Support\ReferenceDates;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigCampaignRepositoryTest extends TestCase
{
    #[Test]
    public function an_active_campaign_is_returned_for_its_partner(): void
    {
        $repository = new ConfigCampaignRepository([
            'aurum' => [
                'percentage' => 15,
                'start_date' => '2026-01-01',
                'end_date' => '2026-08-31',
            ],
        ]);

        $campaign = $repository->findForPartner(new PartnerId('aurum'), ReferenceDates::frozen());

        self::assertNotNull($campaign);
        self::assertSame('aurum', $campaign->partnerId->value);
        self::assertSame(15, $campaign->percentage);
        self::assertSame('CHECK24 pays 15%', $campaign->label);
        self::assertSame(2026, $campaign->startDate->year);
        self::assertSame(1, $campaign->startDate->month);
        self::assertSame(1, $campaign->startDate->day);
        self::assertSame(2026, $campaign->endDate->year);
        self::assertSame(8, $campaign->endDate->month);
        self::assertSame(31, $campaign->endDate->day);
    }

    #[Test]
    public function an_expired_campaign_is_not_returned(): void
    {
        $repository = new ConfigCampaignRepository([
            'aurum' => [
                'percentage' => 15,
                'start_date' => '2026-01-01',
                'end_date' => '2026-08-30',
            ],
        ]);

        self::assertNull($repository->findForPartner(new PartnerId('aurum'), ReferenceDates::frozen()));
    }

    #[Test]
    public function a_partner_without_a_campaign_returns_null(): void
    {
        $repository = new ConfigCampaignRepository([
            'aurum' => [
                'percentage' => 15,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ],
        ]);

        self::assertNull($repository->findForPartner(new PartnerId('celeris'), ReferenceDates::frozen()));
    }

    #[Test]
    public function rejects_an_invalid_date(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConfigCampaignRepository([
            'aurum' => [
                'percentage' => 15,
                'start_date' => '2026-13-01',
                'end_date' => '2026-12-31',
            ],
        ]);
    }

    #[Test]
    public function rejects_a_window_that_ends_before_it_starts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ConfigCampaignRepository([
            'aurum' => [
                'percentage' => 15,
                'start_date' => '2026-12-31',
                'end_date' => '2026-01-01',
            ],
        ]);
    }
}
