<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Campaign\Campaign;
use App\Domain\Offer\PartnerId;
use App\Domain\Shared\ReferenceDate;

interface CampaignRepository
{
    public function findForPartner(PartnerId $partnerId, ReferenceDate $on): ?Campaign;
}
