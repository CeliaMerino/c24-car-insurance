<?php

declare(strict_types=1);

namespace App\Infrastructure\Partner;

use App\Application\Port\PartnerGateway;
use App\Application\Port\RegisteredPartner;
use App\Domain\Comparison\PartnerOutcome;
use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\Offer;
use App\Domain\Quote\QuoteRequest;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;

/**
 * Concurrent partner HTTP, as specified in specs/03-architecture.md sections 3.2–3.4.
 *
 * CurlHttpClient is required: NativeHttpClient does not multiplex. Every request
 * sets both `timeout` and `max_duration`. The global deadline is the timeout
 * argument to stream(). Nothing thrown by a partner leaves this class.
 */
#[AsAlias(PartnerGateway::class)]
final class HttpPartnerGateway implements PartnerGateway
{
    public function __construct(
        #[Autowire(service: CurlHttpClient::class)]
        private readonly HttpClientInterface $client,
        private readonly PartnerResponseParser $parser,
    ) {
        if ($this->client instanceof NativeHttpClient) {
            throw new LogicException(
                'Partner calls use CurlHttpClient explicitly. NativeHttpClient does not multiplex.',
            );
        }
    }

    public function fetchQuotes(QuoteRequest $request, array $partners, int $deadlineMs): array
    {
        if ([] === $partners) {
            return [];
        }

        $startedNs = hrtime(true);
        $payload = $this->payload($request);

        /** @var array<string, RegisteredPartner> $byId */
        $byId = [];
        /** @var array<string, ResponseInterface> $responses */
        $responses = [];
        /** @var list<PartnerOutcome> $outcomes */
        $outcomes = [];

        foreach ($partners as $partner) {
            $byId[$partner->id->value] = $partner;
            $seconds = $partner->timeoutMs / 1000.0;

            try {
                $responses[$partner->id->value] = $this->client->request('POST', $partner->quoteUrl, [
                    'json' => $payload,
                    'timeout' => $seconds,
                    'max_duration' => $seconds,
                    'user_data' => $partner->id->value,
                ]);
            } catch (Throwable $e) {
                $outcomes[] = $this->outcome($partner, $this->statusFromFailure($e), $startedNs, null);
            }
        }

        $pending = $responses;

        if ([] === $pending) {
            return $outcomes;
        }

        $remainingSeconds = ($deadlineMs - $this->elapsedMs($startedNs)) / 1000.0;

        if ($remainingSeconds <= 0.0) {
            return [...$outcomes, ...$this->timeoutPending($pending, $byId, $startedNs)];
        }

        try {
            foreach ($this->client->stream($responses, $remainingSeconds) as $response => $chunk) {
                $id = $response->getInfo('user_data');
                if (!is_string($id) || !isset($pending[$id])) {
                    continue;
                }

                $partner = $byId[$id];
                $deadlineFired = $this->elapsedMs($startedNs) >= $deadlineMs;

                try {
                    if ($chunk->isTimeout()) {
                        if ($deadlineFired) {
                            $outcomes = [...$outcomes, ...$this->timeoutPending($pending, $byId, $startedNs)];
                            $pending = [];
                            break;
                        }

                        $this->cancelWithoutReading($response);
                        unset($pending[$id]);
                        $outcomes[] = $this->outcome($partner, PartnerStatus::Timeout, $startedNs, null);
                        continue;
                    }

                    if (!$chunk->isLast()) {
                        continue;
                    }

                    unset($pending[$id]);
                    $outcomes[] = $this->complete($response, $partner, $request, $startedNs);
                } catch (Throwable $e) {
                    $this->cancelWithoutReading($response);
                    unset($pending[$id]);
                    $outcomes[] = $this->outcome($partner, $this->statusFromFailure($e), $startedNs, null);
                }
            }
        } catch (Throwable $e) {
            foreach ($pending as $id => $response) {
                $this->cancelWithoutReading($response);
                unset($pending[$id]);
                $outcomes[] = $this->outcome($byId[$id], $this->statusFromFailure($e), $startedNs, null);
            }
        }

        if ([] !== $pending) {
            $outcomes = [...$outcomes, ...$this->timeoutPending($pending, $byId, $startedNs)];
        }

        return $outcomes;
    }

    /**
     * @param array<string, ResponseInterface> $pending
     * @param array<string, RegisteredPartner> $byId
     *
     * @return list<PartnerOutcome>
     */
    private function timeoutPending(array $pending, array $byId, int $startedNs): array
    {
        $outcomes = [];

        foreach ($pending as $id => $response) {
            $this->cancelWithoutReading($response);
            $outcomes[] = $this->outcome($byId[$id], PartnerStatus::Timeout, $startedNs, null);
        }

        return $outcomes;
    }

    private function complete(
        ResponseInterface $response,
        RegisteredPartner $partner,
        QuoteRequest $request,
        int $startedNs,
    ): PartnerOutcome {
        try {
            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);
        } catch (Throwable $e) {
            return $this->outcome($partner, $this->statusFromFailure($e), $startedNs, null);
        }

        try {
            $offer = $this->parser->parse($partner->id, $request->coverage, $statusCode, $body);
        } catch (InvalidPartnerResponse) {
            return $this->outcome($partner, PartnerStatus::Error, $startedNs, null);
        }

        return $this->outcome($partner, PartnerStatus::Ok, $startedNs, $offer);
    }

    private function outcome(
        RegisteredPartner $partner,
        PartnerStatus $status,
        int $startedNs,
        ?Offer $offer,
    ): PartnerOutcome {
        return new PartnerOutcome($partner->id, $status, $this->elapsedMs($startedNs), $offer);
    }

    private function statusFromFailure(Throwable $e): PartnerStatus
    {
        if ($e instanceof TimeoutExceptionInterface) {
            return PartnerStatus::Timeout;
        }

        if ($e instanceof TransportExceptionInterface) {
            $message = strtolower($e->getMessage());
            if (str_contains($message, 'timeout') || str_contains($message, 'timed out')) {
                return PartnerStatus::Timeout;
            }

            return PartnerStatus::Error;
        }

        return PartnerStatus::Error;
    }

    private function cancelWithoutReading(ResponseInterface $response): void
    {
        if (true === $response->getInfo('canceled')) {
            return;
        }

        try {
            $response->cancel();
        } catch (Throwable) {
        }
    }

    private function elapsedMs(int $startedNs): int
    {
        return (int) ((hrtime(true) - $startedNs) / 1_000_000);
    }

    /**
     * @return array<string, bool|string>
     */
    private function payload(QuoteRequest $request): array
    {
        return [
            'date_of_birth' => $request->dateOfBirth->toIsoDate(),
            'postal_code' => $request->postalCode->value(),
            'car_category' => $request->carCategory->value,
            'usage' => $request->usage->value,
            'annual_mileage' => $request->annualMileage->value,
            'garage' => $request->garage,
            'coverage' => $request->coverage->value,
        ];
    }
}
