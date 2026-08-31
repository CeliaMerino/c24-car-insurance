<?php

declare(strict_types=1);

namespace App\Tests\Domain\Quote;

use App\Tests\Support\QuoteRequestBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QuoteRequestTest extends TestCase
{
    #[Test]
    public function vector_a_builds_valid_quote_request(): void
    {
        $request = QuoteRequestBuilder::vectorA()->build();

        self::assertSame('1990-05-14', $request->dateOfBirth->toIsoDate());
        self::assertSame('28013', $request->postalCode->value());
    }

    #[Test]
    public function vector_b_builds_valid_quote_request(): void
    {
        $request = QuoteRequestBuilder::vectorB()->build();

        self::assertSame('1981-03-02', $request->dateOfBirth->toIsoDate());
        self::assertSame('15001', $request->postalCode->value());
    }
}
