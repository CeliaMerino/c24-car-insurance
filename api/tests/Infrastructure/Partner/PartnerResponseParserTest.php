<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Partner;

use App\Domain\Offer\PartnerId;
use App\Domain\Quote\CoverageLevel;
use App\Infrastructure\Partner\InvalidPartnerResponse;
use App\Infrastructure\Partner\PartnerResponseParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * L3. HTTP response parsing and schema validation
 * (specs/06-testing.md section 3). Failure bodies are the exact shapes of
 * specs/04-providers.md section 4.1.
 */
final class PartnerResponseParserTest extends TestCase
{
    private PartnerResponseParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PartnerResponseParser();
    }

    #[Test]
    public function valid_success_body_becomes_an_offer(): void
    {
        $offer = $this->parser->parse(
            new PartnerId('aurum'),
            CoverageLevel::ThirdPartyPlus,
            200,
            '{"partner":"aurum","coverage":"third_party_plus","annual_premium_cents":56900,"currency":"EUR"}',
        );

        self::assertSame('aurum', $offer->partnerId->value);
        self::assertSame(CoverageLevel::ThirdPartyPlus, $offer->coverage);
        self::assertSame(56900, $offer->basePrice->cents());
        self::assertSame(56900, $offer->finalPrice->cents());
        self::assertNull($offer->discount);
    }

    #[Test]
    public function extra_fields_on_a_valid_body_are_ignored(): void
    {
        $offer = $this->parser->parse(
            new PartnerId('aurum'),
            CoverageLevel::ThirdPartyPlus,
            200,
            '{"partner":"aurum","coverage":"third_party_plus","annual_premium_cents":56900,"currency":"EUR","retry_after":30}',
        );

        self::assertSame(56900, $offer->basePrice->cents());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function invalidBodies(): iterable
    {
        yield 'HTTP 500' => [500, '{"error":"service_unavailable","retry_after":30}'];
        yield 'HTTP 503' => [503, '{"error":"service_unavailable","retry_after":30}'];
        yield 'malformed 200' => [200, '{"partner":"dorsal","premium":"unavailable"}'];
        yield 'non-JSON HTML' => [200, '<html><body><h1>502 Bad Gateway</h1></body></html>'];
        yield 'missing premium' => [200, '{"partner":"aurum","coverage":"third_party_plus","currency":"EUR"}'];
        yield 'premium as string' => [200, '{"partner":"aurum","coverage":"third_party_plus","annual_premium_cents":"56900","currency":"EUR"}'];
        yield 'negative premium' => [200, '{"partner":"aurum","coverage":"third_party_plus","annual_premium_cents":-1,"currency":"EUR"}'];
        yield 'wrong currency' => [200, '{"partner":"aurum","coverage":"third_party_plus","annual_premium_cents":56900,"currency":"USD"}'];
        yield 'partner mismatch' => [200, '{"partner":"bastion","coverage":"third_party_plus","annual_premium_cents":56900,"currency":"EUR"}'];
        yield 'coverage mismatch' => [200, '{"partner":"aurum","coverage":"comprehensive","annual_premium_cents":56900,"currency":"EUR"}'];
        yield 'JSON array' => [200, '[]'];
        yield 'JSON string' => [200, '"ok"'];
    }

    #[Test]
    #[DataProvider('invalidBodies')]
    public function invalid_response_is_rejected(int $statusCode, string $body): void
    {
        $this->expectException(InvalidPartnerResponse::class);

        $this->parser->parse(
            new PartnerId('aurum'),
            CoverageLevel::ThirdPartyPlus,
            $statusCode,
            $body,
        );
    }

    #[Test]
    public function retry_after_on_an_error_body_is_ignored(): void
    {
        $this->expectException(InvalidPartnerResponse::class);

        $this->parser->parse(
            new PartnerId('dorsal'),
            CoverageLevel::ThirdPartyPlus,
            503,
            '{"error":"service_unavailable","retry_after":30}',
        );
    }
}
