<?php

declare(strict_types=1);

namespace App\Domain\Offer;

use InvalidArgumentException;

final readonly class PartnerId
{
    public function __construct(
        public string $value,
    ) {
        if ('' === $this->value) {
            throw new InvalidArgumentException('Partner id cannot be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
