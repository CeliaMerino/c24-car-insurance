<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Records request() vs stream() so L3 can prove every partner is dispatched
 * before any response is read, without making a real network call.
 */
final class RecordingHttpClient implements HttpClientInterface
{
    /** @var list<string> */
    public array $events = [];

    /** @var list<array<string, mixed>> */
    public array $requestOptions = [];

    public ?float $streamTimeout = null;

    public function __construct(
        private HttpClientInterface $inner,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $this->events[] = 'request';
        $this->requestOptions[] = $options;

        return $this->inner->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        $this->events[] = 'stream';
        $this->streamTimeout = $timeout;

        return $this->inner->stream($responses, $timeout);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->inner = $this->inner->withOptions($options);

        return $clone;
    }
}
