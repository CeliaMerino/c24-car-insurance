<?php

declare(strict_types=1);

namespace App\Domain\Campaign;

use App\Domain\Offer\PartnerId;
use App\Domain\Shared\ReferenceDate;
use InvalidArgumentException;

final readonly class Campaign
{
    public function __construct(
        public PartnerId $partnerId,
        public int $percentage,
        public string $label,
        public ReferenceDate $startDate,
        public ReferenceDate $endDate,
    ) {
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException('Campaign percentage must be between 0 and 100.');
        }

        if (self::compareDate($startDate, $endDate) > 0) {
            throw new InvalidArgumentException('Campaign end date must be on or after the start date.');
        }
    }

    public function isActiveAt(ReferenceDate $on): bool
    {
        return self::compareDate($on, $this->startDate) >= 0
            && self::compareDate($on, $this->endDate) <= 0;
    }

    private static function compareDate(ReferenceDate $a, ReferenceDate $b): int
    {
        return [$a->year, $a->month, $a->day] <=> [$b->year, $b->month, $b->day];
    }
}
