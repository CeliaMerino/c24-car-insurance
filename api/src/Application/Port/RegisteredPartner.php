<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Offer\PartnerId;

final readonly class RegisteredPartner
{
    public function __construct(
        public PartnerId $id,
        public string $displayName,
        public string $quoteUrl,
        public int $timeoutMs,
    ) {
    }
}
