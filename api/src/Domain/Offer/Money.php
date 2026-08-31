<?php

declare(strict_types=1);

namespace App\Domain\Offer;

use InvalidArgumentException;

final readonly class Money
{
    public const string CURRENCY = 'EUR';

    public function __construct(
        private int $cents,
    ) {
        if ($cents < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }
    }

    public function cents(): int
    {
        return $this->cents;
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        if ($other->cents > $this->cents) {
            throw new InvalidArgumentException('Subtraction would result in a negative amount.');
        }

        return new self($this->cents - $other->cents);
    }

    public function applyDiscountPercentage(int $percentage): self
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException('Discount percentage must be between 0 and 100.');
        }

        $discountedCents = (int) round($this->cents * (100 - $percentage) / 100, 0, PHP_ROUND_HALF_UP);

        return new self($discountedCents);
    }

    public function compareTo(self $other): int
    {
        return $this->cents <=> $other->cents;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents;
    }
}
