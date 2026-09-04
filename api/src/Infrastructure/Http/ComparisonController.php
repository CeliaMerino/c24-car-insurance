<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\CompareOffers\CompareOffersHandler;
use App\Application\CompareOffers\CompareOffersQuery;
use App\Application\Port\Clock;
use App\Domain\Validation\ValidationException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * POST /api/v1/comparisons of specs/05-api-contract.md section 2.
 */
#[AsController]
final readonly class ComparisonController
{
    public function __construct(
        private QuoteRequestParser $parser,
        private CompareOffersHandler $handler,
        private ComparisonResponseMapper $mapper,
        private Clock $clock,
    ) {
    }

    #[Route('/api/v1/comparisons', name: 'api_v1_comparisons', methods: ['POST'])]
    public function compare(Request $request): Response
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

        /** @var array<string, mixed> $decoded */
        try {
            $quoteRequest = $this->parser->parse($decoded, $this->clock->today());
        } catch (ValidationException $exception) {
            return ProblemResponse::validation($exception->errors);
        }

        $comparison = $this->handler->handle(new CompareOffersQuery(
            $quoteRequest,
            (new Ulid())->toString(),
        ));

        return new JsonResponse($this->mapper->map($comparison));
    }

    private function isJson(Request $request): bool
    {
        $contentType = $request->headers->get('Content-Type') ?? '';
        $mediaType = strtolower(trim(explode(';', $contentType)[0]));

        return 'application/json' === $mediaType;
    }
}
