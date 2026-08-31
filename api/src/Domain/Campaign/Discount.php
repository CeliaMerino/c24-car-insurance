<?php

declare(strict_types=1);

namespace App\Domain\Campaign;

final readonly class Discount
{
    public function __construct(
        public int $percentage,
        public string $label,
    ) {
    }
}
