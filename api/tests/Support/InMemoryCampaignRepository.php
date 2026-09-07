<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\CampaignRepository;
use App\Domain\Campaign\Campaign;
use App\Domain\Offer\PartnerId;
use App\Domain\Shared\ReferenceDate;

final class InMemoryCampaignRepository implements CampaignRepository
{
    /**
     * @param array<string, Campaign> $campaigns keyed by partner id
     */
    public function __construct(
        private array $campaigns = [],
    ) {
    }

    public function findForPartner(PartnerId $partnerId, ReferenceDate $on): ?Campaign
    {
        $campaign = $this->campaigns[$partnerId->value] ?? null;

        if (null === $campaign || !$campaign->isActiveAt($on)) {
            return null;
        }

        return $campaign;
    }
}
