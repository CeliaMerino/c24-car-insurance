<?php

declare(strict_types=1);

namespace App\Application\Port;

interface PartnerRegistry
{
    /**
     * @return list<RegisteredPartner>
     */
    public function enabledPartners(): array;
}
