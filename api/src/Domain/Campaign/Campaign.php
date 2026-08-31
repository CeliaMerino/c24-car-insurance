<?php

declare(strict_types=1);

namespace App\Domain\Campaign;

use App\Domain\Offer\PartnerId;
use App\Domain\Shared\ReferenceDate;

final readonly class Campaign
{
    public function __construct(
        public PartnerId $partnerId,
        public int $percentage,
        public string $label,
        public ReferenceDate $startDate,
        public ReferenceDate $endDate,
    ) {
    }

    public function isActiveAt(ReferenceDate $on): bool
    {
        if ($on->year < $this->startDate->year || $on->year > $this->endDate->year) {
            return false;
        }

        if ($on->year === $this->startDate->year
            && ($on->month < $this->startDate->month
                || ($on->month === $this->startDate->month && $on->day < $this->startDate->day))
        ) {
            return false;
        }

        if ($on->year === $this->endDate->year
            && ($on->month > $this->endDate->month
                || ($on->month === $this->endDate->month && $on->day > $this->endDate->day))
        ) {
            return false;
        }

        return true;
    }
}
