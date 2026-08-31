<?php

declare(strict_types=1);

namespace App\Domain\Quote;

final readonly class Age
{
    public function __construct(
        private int $years,
    ) {
        if ($years < 0) {
            throw new \InvalidArgumentException('Age cannot be negative.');
        }
    }

    public function years(): int
    {
        return $this->years;
    }

    public function isAtLeast(int $minimumYears): bool
    {
        return $this->years >= $minimumYears;
    }

    public static function fromDateOfBirth(DateOfBirth $dateOfBirth, int $referenceYear, int $referenceMonth, int $referenceDay): self
    {
        $years = $referenceYear - $dateOfBirth->year();

        if ($referenceMonth < $dateOfBirth->month()
            || ($referenceMonth === $dateOfBirth->month() && $referenceDay < $dateOfBirth->day())
        ) {
            --$years;
        }

        return new self($years);
    }
}
