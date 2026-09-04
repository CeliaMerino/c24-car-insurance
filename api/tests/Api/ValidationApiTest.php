<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\QuoteRequestBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L5. Validation of specs/05-api-contract.md sections 2.1, 2.4 and 2.5.
 */
final class ValidationApiTest extends WebTestCase
{
    #[Test]
    public function an_underage_customer_is_rejected_with_min_age(): void
    {
        $payload = QuoteRequestBuilder::vectorA()
            ->withDateOfBirth('2008-09-01')
            ->toPayload();

        $response = $this->postComparison($payload);

        self::assertSame(422, $response['status']);
        self::assertSame('application/problem+json', $response['content_type']);
        self::assertStringContainsString('no-store', (string) $response['cache_control']);
        self::assertSame('https://check24.example/problems/validation-error', $response['body']['type']);
        self::assertSame('Validation failed', $response['body']['title']);
        self::assertSame(422, $response['body']['status']);
        self::assertSame(
            [
                [
                    'field' => 'date_of_birth',
                    'code' => 'min_age',
                    'message' => 'You must be at least 18 years old.',
                ],
            ],
            $response['body']['errors'],
        );
    }

    #[Test]
    public function a_future_date_of_birth_is_rejected(): void
    {
        $payload = QuoteRequestBuilder::vectorA()
            ->withDateOfBirth('2026-09-01')
            ->toPayload();

        $response = $this->postComparison($payload);

        self::assertSame(422, $response['status']);
        self::assertSame('future_date', $this->errorByField($response['body']['errors'], 'date_of_birth')['code']);
    }

    #[Test]
    public function all_invalid_fields_are_reported_in_one_response(): void
    {
        $payload = QuoteRequestBuilder::vectorA()
            ->withDateOfBirth('2008-09-01')
            ->withPostalCode('12')
            ->toPayload();
        $payload['car_category'] = 'spaceship';

        $response = $this->postComparison($payload);

        self::assertSame(422, $response['status']);
        $byField = $this->errorsByField($response['body']['errors']);
        self::assertCount(3, $response['body']['errors']);
        self::assertSame('min_age', $byField['date_of_birth']['code']);
        self::assertSame('You must be at least 18 years old.', $byField['date_of_birth']['message']);
        self::assertSame('format', $byField['postal_code']['code']);
        self::assertSame('Enter a five-digit postal code.', $byField['postal_code']['message']);
        self::assertSame('unknown_value', $byField['car_category']['code']);
    }

    #[Test]
    public function unknown_fields_are_rejected_rather_than_ignored(): void
    {
        $payload = QuoteRequestBuilder::vectorA()->toPayload();
        $payload['driver_name'] = 'Ada';
        $payload['extra'] = true;

        $response = $this->postComparison($payload);

        self::assertSame(422, $response['status']);
        $byField = $this->errorsByField($response['body']['errors']);
        self::assertArrayHasKey('driver_name', $byField);
        self::assertSame('unknown_field', $byField['driver_name']['code']);
        self::assertArrayHasKey('extra', $byField);
        self::assertSame('unknown_field', $byField['extra']['code']);
    }

    #[Test]
    public function every_missing_field_is_required(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/v1/comparisons',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: '{}',
        );

        $response = $this->decode($client);

        self::assertSame(422, $response['status']);
        $byField = $this->errorsByField($response['body']['errors']);
        foreach (['date_of_birth', 'postal_code', 'car_category', 'usage', 'annual_mileage', 'garage', 'coverage'] as $field) {
            self::assertArrayHasKey($field, $byField, $field);
            self::assertSame('required', $byField[$field]['code']);
        }
    }

    #[Test]
    public function invalid_json_is_400(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/v1/comparisons',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: '{',
        );

        $response = $this->decode($client);

        self::assertSame(400, $response['status']);
        self::assertSame('application/problem+json', $response['content_type']);
        self::assertStringContainsString('no-store', (string) $response['cache_control']);
        self::assertSame('https://check24.example/problems/malformed-request', $response['body']['type']);
        self::assertSame(400, $response['body']['status']);
    }

    #[Test]
    public function a_non_json_content_type_is_400(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/v1/comparisons',
            server: [
                'CONTENT_TYPE' => 'text/plain',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(QuoteRequestBuilder::vectorA()->toPayload(), JSON_THROW_ON_ERROR),
        );

        $response = $this->decode($client);

        self::assertSame(400, $response['status']);
        self::assertSame('application/problem+json', $response['content_type']);
        self::assertStringContainsString('no-store', (string) $response['cache_control']);
    }

    #[Test]
    public function a_json_array_is_400(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/v1/comparisons',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: '[]',
        );

        $response = $this->decode($client);

        self::assertSame(400, $response['status']);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array{status: int, content_type: ?string, cache_control: ?string, body: array<string, mixed>}
     */
    private function postComparison(array $body): array
    {
        $client = static::createClient();
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
     * @return array{status: int, content_type: ?string, cache_control: ?string, body: array<string, mixed>}
     */
    private function decode(KernelBrowser $client): array
    {
        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertJson($content);

        /** @var array<string, mixed> $body */
        $body = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $contentType = $response->headers->get('Content-Type');
        if (is_string($contentType) && str_contains($contentType, ';')) {
            $contentType = trim(explode(';', $contentType)[0]);
        }

        return [
            'status' => $response->getStatusCode(),
            'content_type' => $contentType,
            'cache_control' => $response->headers->get('Cache-Control'),
            'body' => $body,
        ];
    }

    /**
     * @param list<array<string, mixed>> $errors
     *
     * @return array<string, array<string, mixed>>
     */
    private function errorsByField(array $errors): array
    {
        $byField = [];
        foreach ($errors as $error) {
            $byField[(string) $error['field']] = $error;
        }

        return $byField;
    }

    /**
     * @param list<array<string, mixed>> $errors
     *
     * @return array<string, mixed>
     */
    private function errorByField(array $errors, string $field): array
    {
        $byField = $this->errorsByField($errors);
        self::assertArrayHasKey($field, $byField);

        return $byField[$field];
    }
}
