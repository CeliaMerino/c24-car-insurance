<?php

declare(strict_types=1);

namespace App\Tests\Application\CompareOffers;

use App\Application\CompareOffers\CompareOffersHandler;
use App\Application\CompareOffers\CompareOffersQuery;
use App\Application\Port\RegisteredPartner;
use App\Domain\Comparison\PartnerOutcome;
use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\Money;
use App\Domain\Offer\Offer;
use App\Domain\Offer\PartnerId;
use App\Domain\Quote\CoverageLevel;
use App\Infrastructure\CircuitBreaker\InMemoryCircuitBreaker;
use App\Tests\Support\FakeClock;
use App\Tests\Support\FakePartnerGateway;
use App\Tests\Support\InMemoryCampaignRepository;
use App\Tests\Support\InMemoryPartnerRegistry;
use App\Tests\Support\QuoteRequestBuilder;
use App\Tests\Support\RecordingMetricsRecorder;
use App\Tests\Support\ReferenceDates;
use App\Tests\Support\RegisteredPartners;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * L4. Circuit breaker transitions (specs/03-architecture.md section 3.5,
 * specs/06-testing.md sections 3 and 5). Fake gateway, frozen clock — no
 * real waiting.
 */
final class CircuitBreakerTest extends TestCase
{
    private const string COMPARISON_ID = '01TESTCOMPARISON00000000000';
    private const int THRESHOLD = 3;
    private const int COOLDOWN_S = 30;

