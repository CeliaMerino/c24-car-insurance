<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final readonly class ReferenceDate
{
    public function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
    }
}
