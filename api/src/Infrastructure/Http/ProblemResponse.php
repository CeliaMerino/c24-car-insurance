<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Validation\ValidationError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * RFC 9457 problem+json bodies of specs/05-api-contract.md sections 2.4 and 2.5.
 */
final class ProblemResponse
{
    /**
     * @param list<ValidationError> $errors
     */
    public static function validation(array $errors): JsonResponse
    {
        return self::create(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'https://check24.example/problems/validation-error',
            'Validation failed',
            [
                'errors' => array_map(
                    static fn (ValidationError $error): array => [
                        'field' => $error->field,
                        'code' => $error->code,
                        'message' => $error->message,
                    ],
                    $errors,
                ),
            ],
        );
    }

    public static function malformed(string $detail): JsonResponse
    {
        return self::create(
            Response::HTTP_BAD_REQUEST,
            'https://check24.example/problems/malformed-request',
            'Malformed request',
            ['detail' => $detail],
        );
    }

    /**
     * @param array<string, mixed> $extra
     */
    private static function create(int $status, string $type, string $title, array $extra): JsonResponse
    {
        $response = new JsonResponse(
            [
                'type' => $type,
                'title' => $title,
                'status' => $status,
                ...$extra,
            ],
            $status,
            ['Content-Type' => 'application/problem+json'],
        );
        $response->headers->set('Content-Type', 'application/problem+json');

        return $response;
    }
}
