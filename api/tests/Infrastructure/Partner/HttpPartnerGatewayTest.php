<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Partner;

use App\Domain\Comparison\PartnerStatus;
use App\Infrastructure\Partner\HttpPartnerGateway;
use App\Infrastructure\Partner\PartnerResponseParser;
use App\Tests\Support\QuoteRequestBuilder;
use App\Tests\Support\RecordingHttpClient;
use App\Tests\Support\RegisteredPartners;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * L3. Outcome mapping to ok / timeout / error, dispatch-before-read, and
 * timeout / max_duration / stream deadline options
 * (specs/06-testing.md section 3, specs/03-architecture.md sections 3.2–3.4).
 *
 * No real network call.
 */
final class HttpPartnerGatewayTest extends TestCase
{
    #[Test]
    public function a_schema_valid_200_is_ok_with_an_offer(): void
    {
        $gateway = $this->gateway(self::success('aurum', 56900));

        $outcomes = $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            [RegisteredPartners::aurum()],
            3000,
        );

        self::assertCount(1, $outcomes);
        self::assertSame(PartnerStatus::Ok, $outcomes[0]->status);
        self::assertNotNull($outcomes[0]->offer);
        self::assertSame(56900, $outcomes[0]->offer->basePrice->cents());
        self::assertGreaterThanOrEqual(0, $outcomes[0]->durationMs);
    }

    #[Test]
    public function http_500_is_error_with_no_offer(): void
    {
        $gateway = $this->gateway(new MockResponse(
            '{"error":"service_unavailable","retry_after":30}',
            ['http_code' => 500],
        ));

        $outcomes = $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            [RegisteredPartners::aurum()],
            3000,
        );

        self::assertSame(PartnerStatus::Error, $outcomes[0]->status);
        self::assertNull($outcomes[0]->offer);
    }

    #[Test]
    public function a_malformed_payload_is_error(): void
    {
        $gateway = $this->gateway(new MockResponse('{"partner":"dorsal","premium":"unavailable"}'));

        $outcomes = $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            [RegisteredPartners::dorsal()],
            3000,
        );

        self::assertSame(PartnerStatus::Error, $outcomes[0]->status);
        self::assertNull($outcomes[0]->offer);
    }

    #[Test]
    public function a_non_json_body_is_error(): void
    {
        $gateway = $this->gateway(new MockResponse(
            '<html><body><h1>502 Bad Gateway</h1></body></html>',
            ['response_headers' => ['Content-Type: text/html']],
        ));

        $outcomes = $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            [RegisteredPartners::dorsal()],
            3000,
        );

        self::assertSame(PartnerStatus::Error, $outcomes[0]->status);
        self::assertNull($outcomes[0]->offer);
    }

    #[Test]
    public function an_idle_timeout_is_timeout_and_the_body_is_not_used(): void
    {
        $gateway = $this->gateway(new MockResponse(
            (static function (): \Generator {
                yield '';
                yield '{"partner":"celeris","coverage":"third_party_plus","annual_premium_cents":47800,"currency":"EUR"}';
            })(),
        ));

        $outcomes = $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            [RegisteredPartners::celeris()],
            3000,
        );

        self::assertSame(PartnerStatus::Timeout, $outcomes[0]->status);
        self::assertNull($outcomes[0]->offer);
    }

    #[Test]
    public function a_connection_failure_is_error(): void
    {
        $gateway = $this->gateway(new MockResponse('', ['error' => 'Failed to connect: Connection refused']));

        $outcomes = $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            [RegisteredPartners::bastion()],
            3000,
        );

        self::assertSame(PartnerStatus::Error, $outcomes[0]->status);
        self::assertNull($outcomes[0]->offer);
    }

    #[Test]
    public function a_client_exception_does_not_leave_the_gateway(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            throw new \RuntimeException('boom');
        });
        $gateway = new HttpPartnerGateway($client, new PartnerResponseParser());

        $outcomes = $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            [RegisteredPartners::aurum(), RegisteredPartners::bastion()],
            3000,
        );

        self::assertCount(2, $outcomes);
        foreach ($outcomes as $outcome) {
            self::assertSame(PartnerStatus::Error, $outcome->status);
            self::assertNull($outcome->offer);
        }
    }

    #[Test]
    public function every_partner_is_dispatched_before_any_response_is_streamed(): void
    {
        $inner = new MockHttpClient([
            self::success('aurum', 56900),
            self::success('bastion', 48600),
            self::success('celeris', 47800),
            self::success('dorsal', 61300),
        ]);
        $client = new RecordingHttpClient($inner);
        $gateway = new HttpPartnerGateway($client, new PartnerResponseParser());

        $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            RegisteredPartners::four(),
            3000,
        );

        self::assertSame(['request', 'request', 'request', 'request', 'stream'], $client->events);
    }

    #[Test]
    public function every_request_sets_timeout_and_max_duration(): void
    {
        $inner = new MockHttpClient([self::success('aurum', 56900)]);
        $client = new RecordingHttpClient($inner);
        $gateway = new HttpPartnerGateway($client, new PartnerResponseParser());

        $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            [RegisteredPartners::aurum(timeoutMs: 2000)],
            3000,
        );

        self::assertCount(1, $client->requestOptions);
        self::assertSame(2.0, $client->requestOptions[0]['timeout']);
        self::assertSame(2.0, $client->requestOptions[0]['max_duration']);
    }

    #[Test]
    public function the_global_deadline_is_the_timeout_argument_to_stream(): void
    {
        $inner = new MockHttpClient([self::success('aurum', 56900)]);
        $client = new RecordingHttpClient($inner);
        $gateway = new HttpPartnerGateway($client, new PartnerResponseParser());

        $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            [RegisteredPartners::aurum()],
            3000,
        );

        self::assertNotNull($client->streamTimeout);
        self::assertGreaterThan(0.0, $client->streamTimeout);
        self::assertLessThanOrEqual(3.0, $client->streamTimeout);
    }

    #[Test]
    public function native_http_client_is_rejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('CurlHttpClient');

        new HttpPartnerGateway(new NativeHttpClient(), new PartnerResponseParser());
    }

    #[Test]
    public function mixed_outcomes_are_contained_per_partner(): void
    {
        $client = new MockHttpClient([
            self::success('aurum', 56900),
            new MockResponse('', ['error' => 'Connection refused']),
            self::success('celeris', 47800),
            new MockResponse('{"partner":"dorsal","premium":"unavailable"}'),
        ]);
        $gateway = new HttpPartnerGateway($client, new PartnerResponseParser());

        $outcomes = $gateway->fetchQuotes(
            QuoteRequestBuilder::vectorA()->build(),
            RegisteredPartners::four(),
            3000,
        );

        $byId = [];
        foreach ($outcomes as $outcome) {
            $byId[$outcome->partnerId->value] = $outcome;
        }

        self::assertSame(PartnerStatus::Ok, $byId['aurum']->status);
        self::assertSame(PartnerStatus::Error, $byId['bastion']->status);
        self::assertSame(PartnerStatus::Ok, $byId['celeris']->status);
        self::assertSame(PartnerStatus::Error, $byId['dorsal']->status);
        self::assertNotNull($byId['aurum']->offer);
        self::assertNull($byId['bastion']->offer);
    }

    private function gateway(MockResponse $response): HttpPartnerGateway
    {
        return new HttpPartnerGateway(new MockHttpClient([$response]), new PartnerResponseParser());
    }

    private static function success(string $partner, int $cents): MockResponse
    {
        return new MockResponse(json_encode([
            'partner' => $partner,
            'coverage' => 'third_party_plus',
            'annual_premium_cents' => $cents,
            'currency' => 'EUR',
        ], JSON_THROW_ON_ERROR));
    }
}
