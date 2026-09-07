<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\PartnerId;
use App\Domain\Quote\CoverageLevel;

/**
 * Prometheus metrics named in specs/07-observability.md section 3.
 * Every method maps to a metric that has a stated action.
 */
interface MetricsRecorder
{
    public function recordComparison(CoverageLevel $coverage, int $durationMs, int $offerCount): void;

    public function recordPartnerOutcome(PartnerId $partnerId, PartnerStatus $status, int $durationMs): void;

    public function recordCircuitBreakerOpened(PartnerId $partnerId): void;

    public function recordValidationError(string $field, string $code): void;

    public function recordCampaignApplied(PartnerId $partnerId): void;

    public function recordHttpResponse(string $route, int $status): void;

    public function recordFrontendEvent(string $event): void;
}
