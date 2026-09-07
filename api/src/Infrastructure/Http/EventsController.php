<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Port\MetricsRecorder;
use App\Domain\Validation\ValidationError;
use JsonException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/v1/events — browser funnel events (specs/07-observability.md section 3.2).
 */
#[AsController]
final readonly class EventsController
{
    private const array ACCEPTED = [
        'form_started' => true,
        'form_restored' => true,
        'results_viewed' => true,
    ];

    public function __construct(
        private MetricsRecorder $metrics,
    ) {
    }

    #[Route('/api/v1/events', name: 'api_v1_events', methods: ['POST'])]
    public function record(Request $request): Response
    {
        if (!$this->isJson($request)) {
            return ProblemResponse::malformed('Content-Type must be application/json.');
        }

        $content = $request->getContent();

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ProblemResponse::malformed('Request body is not valid JSON.');
        }

        if (!is_array($decoded) || !str_starts_with(ltrim($content), '{')) {
            return ProblemResponse::malformed('Request body must be a JSON object.');
        }

        $event = $decoded['event'] ?? null;
        if (!is_string($event) || !isset(self::ACCEPTED[$event])) {
            return ProblemResponse::validation([
                new ValidationError(
                    'event',
                    'unknown_event',
                    'Event must be one of: form_started, form_restored, results_viewed.',
                ),
            ]);
        }

        $this->metrics->recordFrontendEvent($event);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function isJson(Request $request): bool
    {
        $contentType = $request->headers->get('Content-Type') ?? '';
        $mediaType = strtolower(trim(explode(';', $contentType)[0]));

        return 'application/json' === $mediaType;
    }
}
