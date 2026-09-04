<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\PartnerRegistry;
use App\Application\Port\RegisteredPartner;

final class InMemoryPartnerRegistry implements PartnerRegistry
{
    /**
     * @param list<RegisteredPartner> $partners
     */
    public function __construct(
        private array $partners,
    ) {
    }

    public function enabledPartners(): array
    {
        return $this->partners;
    }
}
