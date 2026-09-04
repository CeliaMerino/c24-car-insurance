<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Domain\Quote\CoverageLevel;
use App\Tests\Support\ForcedPartnerOutcomes;
use App\Tests\Support\QuoteRequestBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L5. The comparison contract of specs/05-api-contract.md section 2, using the
 * real simulator and PARTNER_{ID}_FORCE rather than the seed
 * (specs/06-testing.md sections 2, 3 and 5).
 */
final class ComparisonApiTest extends WebTestCase
{
    protected function tearDown(): void
    {
        ForcedPartnerOutcomes::clear();

        parent::tearDown();
    }

    #[Test]
    public function a_valid_submission_returns_offers_from_all_healthy_partners(): void
    {
        ForcedPartnerOutcomes::all('ok');

        $payload = $this->postComparison(QuoteRequestBuilder::vectorA()->toPayload());

        self::assertSame(200, $payload['status']);
        self::assertStringContainsString('no-store', (string) $payload['cache_control']);
        self::assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $payload['body']['comparison_id']);
        self::assertSame('third_party_plus', $payload['body']['coverage']);
        self::assertSame('EUR', $payload['body']['currency']);
        self::assertIsInt($payload['body']['duration_ms']);
        self::assertGreaterThanOrEqual(0, $payload['body']['duration_ms']);

        self::assertSame(
            [
                ['partner' => 'celeris', 'cents' => 47_800, 'name' => 'Celeris Seguros'],
                ['partner' => 'bastion', 'cents' => 48_600, 'name' => 'Bastion Insurance'],
                ['partner' => 'aurum', 'cents' => 56_900, 'name' => 'Aurum Direct'],
                ['partner' => 'dorsal', 'cents' => 61_300, 'name' => 'Dorsal Mutual'],
            ],
            array_map(
                static fn (array $offer): array => [
                    'partner' => $offer['partner'],
                    'cents' => $offer['final_annual_premium_cents'],
                    'name' => $offer['partner_display_name'],
                ],
                $payload['body']['offers'],
            ),
        );

        foreach ($payload['body']['offers'] as $offer) {
            self::assertSame($offer['base_annual_premium_cents'], $offer['final_annual_premium_cents']);
            self::assertNull($offer['campaign']);
        }

