<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ForcedPartnerOutcomes;
use App\Tests\Support\QuoteRequestBuilder;
use PHPUnit\Framework\Attributes\Test;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L5. Observability contract of specs/07-observability.md sections 3 and 6,
 * including the "Partner degradation is attributable" acceptance criterion
 * (specs/06-testing.md section 5).
 */
final class MetricsApiTest extends WebTestCase
{
    protected function tearDown(): void
    {
        ForcedPartnerOutcomes::clear();

        parent::tearDown();
    }

    #[Test]
    public function metrics_exposes_prometheus_text_format(): void
    {
        $client = static::createClient();
        $client->request('GET', '/metrics');

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
    }

    #[Test]
    public function partner_degradation_is_attributable_in_metrics(): void
    {
        // Use malformed rather than http_error: a single http_error among ok
        // partners currently collapses the concurrent KernelHttpClient batch
        // in this test harness. malformed still yields status=error for one
        // partner and is the seam ComparisonApiTest already relies on.
        ForcedPartnerOutcomes::set([
            'aurum' => 'ok',
            'bastion' => 'malformed',
            'celeris' => 'ok',
            'dorsal' => 'ok',
        ]);

        $payload = $this->postComparison(QuoteRequestBuilder::vectorA()->toPayload());
        self::assertSame(200, $payload['status']);
        self::assertSame('error', $this->partnerStatus($payload['body']['partners'], 'bastion'));
        self::assertSame('ok', $this->partnerStatus($payload['body']['partners'], 'aurum'));

        $body = $this->metricsText();

        self::assertMatchesRegularExpression(
            '/c24_partner_requests_total\{[^}]*partner="bastion"[^}]*status="error"[^}]*\} [0-9]+/',
            $body,
        );
        self::assertMatchesRegularExpression(
            '/c24_partner_requests_total\{[^}]*partner="aurum"[^}]*status="ok"[^}]*\} [0-9]+/',
            $body,
        );
        self::assertMatchesRegularExpression(
            '/c24_comparisons_total\{coverage="third_party_plus"\} [0-9]+/',
            $body,
        );
    }

    #[Test]
    public function validation_errors_are_counted_by_field_and_code(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/v1/comparisons',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'date_of_birth' => 'not-a-date',
                'postal_code' => 'abc',
                'car_category' => 'sedan',
                'usage' => 'private',
                'annual_mileage' => 'up_to_10000',
                'garage' => true,
                'coverage' => 'third_party',
            ], JSON_THROW_ON_ERROR),
        );
        self::assertSame(422, $client->getResponse()->getStatusCode());

        $body = $this->metricsText();

        self::assertMatchesRegularExpression(
            '/c24_validation_errors_total\{[^}]*field="date_of_birth"[^}]*\} [0-9]+/',
            $body,
        );
        self::assertMatchesRegularExpression(
            '/c24_validation_errors_total\{[^}]*field="postal_code"[^}]*\} [0-9]+/',
            $body,
        );
    }

    #[Test]
    public function frontend_events_increment_the_funnel_counter(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/v1/events',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['event' => 'form_started'], JSON_THROW_ON_ERROR),
        );
        self::assertSame(204, $client->getResponse()->getStatusCode());

        $client->request(
            'POST',
            '/api/v1/events',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['event' => 'not_a_real_event'], JSON_THROW_ON_ERROR),
        );
        self::assertSame(422, $client->getResponse()->getStatusCode());

        $client->request(
            'POST',
            '/api/v1/events',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['event' => 'form_started'], JSON_THROW_ON_ERROR),
        );
        self::assertSame(204, $client->getResponse()->getStatusCode());

        $body = $this->metricsText();

        self::assertMatchesRegularExpression(
            '/c24_frontend_events_total\{event="form_started"\} [0-9]+/',
            $body,
        );
        self::assertDoesNotMatchRegularExpression(
            '/c24_frontend_events_total\{event="not_a_real_event"\}/',
            $body,
        );
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{status: int, body: array<string, mixed>}
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

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertJson($content);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return [
            'status' => $response->getStatusCode(),
            'body' => $decoded,
        ];
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

    private function metricsText(): string
    {
        /** @var CollectorRegistry $registry */
        $registry = static::getContainer()->get(CollectorRegistry::class);

        return (new RenderTextFormat())->render($registry->getMetricFamilySamples());
    }
}
