<?php

declare(strict_types=1);

namespace App\Infrastructure\Partner;

use App\Domain\Offer\Money;
use App\Domain\Offer\Offer;
use App\Domain\Offer\PartnerId;
use App\Domain\Quote\CoverageLevel;
use JsonException;

/**
 * Turns a partner HTTP body into an Offer, or rejects it.
 *
 * Pricing is not here. The adapter's only job is the schema of
 * specs/04-providers.md section 2.2 against the failure shapes of section 4.1.
 */
final class PartnerResponseParser
{
    public function parse(
        PartnerId $expectedPartner,
        CoverageLevel $requestedCoverage,
        int $statusCode,
        string $body,
    ): Offer {
        if (200 !== $statusCode) {
            throw new InvalidPartnerResponse(sprintf('HTTP %d is not a successful partner response.', $statusCode));
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidPartnerResponse('Partner response is not JSON.', previous: $e);
        }

        if (!is_array($decoded)) {
            throw new InvalidPartnerResponse('Partner response is not a JSON object.');
        }

        $partner = $this->string($decoded, 'partner');
        if ($partner !== $expectedPartner->value) {
            throw new InvalidPartnerResponse(sprintf(
                'Partner response named "%s" but the call was for "%s".',
                $partner,
                $expectedPartner->value,
            ));
        }

        $coverageValue = $this->string($decoded, 'coverage');
        $coverage = CoverageLevel::tryFrom($coverageValue);
        if ($coverage !== $requestedCoverage) {
            throw new InvalidPartnerResponse(sprintf(
                'Partner response coverage "%s" does not match the requested "%s".',
                $coverageValue,
                $requestedCoverage->value,
            ));
        }

        $cents = $decoded['annual_premium_cents'] ?? null;
        if (!is_int($cents) || $cents < 0) {
            throw new InvalidPartnerResponse('Field "annual_premium_cents" must be a non-negative integer.');
        }

        $currency = $this->string($decoded, 'currency');
        if (Money::CURRENCY !== $currency) {
            throw new InvalidPartnerResponse(sprintf('Partner response currency "%s" is not %s.', $currency, Money::CURRENCY));
        }

        return Offer::fromPartnerQuote($expectedPartner, $coverage, new Money($cents));
    }

    /**
     * @param array<mixed> $payload
     */
    private function string(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;

        if (!is_string($value) || '' === $value) {
            throw new InvalidPartnerResponse(sprintf('Field "%s" is missing or not a string.', $field));
        }

        return $value;
    }
}
