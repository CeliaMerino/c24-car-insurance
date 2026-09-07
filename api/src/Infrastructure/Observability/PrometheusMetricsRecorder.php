<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use App\Application\Port\MetricsRecorder;
use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\PartnerId;
use App\Domain\Quote\CoverageLevel;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Histogram;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * APCu-backed recorder for the metrics in specs/07-observability.md section 3.
 */
#[AsAlias(MetricsRecorder::class)]
final class PrometheusMetricsRecorder implements MetricsRecorder
{
    private const string NAMESPACE = 'c24';

    /** @var list<float> */
    private const array COMPARISON_DURATION_BUCKETS = [
        0.1, 0.25, 0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 3.5, 5.0,
    ];

    /** @var list<float> */
    private const array PARTNER_DURATION_BUCKETS = [
        0.05, 0.1, 0.25, 0.5, 1.0, 1.5, 2.0, 2.5, 3.0,
    ];

    /** @var list<float> */
    private const array OFFER_COUNT_BUCKETS = [0.0, 1.0, 2.0, 3.0, 4.0];

    private readonly Counter $comparisonsTotal;
    private readonly Histogram $comparisonDuration;
    private readonly Histogram $comparisonOffers;
    private readonly Counter $partnerRequestsTotal;
    private readonly Histogram $partnerRequestDuration;
    private readonly Counter $circuitBreakerOpenedTotal;
    private readonly Counter $validationErrorsTotal;
    private readonly Counter $campaignAppliedTotal;
    private readonly Counter $httpResponsesTotal;
    private readonly Counter $frontendEventsTotal;

    public function __construct(CollectorRegistry $registry)
    {
        $this->comparisonsTotal = $registry->getOrRegisterCounter(
            self::NAMESPACE,
            'comparisons_total',
            'Comparisons completed, by coverage tier',
            ['coverage'],
        );
        $this->comparisonDuration = $registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'comparison_duration_seconds',
            'End-to-end comparison duration in seconds',
            [],
            self::COMPARISON_DURATION_BUCKETS,
        );
        $this->comparisonOffers = $registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'comparison_offers',
            'Number of offers returned by a comparison',
            [],
            self::OFFER_COUNT_BUCKETS,
        );
        $this->partnerRequestsTotal = $registry->getOrRegisterCounter(
            self::NAMESPACE,
            'partner_requests_total',
            'Partner outcomes by partner and status',
            ['partner', 'status'],
        );
        $this->partnerRequestDuration = $registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'partner_request_duration_seconds',
            'Partner call duration in seconds',
            ['partner'],
            self::PARTNER_DURATION_BUCKETS,
        );
        $this->circuitBreakerOpenedTotal = $registry->getOrRegisterCounter(
            self::NAMESPACE,
            'circuit_breaker_opened_total',
            'Circuit breaker open transitions by partner',
            ['partner'],
        );
        $this->validationErrorsTotal = $registry->getOrRegisterCounter(
            self::NAMESPACE,
            'validation_errors_total',
            'Validation errors by field and code',
            ['field', 'code'],
        );
        $this->campaignAppliedTotal = $registry->getOrRegisterCounter(
            self::NAMESPACE,
            'campaign_applied_total',
            'Campaign discounts applied by partner',
            ['partner'],
        );
        $this->httpResponsesTotal = $registry->getOrRegisterCounter(
            self::NAMESPACE,
            'http_responses_total',
            'HTTP responses by route and status',
            ['route', 'status'],
        );
        $this->frontendEventsTotal = $registry->getOrRegisterCounter(
            self::NAMESPACE,
            'frontend_events_total',
            'Frontend funnel events',
            ['event'],
        );
    }

    public function recordComparison(CoverageLevel $coverage, int $durationMs, int $offerCount): void
    {
        $this->comparisonsTotal->inc([$coverage->value]);
        $this->comparisonDuration->observe($durationMs / 1000.0);
        $this->comparisonOffers->observe((float) $offerCount);
    }

    public function recordPartnerOutcome(PartnerId $partnerId, PartnerStatus $status, int $durationMs): void
    {
        $this->partnerRequestsTotal->inc([$partnerId->value, $status->value]);

        if (PartnerStatus::Skipped !== $status) {
            $this->partnerRequestDuration->observe($durationMs / 1000.0, [$partnerId->value]);
        }
    }

    public function recordCircuitBreakerOpened(PartnerId $partnerId): void
    {
        $this->circuitBreakerOpenedTotal->inc([$partnerId->value]);
    }

    public function recordValidationError(string $field, string $code): void
    {
        $this->validationErrorsTotal->inc([$field, $code]);
    }

    public function recordCampaignApplied(PartnerId $partnerId): void
    {
        $this->campaignAppliedTotal->inc([$partnerId->value]);
    }

    public function recordHttpResponse(string $route, int $status): void
    {
        $this->httpResponsesTotal->inc([$route, (string) $status]);
    }

    public function recordFrontendEvent(string $event): void
    {
        $this->frontendEventsTotal->inc([$event]);
    }
}