        self::assertSame(
            ['aurum', 'bastion', 'celeris', 'dorsal'],
            array_column($payload['body']['partners'], 'partner'),
        );
        foreach ($payload['body']['partners'] as $partner) {
            self::assertSame('ok', $partner['status']);
            self::assertIsInt($partner['duration_ms']);
        }
    }

    #[Test]
    public function coverage_level_changes_the_prices_quoted(): void
    {
        ForcedPartnerOutcomes::all('ok');

        $thirdParty = QuoteRequestBuilder::vectorA()
            ->withCoverage(CoverageLevel::ThirdParty)
            ->toPayload();
        $comprehensive = QuoteRequestBuilder::vectorA()
            ->withCoverage(CoverageLevel::Comprehensive)
            ->toPayload();

        $client = static::createClient();
        $low = $this->postComparison($thirdParty, $client);
        $high = $this->postComparison($comprehensive, $client);

        self::assertSame(200, $low['status']);
        self::assertSame(200, $high['status']);
        self::assertSame('third_party', $low['body']['coverage']);
        self::assertSame('comprehensive', $high['body']['coverage']);

        $lowByPartner = $this->offersByPartner($low['body']['offers']);
        $highByPartner = $this->offersByPartner($high['body']['offers']);

        ksort($lowByPartner);
        ksort($highByPartner);
        self::assertSame(array_keys($lowByPartner), array_keys($highByPartner));
        foreach ($lowByPartner as $partner => $cents) {
            self::assertGreaterThan($cents, $highByPartner[$partner], $partner);
        }
    }

    #[Test]
    public function a_slow_partner_does_not_delay_the_comparison(): void
    {
        ForcedPartnerOutcomes::set([
            'aurum' => 'ok',
            'bastion' => 'timeout',
            'celeris' => 'ok',
            'dorsal' => 'ok',
        ]);

        $payload = $this->postComparison(QuoteRequestBuilder::vectorA()->toPayload());

        self::assertSame(200, $payload['status']);
        self::assertCount(3, $payload['body']['offers']);
        self::assertSame(['celeris', 'aurum', 'dorsal'], $this->offerPartners($payload['body']['offers']));
        self::assertSame('timeout', $this->partnerStatus($payload['body']['partners'], 'bastion'));
        self::assertSame(['aurum', 'bastion', 'celeris', 'dorsal'], array_column($payload['body']['partners'], 'partner'));
        self::assertLessThan(1000, $payload['body']['duration_ms']);
    }

    #[Test]
    public function a_partner_returning_a_malformed_payload_is_excluded(): void
    {
        ForcedPartnerOutcomes::set([
            'aurum' => 'ok',
            'bastion' => 'ok',
            'celeris' => 'ok',
            'dorsal' => 'malformed',
        ]);

        $payload = $this->postComparison(QuoteRequestBuilder::vectorA()->toPayload());

        self::assertSame(200, $payload['status']);
        self::assertSame(['celeris', 'bastion', 'aurum'], $this->offerPartners($payload['body']['offers']));
        self::assertSame('error', $this->partnerStatus($payload['body']['partners'], 'dorsal'));
        self::assertCount(4, $payload['body']['partners']);
    }

    #[Test]
    public function no_partner_responding_is_a_successful_empty_comparison(): void
    {
        ForcedPartnerOutcomes::set([
            'aurum' => 'http_error',
            'bastion' => 'http_error',
            'celeris' => 'http_error',
            'dorsal' => 'malformed',
        ]);

        $payload = $this->postComparison(QuoteRequestBuilder::vectorA()->toPayload());

        self::assertSame(200, $payload['status']);
        self::assertStringContainsString('no-store', (string) $payload['cache_control']);
        self::assertSame([], $payload['body']['offers']);
        self::assertSame(
            ['aurum', 'bastion', 'celeris', 'dorsal'],
            array_column($payload['body']['partners'], 'partner'),
        );
        self::assertSame(
            ['error', 'error', 'error', 'error'],
            array_column($payload['body']['partners'], 'status'),
        );
        self::assertSame('third_party_plus', $payload['body']['coverage']);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{status: int, cache_control: ?string, body: array<string, mixed>}
     */
    private function postComparison(array $body, ?KernelBrowser $client = null): array
    {
        $client ??= static::createClient();
        $client->request(
            'POST',
            '/api/v1/comparisons',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );

        return $this->decode($client);
    }

    /**
     * @return array{status: int, cache_control: ?string, body: array<string, mixed>}
     */
    private function decode(KernelBrowser $client): array
    {
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertJson($content);

        /** @var array<string, mixed> $body */
        $body = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return [
            'status' => $response->getStatusCode(),
            'cache_control' => $response->headers->get('Cache-Control'),
            'body' => $body,
        ];
    }

    /**
     * @param list<array<string, mixed>> $offers
     *
     * @return list<string>
     */
    private function offerPartners(array $offers): array
    {
        return array_map(static fn (array $offer): string => (string) $offer['partner'], $offers);
    }

    /**
     * @param list<array<string, mixed>> $offers
     *
     * @return array<string, int>
     */
    private function offersByPartner(array $offers): array
    {
        $byPartner = [];
        foreach ($offers as $offer) {
            $byPartner[(string) $offer['partner']] = (int) $offer['final_annual_premium_cents'];
        }

        return $byPartner;
    }

    /**
     * @param list<array<string, mixed>> $partners
     */
    private function partnerStatus(array $partners, string $id): string
    {
        foreach ($partners as $partner) {
            if ($id === $partner['partner']) {
                return (string) $partner['status'];
            }
        }

        self::fail('Partner '.$id.' was missing from the partners array.');
    }
}
