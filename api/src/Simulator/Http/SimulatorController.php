<?php

declare(strict_types=1);

namespace App\Simulator\Http;

use App\Simulator\Behaviour\BehaviourProfileRegistry;
use App\Simulator\Behaviour\BehaviourSelector;
use App\Simulator\Behaviour\ResponseOutcome;
use App\Simulator\Clock\SimulatorClock;
use App\Simulator\Pricing\PricingEngine;
use App\Simulator\Pricing\PricingEngineRegistry;
use App\Simulator\Quote\InvalidQuotePayload;
use App\Simulator\Quote\QuoteInput;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * The four partners, as the HTTP services of specs/05-api-contract.md
 * section 4.
 *
 * The route is declared in config/routes/simulator.php rather than as an
 * attribute here, because `APP_SIMULATOR_ENABLED` has to keep it out of the API
 * container's router entirely.
 *
 * Latency is a plain sleep, which specs/04-providers.md section 5.3 permits
 * because each partner call is a separate request served by its own worker. It
 * requires a worker pool sized for at least four concurrent requests.
 */
#[AsController]
final class SimulatorController
{
    private const string CURRENCY = 'EUR';

    /** specs/04-providers.md section 4.1. The system does not retry, and ignores this (ADR-005). */
    private const int RETRY_AFTER_SECONDS = 30;

    public function __construct(
        private readonly PricingEngineRegistry $engines,
        private readonly BehaviourProfileRegistry $profiles,
        private readonly BehaviourSelector $selector,
        private readonly SimulatorClock $clock,
    ) {
    }

    public function quote(string $partner, Request $request): Response
    {
        $engine = $this->engines->find($partner);
        $profile = $this->profiles->find($partner);

        if (null === $engine || null === $profile) {
            return new JsonResponse(['error' => 'unknown_partner'], Response::HTTP_NOT_FOUND);
        }

        try {
            $input = QuoteInput::fromPayload($this->decode($request), $this->clock->today());
        } catch (InvalidQuotePayload $e) {
            return new JsonResponse(['error' => 'invalid_request', 'detail' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $decision = $this->selector->decide($profile, $partner.'|'.$input->fingerprint());

        if ($decision->latencyMs > 0) {
            usleep($decision->latencyMs * 1000);
        }

        return match ($decision->outcome) {
            ResponseOutcome::Success => $this->success($partner, $engine, $input),
            ResponseOutcome::HttpError => $this->httpError($decision->httpErrorStatus),
            ResponseOutcome::Malformed => $this->malformed($partner),
            ResponseOutcome::NonJson => $this->nonJson(),
            ResponseOutcome::ConnectionError => $this->connectionError(),
        };
    }

    /** specs/04-providers.md section 2.2. */
    private function success(string $partner, PricingEngine $engine, QuoteInput $input): JsonResponse
    {
        return new JsonResponse([
            'partner' => $partner,
            'coverage' => $input->coverage->value,
            'annual_premium_cents' => $engine->priceCents($input),
            'currency' => self::CURRENCY,
        ]);
    }

    private function httpError(int $status): JsonResponse
    {
        return new JsonResponse(['error' => 'service_unavailable', 'retry_after' => self::RETRY_AFTER_SECONDS], $status);
    }

    /** HTTP 200, valid JSON, wrong shape. */
    private function malformed(string $partner): JsonResponse
    {
        return new JsonResponse(['partner' => $partner, 'premium' => 'unavailable']);
    }

    /** HTTP 200, HTML. */
    private function nonJson(): Response
    {
        return new Response(
            '<html><body><h1>502 Bad Gateway</h1></body></html>',
            Response::HTTP_OK,
            ['Content-Type' => 'text/html'],
        );
    }

    /**
     * A request that has already been accepted cannot be refused at the TCP
     * level, so the closest equivalent is a transfer that ends before the
     * promised body arrives. A client sees a transport error rather than an
     * HTTP response, which is what `connection refused` produces for the
     * adapter.
     */
    private function connectionError(): Response
    {
        $response = new StreamedResponse(static function (): void {}, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'application/json');
        $response->headers->set('Content-Length', '1024');

        return $response;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidQuotePayload
     */
    private function decode(Request $request): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new InvalidQuotePayload('Request body is not valid JSON.', previous: $e);
        }

        if (!is_array($decoded)) {
            throw new InvalidQuotePayload('Request body must be a JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
