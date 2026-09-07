<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\MetricsRecorder;
use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\PartnerId;
use App\Domain\Quote\CoverageLevel;

final class RecordingMetricsRecorder implements MetricsRecorder
{
    public ?CoverageLevel $comparisonCoverage = null;

    public ?int $comparisonDurationMs = null;

    public ?int $offerCount = null;

    /** @var list<array{partner: string, status: PartnerStatus, durationMs: int}> */
    public array $partnerOutcomes = [];

    /** @var list<string> */
    public array $circuitBreakerOpened = [];

    /** @var list<array{field: string, code: string}> */
    public array $validationErrors = [];

    /** @var list<string> */
    public array $campaignsApplied = [];

    /** @var list<array{route: string, status: int}> */
    public array $httpResponses = [];

    /** @var list<string> */
    public array $frontendEvents = [];

    public function recordComparison(CoverageLevel $coverage, int $durationMs, int $offerCount): void
    {
        $this->comparisonCoverage = $coverage;
        $this->comparisonDurationMs = $durationMs;
        $this->offerCount = $offerCount;
    }

    public function recordPartnerOutcome(PartnerId $partnerId, PartnerStatus $status, int $durationMs): void
    {
        $this->partnerOutcomes[] = [
            'partner' => $partnerId->value,
            'status' => $status,
            'durationMs' => $durationMs,
        ];
    }

    public function recordCircuitBreakerOpened(PartnerId $partnerId): void
    {
        $this->circuitBreakerOpened[] = $partnerId->value;
    }

    public function recordValidationError(string $field, string $code): void
    {
        $this->validationErrors[] = ['field' => $field, 'code' => $code];
    }

    public function recordCampaignApplied(PartnerId $partnerId): void
    {
        $this->campaignsApplied[] = $partnerId->value;
    }

    public function recordHttpResponse(string $route, int $status): void
    {
        $this->httpResponses[] = ['route' => $route, 'status' => $status];
    }

    public function recordFrontendEvent(string $event): void
    {
        $this->frontendEvents[] = $event;
    }
}
