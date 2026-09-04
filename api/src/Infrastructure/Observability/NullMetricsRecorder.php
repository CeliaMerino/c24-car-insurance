<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use App\Application\Port\MetricsRecorder;
use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\PartnerId;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * Placeholder until the Prometheus recorder of phase 8. The handler still
 * reports every outcome so that swapping the implementation does not change
 * orchestration.
 */
#[AsAlias(MetricsRecorder::class)]
final class NullMetricsRecorder implements MetricsRecorder
{
    public function recordComparisonDuration(int $durationMs): void
    {
    }

    public function recordPartnerOutcome(PartnerId $partnerId, PartnerStatus $status, int $durationMs): void
    {
    }
}
