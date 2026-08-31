<?php

declare(strict_types=1);

namespace App\Domain\Quote;

use App\Domain\Validation\ValidationError;
use App\Domain\Validation\ValidationException;

final readonly class PostalCode
{
    private const int MIN = 1000;
    private const int MAX = 52999;

    private function __construct(
        private string $value,
    ) {
    }

    public function value(): string
    {
        return $this->value;
    }

    public function regionPrefix(): string
    {
        return substr($this->value, 0, 2);
    }

    /**
     * @throws ValidationException
     */
    public static function fromString(string $value): self
    {
        if (!preg_match('/^\d{5}$/', $value)) {
            throw new ValidationException([
                new ValidationError('postal_code', 'format', 'Enter a five-digit postal code.'),
            ]);
        }

        $numeric = (int) $value;

        if ($numeric < self::MIN || $numeric > self::MAX) {
            throw new ValidationException([
                new ValidationError('postal_code', 'format', 'Enter a five-digit postal code.'),
            ]);
        }

        return new self($value);
    }
}
