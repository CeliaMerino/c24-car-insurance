<?php

declare(strict_types=1);

namespace App\Tests\Domain\Quote;

use App\Domain\Quote\PostalCode;
use App\Domain\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PostalCodeTest extends TestCase
{
    #[Test]
    public function accepts_valid_spanish_postal_code(): void
    {
        $postalCode = PostalCode::fromString('28013');

        self::assertSame('28013', $postalCode->value());
        self::assertSame('28', $postalCode->regionPrefix());
    }

    #[Test]
    public function accepts_lower_boundary(): void
    {
        $postalCode = PostalCode::fromString('01000');

        self::assertSame('01000', $postalCode->value());
    }

    #[Test]
    public function accepts_upper_boundary(): void
    {
        $postalCode = PostalCode::fromString('52999');

        self::assertSame('52999', $postalCode->value());
    }

    #[Test]
    public function rejects_code_below_range(): void
    {
        $this->expectValidationError('00999', 'format');
    }

    #[Test]
    public function rejects_code_above_range(): void
    {
        $this->expectValidationError('53000', 'format');
    }

    #[Test]
    public function rejects_non_five_digit_code(): void
    {
        $this->expectValidationError('2801', 'format');
    }

    private function expectValidationError(string $value, string $code): void
    {
        try {
            PostalCode::fromString($value);
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('postal_code', $exception->errors[0]->field);
            self::assertSame($code, $exception->errors[0]->code);
            self::assertSame('Enter a five-digit postal code.', $exception->errors[0]->message);
        }
    }
}
