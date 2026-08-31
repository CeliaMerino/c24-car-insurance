<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Comparison\PartnerOutcome;
use App\Domain\Quote\QuoteRequest;

interface PartnerGateway
{
    /**
     * @param list<RegisteredPartner> $partners
     *
     * @return list<PartnerOutcome>
     */
    public function fetchQuotes(QuoteRequest $request, array $partners, int $deadlineMs): array;
}
