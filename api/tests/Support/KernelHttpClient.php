<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/**
 * Turns partner HTTP calls into in-process kernel requests so L5 can use the
 * real simulator and PARTNER_{ID}_FORCE without opening a TCP port.
 *
 * Honours max_duration after the simulator returns: a forced timeout sleeps
 * longer than the scaled per-partner ceiling, and is then mapped to a transport
 * timeout rather than a successful quote.
 */
final class KernelHttpClient implements HttpClientInterface
{
    private MockHttpClient $inner;

    public function __construct(KernelInterface $kernel)
    {
        $this->inner = new MockHttpClient(
            fn (string $method, string $url, array $options): MockResponse => $this->execute($kernel, $method, $url, $options),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->inner->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
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

    /**
     * @param array<string, mixed> $options
     */
    private function execute(KernelInterface $kernel, string $method, string $url, array $options): MockResponse
    {
        $startedNs = hrtime(true);
        $body = $options['body'] ?? '';
        if (!is_string($body)) {
            $body = '';
        }

        $request = Request::create($url, $method, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: $body);

        $response = $kernel->handle($request, HttpKernelInterface::MAIN_REQUEST);

        $maxDuration = $options['max_duration'] ?? 0.0;
        $elapsedSeconds = (hrtime(true) - $startedNs) / 1_000_000_000;
        if (is_numeric($maxDuration) && (float) $maxDuration > 0.0 && $elapsedSeconds > (float) $maxDuration) {
            return new MockResponse('', ['error' => 'Timeout was reached']);
        }

        $headers = [];
        foreach ($response->headers->all() as $name => $values) {
            foreach ($values as $value) {
                if (is_string($value)) {
                    $headers[] = $name.': '.$value;
                }
            }
        }

        return new MockResponse((string) $response->getContent(), [
            'http_code' => $response->getStatusCode(),
            'response_headers' => $headers,
        ]);
    }
}
