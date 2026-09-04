<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Stub for GET /metrics (specs/05-api-contract.md section 3). The Prometheus
 * recorder arrives in the observability phase; this only reserves the path
 * and the exposition content type.
 */
#[AsController]
final class MetricsController
{
    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function metrics(): Response
    {
        return new Response(
            "# Metrics are not implemented yet.\n",
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8'],
        );
    }
}
