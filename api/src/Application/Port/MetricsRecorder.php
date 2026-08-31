<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\PartnerId;

interface MetricsRecorder
{
    public function recordComparisonDuration(int $durationMs): void;

    public function recordPartnerOutcome(PartnerId $partnerId, PartnerStatus $status, int $durationMs): void;
}
