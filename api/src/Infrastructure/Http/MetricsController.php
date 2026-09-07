<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /metrics — Prometheus exposition (specs/05-api-contract.md section 3,
 * specs/07-observability.md section 3).
 */
#[AsController]
final readonly class MetricsController
{
    public function __construct(
        private CollectorRegistry $registry,
    ) {
    }

    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function metrics(): Response
    {
        $renderer = new RenderTextFormat();
        $body = $renderer->render($this->registry->getMetricFamilySamples());

        return new Response(
            $body,
            Response::HTTP_OK,
            ['Content-Type' => RenderTextFormat::MIME_TYPE],
        );
    }
}
