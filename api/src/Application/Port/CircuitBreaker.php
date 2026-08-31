<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\PartnerId;

interface CircuitBreaker
{
    /**
     * @param list<PartnerId> $partnerIds
     *
     * @return list<PartnerId>
     */
    public function callablePartners(array $partnerIds): array;

    public function recordOutcome(PartnerId $partnerId, PartnerStatus $status): void;
}
