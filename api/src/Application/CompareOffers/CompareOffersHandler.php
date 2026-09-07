<?php

declare(strict_types=1);

namespace App\Application\CompareOffers;

use App\Application\Port\CampaignRepository;
use App\Application\Port\CircuitBreaker;
use App\Application\Port\Clock;
use App\Application\Port\MetricsRecorder;
use App\Application\Port\PartnerGateway;
use App\Application\Port\PartnerRegistry;
use App\Application\Port\RegisteredPartner;
use App\Domain\Comparison\CircuitBreakerTransition;
use App\Domain\Comparison\Comparison;
use App\Domain\Comparison\PartnerOutcome;
use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\Offer;
use App\Domain\Offer\OfferSorter;
use Psr\Log\LoggerInterface;

/**
 * Sequence of specs/03-architecture.md section 3.1. Open partners are not
 * called and are reported as skipped (section 3.5). Campaigns are applied
 * to collected offers before sorting (section 3.1 steps 7–8).
 */
final readonly class CompareOffersHandler
{
    public function __construct(
        private PartnerRegistry $partners,
        private CircuitBreaker $breaker,
        private PartnerGateway $gateway,
        private CampaignRepository $campaigns,
        private MetricsRecorder $metrics,
        private Clock $clock,
        private LoggerInterface $logger,
        private int $deadlineMs,
    ) {
    }

    public function handle(CompareOffersQuery $query): Comparison
    {
        $startedMs = $this->clock->monotonicMs();

        $enabled = $this->partners->enabledPartners();
        $callable = $this->callablePartners($enabled);
        $outcomes = $this->skippedOutcomes($enabled, $callable);
        $fetched = $this->gateway->fetchQuotes($query->request, $callable, $this->deadlineMs);

        foreach ($fetched as $outcome) {
            $outcomes[] = $outcome;
        }

        foreach ($outcomes as $outcome) {
            $transition = $this->breaker->recordOutcome($outcome->partnerId, $outcome->status);
            if (CircuitBreakerTransition::Opened === $transition) {
                $this->metrics->recordCircuitBreakerOpened($outcome->partnerId);
            }
            if (null !== $transition) {
                $this->logger->warning('circuit_breaker.transition', [
                    'comparison_id' => $query->comparisonId,
                    'partner' => $outcome->partnerId->value,
                    'new_state' => $transition->value,
                ]);
            }
        }

        $today = $this->clock->today();
        $offers = [];
        foreach ($outcomes as $outcome) {
            if (PartnerStatus::Ok === $outcome->status && $outcome->offer instanceof Offer) {
                $campaign = $this->campaigns->findForPartner($outcome->offer->partnerId, $today);
                $offer = $outcome->offer->withCampaign($campaign, $today);
                $offers[] = $offer;
                if (null !== $offer->discount) {
                    $this->metrics->recordCampaignApplied($offer->partnerId);
                }
            }
        }

        $offers = OfferSorter::sortByFinalPrice($offers);
        $outcomes = $this->sortOutcomes($outcomes);

        $durationMs = $this->clock->monotonicMs() - $startedMs;

        $this->metrics->recordComparison($query->request->coverage, $durationMs, count($offers));
        foreach ($outcomes as $outcome) {
            $this->metrics->recordPartnerOutcome($outcome->partnerId, $outcome->status, $outcome->durationMs);
            if (PartnerStatus::Ok !== $outcome->status && PartnerStatus::Skipped !== $outcome->status) {
                $this->logger->warning('partner.failure', [
                    'comparison_id' => $query->comparisonId,
                    'partner' => $outcome->partnerId->value,
                    'status' => $outcome->status->value,
                    'duration_ms' => $outcome->durationMs,
                    'http_status' => $outcome->httpStatus,
                ]);
            }
        }

        $partnerStatuses = [];
        foreach ($outcomes as $outcome) {
            $partnerStatuses[$outcome->partnerId->value] = $outcome->status->value;
        }

        $this->logger->info('comparison.completed', [
            'comparison_id' => $query->comparisonId,
            'duration_ms' => $durationMs,
            'offer_count' => count($offers),
            'coverage' => $query->request->coverage->value,
            'partner_statuses' => $partnerStatuses,
        ]);

        return new Comparison(
            $query->comparisonId,
            $query->request->coverage,
            $durationMs,
            $offers,
            $outcomes,
        );
    }

    /**
     * @param list<RegisteredPartner> $enabled
     *
     * @return list<RegisteredPartner>
     */
    private function callablePartners(array $enabled): array
    {
        $ids = [];
        foreach ($enabled as $partner) {
            $ids[] = $partner->id;
        }

        $callableIds = [];
        foreach ($this->breaker->callablePartners($ids) as $id) {
            $callableIds[$id->value] = true;
        }

        $callable = [];
        foreach ($enabled as $partner) {
            if (isset($callableIds[$partner->id->value])) {
                $callable[] = $partner;
            }
        }

        return $callable;
    }

    /**
     * @param list<RegisteredPartner> $enabled
     * @param list<RegisteredPartner> $callable
     *
     * @return list<PartnerOutcome>
     */
    private function skippedOutcomes(array $enabled, array $callable): array
    {
        $callableIds = [];
        foreach ($callable as $partner) {
            $callableIds[$partner->id->value] = true;
        }

        $skipped = [];
        foreach ($enabled as $partner) {
            if (!isset($callableIds[$partner->id->value])) {
                $skipped[] = new PartnerOutcome($partner->id, PartnerStatus::Skipped, 0, null);
            }
        }

        return $skipped;
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
