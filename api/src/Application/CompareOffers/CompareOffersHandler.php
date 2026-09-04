<?php

declare(strict_types=1);

namespace App\Application\CompareOffers;

use App\Application\Port\Clock;
use App\Application\Port\MetricsRecorder;
use App\Application\Port\PartnerGateway;
use App\Application\Port\PartnerRegistry;
use App\Domain\Comparison\Comparison;
use App\Domain\Comparison\PartnerOutcome;
use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\Offer;
use App\Domain\Offer\OfferSorter;

/**
 * Sequence of specs/03-architecture.md section 3.1, without campaigns or the
 * circuit breaker: every enabled partner is treated as callable, and offers
 * keep the partner's price as the final price.
 */
final readonly class CompareOffersHandler
{
    public function __construct(
        private PartnerRegistry $partners,
        private PartnerGateway $gateway,
        private MetricsRecorder $metrics,
        private Clock $clock,
        private int $deadlineMs,
    ) {
    }

    public function handle(CompareOffersQuery $query): Comparison
    {
        $startedMs = $this->clock->monotonicMs();

        $callable = $this->partners->enabledPartners();
        $outcomes = $this->gateway->fetchQuotes($query->request, $callable, $this->deadlineMs);

        $offers = [];
        foreach ($outcomes as $outcome) {
            if (PartnerStatus::Ok === $outcome->status && $outcome->offer instanceof Offer) {
                $offers[] = $outcome->offer;
            }
        }

        $offers = OfferSorter::sortByFinalPrice($offers);
        $outcomes = $this->sortOutcomes($outcomes);

        $durationMs = $this->clock->monotonicMs() - $startedMs;

        $this->metrics->recordComparisonDuration($durationMs);
        foreach ($outcomes as $outcome) {
            $this->metrics->recordPartnerOutcome($outcome->partnerId, $outcome->status, $outcome->durationMs);
        }

        return new Comparison(
            $query->comparisonId,
            $query->request->coverage,
            $durationMs,
            $offers,
            $outcomes,
        );
    }

    /**
     * @param list<PartnerOutcome> $outcomes
     *
     * @return list<PartnerOutcome>
     */
    private function sortOutcomes(array $outcomes): array
    {
        $sorted = $outcomes;

        usort(
            $sorted,
            static fn (PartnerOutcome $a, PartnerOutcome $b): int => strcmp($a->partnerId->value, $b->partnerId->value),
        );

        return $sorted;
    }
}
