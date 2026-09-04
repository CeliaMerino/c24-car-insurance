<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\MetricsRecorder;
use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\PartnerId;

final class RecordingMetricsRecorder implements MetricsRecorder
{
    public ?int $comparisonDurationMs = null;

    /** @var list<array{partner: string, status: PartnerStatus, durationMs: int}> */
    public array $partnerOutcomes = [];

    public function recordComparisonDuration(int $durationMs): void
    {
        $this->comparisonDurationMs = $durationMs;
    }

    public function recordPartnerOutcome(PartnerId $partnerId, PartnerStatus $status, int $durationMs): void
    {
        $this->partnerOutcomes[] = [
            'partner' => $partnerId->value,
            'status' => $status,
            'durationMs' => $durationMs,
        ];
    }
}
