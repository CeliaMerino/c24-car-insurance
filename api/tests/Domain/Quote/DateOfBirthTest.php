<?php

declare(strict_types=1);

namespace App\Tests\Domain\Quote;

use App\Domain\Quote\DateOfBirth;
use App\Domain\Validation\ValidationException;
use App\Tests\Support\ReferenceDates;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DateOfBirthTest extends TestCase
{
    #[Test]
    public function rejects_customer_under_eighteen(): void
    {
        $reference = ReferenceDates::frozen();

        try {
            DateOfBirth::fromString('2008-09-01', $reference->year, $reference->month, $reference->day);
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertCount(1, $exception->errors);
            self::assertSame('date_of_birth', $exception->errors[0]->field);
            self::assertSame('min_age', $exception->errors[0]->code);
            self::assertSame('You must be at least 18 years old.', $exception->errors[0]->message);
        }
    }

    #[Test]
    public function accepts_customer_exactly_eighteen(): void
    {
        $reference = ReferenceDates::frozen();

        $dateOfBirth = DateOfBirth::fromString('2008-08-31', $reference->year, $reference->month, $reference->day);

        self::assertSame('2008-08-31', $dateOfBirth->toIsoDate());
    }

    #[Test]
    public function rejects_future_date_of_birth(): void
    {
        $reference = ReferenceDates::frozen();

        try {
            DateOfBirth::fromString('2026-09-01', $reference->year, $reference->month, $reference->day);
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertCount(1, $exception->errors);
            self::assertSame('date_of_birth', $exception->errors[0]->field);
            self::assertSame('future_date', $exception->errors[0]->code);
        }
    }

    #[Test]
    public function rejects_invalid_format(): void
    {
        $reference = ReferenceDates::frozen();

        try {
            DateOfBirth::fromString('14-05-1990', $reference->year, $reference->month, $reference->day);
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('format', $exception->errors[0]->code);
        }
    }
}
