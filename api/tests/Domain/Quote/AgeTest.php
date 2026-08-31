<?php

declare(strict_types=1);

namespace App\Tests\Domain\Quote;

use App\Domain\Quote\Age;
use App\Domain\Quote\DateOfBirth;
use App\Tests\Support\ReferenceDates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AgeTest extends TestCase
{
    #[Test]
    #[DataProvider('ageBoundaryProvider')]
    public function calculates_age_on_reference_date(string $dateOfBirth, int $expectedAge): void
    {
        $reference = ReferenceDates::frozen();
        $birth = $this->birthFromIso($dateOfBirth);
        $age = Age::fromDateOfBirth($birth, $reference->year, $reference->month, $reference->day);

        self::assertSame($expectedAge, $age->years());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function ageBoundaryProvider(): iterable
    {
        yield '17 years old day before 18th birthday' => ['2008-09-01', 17];
        yield '18 years old on birthday' => ['2008-08-31', 18];

        yield '24 years old day before 25th birthday' => ['2001-09-01', 24];
        yield '25 years old on birthday' => ['2001-08-31', 25];

        yield '34 years old day before 35th birthday' => ['1991-09-01', 34];
        yield '35 years old on birthday' => ['1991-08-31', 35];

        yield '54 years old day before 55th birthday' => ['1971-09-01', 54];
        yield '55 years old on birthday' => ['1971-08-31', 55];

        yield '69 years old day before 70th birthday' => ['1956-09-01', 69];
        yield '70 years old on birthday' => ['1956-08-31', 70];
    }

    private function birthFromIso(string $isoDate): DateOfBirth
    {
        [$year, $month, $day] = array_map('intval', explode('-', $isoDate));

        return DateOfBirth::fromParts($year, $month, $day);
    }
}