    #[Test]
    public function a_partner_known_to_be_down_is_not_called_and_is_reported_as_skipped(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Error),
            'bastion' => $this->ok('bastion', new Money(48600)),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock);

        $handler->handle($this->query());
        $handler->handle($this->query());
        $handler->handle($this->query());

        $comparison = $handler->handle($this->query());

        self::assertSame(['bastion'], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Skipped, $this->partnerStatus($comparison->partnerOutcomes, 'aurum'));
        self::assertSame(PartnerStatus::Ok, $this->partnerStatus($comparison->partnerOutcomes, 'bastion'));
        self::assertSame(0, $this->outcome($comparison->partnerOutcomes, 'aurum')->durationMs);
        self::assertSame(['bastion'], array_map(
            static fn (Offer $offer): string => $offer->partnerId->value,
            $comparison->offers,
        ));
    }

    #[Test]
    public function two_consecutive_failures_do_not_open_the_breaker(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Error),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock, [RegisteredPartners::aurum()]);

        $handler->handle($this->query());
        $handler->handle($this->query());
        $comparison = $handler->handle($this->query());

        self::assertSame(['aurum'], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Error, $this->partnerStatus($comparison->partnerOutcomes, 'aurum'));
    }

    #[Test]
    public function timeouts_count_as_failures_toward_opening(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Timeout),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock, [RegisteredPartners::aurum()]);

        $handler->handle($this->query());
        $handler->handle($this->query());
        $handler->handle($this->query());
        $comparison = $handler->handle($this->query());

        self::assertSame([], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Skipped, $this->partnerStatus($comparison->partnerOutcomes, 'aurum'));
    }

    #[Test]
    public function timeouts_and_errors_together_count_as_consecutive_failures(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Timeout),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock, [RegisteredPartners::aurum()]);

        $handler->handle($this->query());
        $gateway->setOutcome('aurum', $this->failure('aurum', PartnerStatus::Error));
        $handler->handle($this->query());
        $gateway->setOutcome('aurum', $this->failure('aurum', PartnerStatus::Timeout));
        $handler->handle($this->query());
        $comparison = $handler->handle($this->query());

        self::assertSame([], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Skipped, $this->partnerStatus($comparison->partnerOutcomes, 'aurum'));
    }

    #[Test]
    public function a_success_resets_the_consecutive_failure_count(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Error),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock, [RegisteredPartners::aurum()]);

        $handler->handle($this->query());
        $handler->handle($this->query());
        $gateway->setOutcome('aurum', $this->ok('aurum', new Money(56900)));
        $handler->handle($this->query());
        $gateway->setOutcome('aurum', $this->failure('aurum', PartnerStatus::Error));
        $handler->handle($this->query());
        $handler->handle($this->query());
        $comparison = $handler->handle($this->query());

        self::assertSame(['aurum'], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Error, $this->partnerStatus($comparison->partnerOutcomes, 'aurum'));
    }

    #[Test]
    public function skipped_does_not_count_as_a_failure(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $aurum = new PartnerId('aurum');
        $breaker->recordOutcome($aurum, PartnerStatus::Error);
        $breaker->recordOutcome($aurum, PartnerStatus::Error);
        $breaker->recordOutcome($aurum, PartnerStatus::Skipped);

        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Error),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock, [RegisteredPartners::aurum()]);

        $comparison = $handler->handle($this->query());

        self::assertSame(['aurum'], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Error, $this->partnerStatus($comparison->partnerOutcomes, 'aurum'));
    }

    #[Test]
    public function skipped_does_not_close_an_open_breaker(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Error),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock, [RegisteredPartners::aurum()]);

        $handler->handle($this->query());
        $handler->handle($this->query());
        $handler->handle($this->query());
        $handler->handle($this->query());
        $comparison = $handler->handle($this->query());

        self::assertSame([], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Skipped, $this->partnerStatus($comparison->partnerOutcomes, 'aurum'));
    }

    #[Test]
    public function the_breaker_stays_open_until_the_cooldown_elapses(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Error),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock, [RegisteredPartners::aurum()]);

        $handler->handle($this->query());
        $handler->handle($this->query());
        $handler->handle($this->query());

        $clock->advanceMs(self::COOLDOWN_S * 1000 - 1);
        $stillOpen = $handler->handle($this->query());

        self::assertSame([], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Skipped, $this->partnerStatus($stillOpen->partnerOutcomes, 'aurum'));

        $clock->advanceMs(1);
        $trial = $handler->handle($this->query());

        self::assertSame(['aurum'], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Error, $this->partnerStatus($trial->partnerOutcomes, 'aurum'));
    }

    #[Test]
    public function a_successful_trial_after_cooldown_closes_the_breaker(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Error),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock, [RegisteredPartners::aurum()]);

        $handler->handle($this->query());
        $handler->handle($this->query());
        $handler->handle($this->query());

        $clock->advanceMs(self::COOLDOWN_S * 1000);
        $gateway->setOutcome('aurum', $this->ok('aurum', new Money(56900)));
        $handler->handle($this->query());

        $gateway->setOutcome('aurum', $this->failure('aurum', PartnerStatus::Error));
        $comparison = $handler->handle($this->query());

        self::assertSame(['aurum'], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Error, $this->partnerStatus($comparison->partnerOutcomes, 'aurum'));
    }

    #[Test]
    public function a_failed_trial_reopens_the_breaker(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Timeout),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock, [RegisteredPartners::aurum()]);

        $handler->handle($this->query());
        $handler->handle($this->query());
        $handler->handle($this->query());

        $clock->advanceMs(self::COOLDOWN_S * 1000);
        $handler->handle($this->query());

        $comparison = $handler->handle($this->query());

        self::assertSame([], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Skipped, $this->partnerStatus($comparison->partnerOutcomes, 'aurum'));
    }

    #[Test]
    public function opening_one_partner_does_not_skip_another(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Error),
            'bastion' => $this->ok('bastion', new Money(48600)),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock);

        $handler->handle($this->query());
        $handler->handle($this->query());
        $handler->handle($this->query());
        $comparison = $handler->handle($this->query());

        self::assertSame(['bastion'], $gateway->lastCalledPartnerIds);
        self::assertSame(PartnerStatus::Skipped, $this->partnerStatus($comparison->partnerOutcomes, 'aurum'));
        self::assertSame(PartnerStatus::Ok, $this->partnerStatus($comparison->partnerOutcomes, 'bastion'));
    }

    #[Test]
    public function every_partner_open_is_still_a_successful_comparison_with_zero_offers(): void
    {
        $clock = new FakeClock(ReferenceDates::frozen());
        $breaker = $this->breaker($clock);
        $gateway = new FakePartnerGateway([
            'aurum' => $this->failure('aurum', PartnerStatus::Error),
            'bastion' => $this->failure('bastion', PartnerStatus::Timeout),
            'celeris' => $this->failure('celeris', PartnerStatus::Error),
            'dorsal' => $this->failure('dorsal', PartnerStatus::Timeout),
        ]);
        $handler = $this->handler($gateway, $breaker, $clock, RegisteredPartners::four());

        $handler->handle($this->query());
        $handler->handle($this->query());
        $handler->handle($this->query());
        $comparison = $handler->handle($this->query());

        self::assertSame([], $gateway->lastCalledPartnerIds);
        self::assertSame([], $comparison->offers);
        self::assertCount(4, $comparison->partnerOutcomes);
        foreach ($comparison->partnerOutcomes as $outcome) {
            self::assertSame(PartnerStatus::Skipped, $outcome->status);
        }
        self::assertSame(self::COMPARISON_ID, $comparison->comparisonId);
        self::assertSame(CoverageLevel::ThirdPartyPlus, $comparison->coverage);
    }

    private function breaker(FakeClock $clock): InMemoryCircuitBreaker
    {
        return new InMemoryCircuitBreaker($clock, self::THRESHOLD, self::COOLDOWN_S);
    }

    /**
     * @param list<RegisteredPartner>|null $partners
     */
    private function handler(
        FakePartnerGateway $gateway,
        InMemoryCircuitBreaker $breaker,
        FakeClock $clock,
        ?array $partners = null,
    ): CompareOffersHandler {
        return new CompareOffersHandler(
            new InMemoryPartnerRegistry($partners ?? [RegisteredPartners::aurum(), RegisteredPartners::bastion()]),
            $breaker,
            $gateway,
            new InMemoryCampaignRepository(),
            new RecordingMetricsRecorder(),
            $clock,
            3000,
        );
    }

    private function query(): CompareOffersQuery
    {
        return new CompareOffersQuery(QuoteRequestBuilder::vectorA()->build(), self::COMPARISON_ID);
    }

    private function ok(string $partnerId, Money $price): PartnerOutcome
    {
        $id = new PartnerId($partnerId);

        return new PartnerOutcome(
            $id,
            PartnerStatus::Ok,
            10,
            Offer::fromPartnerQuote($id, CoverageLevel::ThirdPartyPlus, $price),
        );
    }

    private function failure(string $partnerId, PartnerStatus $status): PartnerOutcome
    {
        return new PartnerOutcome(new PartnerId($partnerId), $status, 12, null);
    }

    /**
     * @param list<PartnerOutcome> $outcomes
     */
    private function partnerStatus(array $outcomes, string $partnerId): PartnerStatus
    {
        return $this->outcome($outcomes, $partnerId)->status;
    }

    /**
     * @param list<PartnerOutcome> $outcomes
     */
    private function outcome(array $outcomes, string $partnerId): PartnerOutcome
    {
        foreach ($outcomes as $outcome) {
            if ($outcome->partnerId->value === $partnerId) {
                return $outcome;
            }
        }

        self::fail(sprintf('No outcome for partner "%s".', $partnerId));
    }
}
