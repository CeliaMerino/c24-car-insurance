<?php

declare(strict_types=1);

namespace App\Domain\Quote;

use App\Domain\Validation\ValidationError;
use App\Domain\Validation\ValidationException;

final readonly class DateOfBirth
{
    private function __construct(
        private int $year,
        private int $month,
        private int $day,
    ) {
    }

    public function year(): int
    {
        return $this->year;
    }

    public function month(): int
    {
        return $this->month;
    }

    public function day(): int
    {
        return $this->day;
    }

    public function toIsoDate(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    public static function fromParts(int $year, int $month, int $day): self
    {
        return new self($year, $month, $day);
    }

    /**
     * @throws ValidationException
     */
    public static function fromString(string $value, int $referenceYear, int $referenceMonth, int $referenceDay): self
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new ValidationException([
                new ValidationError('date_of_birth', 'format', 'Enter a valid date in YYYY-MM-DD format.'),
            ]);
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        if (!checkdate($month, $day, $year)) {
            throw new ValidationException([
                new ValidationError('date_of_birth', 'format', 'Enter a valid date in YYYY-MM-DD format.'),
            ]);
        }

        if ($year > $referenceYear
            || ($year === $referenceYear && $month > $referenceMonth)
            || ($year === $referenceYear && $month === $referenceMonth && $day > $referenceDay)
        ) {
            throw new ValidationException([
                new ValidationError('date_of_birth', 'future_date', 'Date of birth cannot be in the future.'),
            ]);
        }

        $age = Age::fromDateOfBirth(new self($year, $month, $day), $referenceYear, $referenceMonth, $referenceDay);

        if (!$age->isAtLeast(18)) {
            throw new ValidationException([
                new ValidationError('date_of_birth', 'min_age', 'You must be at least 18 years old.'),
            ]);
        }

        return new self($year, $month, $day);
    }
}
