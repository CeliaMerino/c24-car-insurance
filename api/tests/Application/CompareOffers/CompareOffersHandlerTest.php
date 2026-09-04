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
use App\Infrastructure\Partner\ConfigPartnerRegistry;
use App\Tests\Support\FakeClock;
use App\Tests\Support\FakePartnerGateway;
use App\Tests\Support\InMemoryPartnerRegistry;
use App\Tests\Support\QuoteRequestBuilder;
use App\Tests\Support\RecordingMetricsRecorder;
use App\Tests\Support\ReferenceDates;
use App\Tests\Support\RegisteredPartners;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * L4. Orchestration: ordering, partial results, a fifth partner, deadline
 * plumbing (specs/06-testing.md sections 3 and 5). Campaigns and the circuit
 * breaker are not in this phase; every registered partner is callable.
 */
final class CompareOffersHandlerTest extends TestCase
{
    private const string COMPARISON_ID = '01TESTCOMPARISON00000000000';

    #[Test]
    public function two_partners_with_the_same_final_price_are_ordered_by_partner_name(): void
    {
        $price = new Money(47800);
        $gateway = new FakePartnerGateway([
            'celeris' => $this->ok('celeris', $price),
            'aurum' => $this->ok('aurum', $price),
        ]);

        $comparison = $this->handler(
            $gateway,
            [RegisteredPartners::celeris(), RegisteredPartners::aurum()],
        )->handle($this->query());

        self::assertSame(['aurum', 'celeris'], array_map(
            static fn (Offer $offer): string => $offer->partnerId->value,
            $comparison->offers,
        ));
    }

    #[Test]
    public function offers_are_sorted_by_final_price_ascending(): void
    {
        $gateway = new FakePartnerGateway([
            'aurum' => $this->ok('aurum', new Money(56900)),
            'bastion' => $this->ok('bastion', new Money(48600)),
            'celeris' => $this->ok('celeris', new Money(47800)),
            'dorsal' => $this->ok('dorsal', new Money(61300)),
        ]);

        $comparison = $this->handler($gateway, RegisteredPartners::four())->handle($this->query());

        self::assertSame(['celeris', 'bastion', 'aurum', 'dorsal'], array_map(
            static fn (Offer $offer): string => $offer->partnerId->value,
            $comparison->offers,
        ));
    }

    #[Test]
    public function a_failed_partner_produces_no_offer_and_the_rest_are_kept(): void
    {
        $gateway = new FakePartnerGateway([
            'aurum' => $this->ok('aurum', new Money(56900)),
            'bastion' => new PartnerOutcome(new PartnerId('bastion'), PartnerStatus::Timeout, 2000, null),
            'celeris' => $this->ok('celeris', new Money(47800)),
            'dorsal' => new PartnerOutcome(new PartnerId('dorsal'), PartnerStatus::Error, 12, null),
        ]);

        $comparison = $this->handler($gateway, RegisteredPartners::four())->handle($this->query());

        self::assertSame(['celeris', 'aurum'], array_map(
            static fn (Offer $offer): string => $offer->partnerId->value,
            $comparison->offers,
        ));
        self::assertSame(['aurum', 'bastion', 'celeris', 'dorsal'], array_map(
            static fn (PartnerOutcome $outcome): string => $outcome->partnerId->value,
            $comparison->partnerOutcomes,
        ));
        self::assertSame(PartnerStatus::Timeout, $comparison->partnerOutcomes[1]->status);
        self::assertSame(PartnerStatus::Error, $comparison->partnerOutcomes[3]->status);
    }

    #[Test]
    public function every_partner_failing_is_still_a_successful_comparison_with_zero_offers(): void
    {
        $gateway = new FakePartnerGateway([
            'aurum' => new PartnerOutcome(new PartnerId('aurum'), PartnerStatus::Error, 8, null),
            'bastion' => new PartnerOutcome(new PartnerId('bastion'), PartnerStatus::Timeout, 2000, null),
            'celeris' => new PartnerOutcome(new PartnerId('celeris'), PartnerStatus::Timeout, 2000, null),
            'dorsal' => new PartnerOutcome(new PartnerId('dorsal'), PartnerStatus::Error, 14, null),
        ]);

        $comparison = $this->handler($gateway, RegisteredPartners::four())->handle($this->query());

        self::assertSame([], $comparison->offers);
        self::assertCount(4, $comparison->partnerOutcomes);
        self::assertSame(self::COMPARISON_ID, $comparison->comparisonId);
        self::assertSame(CoverageLevel::ThirdPartyPlus, $comparison->coverage);
    }

    #[Test]
    public function a_fifth_partner_registered_through_config_appears_without_changing_the_handler(): void
    {
        $registry = new ConfigPartnerRegistry(
            [
                'aurum' => ['display_name' => 'Aurum Direct', 'enabled' => true, 'path' => '/sim/partners/aurum/quote', 'timeout_ms' => 200],
                'bastion' => ['display_name' => 'Bastion Insurance', 'enabled' => true, 'path' => '/sim/partners/bastion/quote', 'timeout_ms' => 200],
                'celeris' => ['display_name' => 'Celeris Seguros', 'enabled' => true, 'path' => '/sim/partners/celeris/quote', 'timeout_ms' => 200],
                'dorsal' => ['display_name' => 'Dorsal Mutual', 'enabled' => true, 'path' => '/sim/partners/dorsal/quote', 'timeout_ms' => 200],
                'helix' => ['display_name' => 'Helix Cover', 'enabled' => true, 'path' => '/sim/partners/helix/quote', 'timeout_ms' => 200],
            ],
            'http://simulator',
        );
        $gateway = FakePartnerGateway::echoing();
        $metrics = new RecordingMetricsRecorder();

        $comparison = $this->handler($gateway, $registry->enabledPartners(), $metrics, $registry)->handle($this->query());

        self::assertSame(
            ['aurum', 'bastion', 'celeris', 'dorsal', 'helix'],
            $gateway->calledPartnerIds,
        );
        self::assertCount(5, $comparison->offers);
        self::assertCount(5, $comparison->partnerOutcomes);
        self::assertSame('helix', $comparison->partnerOutcomes[4]->partnerId->value);
    }

    #[Test]
    public function a_disabled_partner_is_not_called_and_does_not_appear(): void
    {
        $registry = new ConfigPartnerRegistry(
            [
                'aurum' => ['display_name' => 'Aurum Direct', 'enabled' => true, 'path' => '/sim/partners/aurum/quote', 'timeout_ms' => 200],
                'bastion' => ['display_name' => 'Bastion Insurance', 'enabled' => false, 'path' => '/sim/partners/bastion/quote', 'timeout_ms' => 200],
            ],
            'http://simulator',
        );
        $gateway = FakePartnerGateway::echoing();

        $comparison = $this->handler($gateway, $registry->enabledPartners(), registry: $registry)->handle($this->query());

        self::assertSame(['aurum'], $gateway->calledPartnerIds);
        self::assertCount(1, $comparison->offers);
        self::assertCount(1, $comparison->partnerOutcomes);
    }

    #[Test]
    public function the_configured_deadline_is_passed_to_the_gateway(): void
    {
        $gateway = FakePartnerGateway::echoing();

        $this->handler($gateway, [RegisteredPartners::aurum()], deadlineMs: 300)->handle($this->query());

        self::assertSame(300, $gateway->deadlineMs);
    }

    #[Test]
    public function metrics_are_recorded_for_the_comparison_and_each_partner(): void
    {
        $gateway = new FakePartnerGateway([
            'aurum' => $this->ok('aurum', new Money(56900)),
            'bastion' => new PartnerOutcome(new PartnerId('bastion'), PartnerStatus::Error, 12, null),
        ]);
        $metrics = new RecordingMetricsRecorder();

        $this->handler(
            $gateway,
            [RegisteredPartners::aurum(), RegisteredPartners::bastion()],
            $metrics,
        )->handle($this->query());

        self::assertSame(5, $metrics->comparisonDurationMs);
        self::assertCount(2, $metrics->partnerOutcomes);
        self::assertSame('aurum', $metrics->partnerOutcomes[0]['partner']);
        self::assertSame(PartnerStatus::Ok, $metrics->partnerOutcomes[0]['status']);
        self::assertSame('bastion', $metrics->partnerOutcomes[1]['partner']);
        self::assertSame(PartnerStatus::Error, $metrics->partnerOutcomes[1]['status']);
    }

    /**
     * @param list<RegisteredPartner> $partners
     */
    private function handler(
        FakePartnerGateway $gateway,
        array $partners,
        ?RecordingMetricsRecorder $metrics = null,
        ?ConfigPartnerRegistry $registry = null,
        int $deadlineMs = 3000,
    ): CompareOffersHandler {
        return new CompareOffersHandler(
            $registry ?? new InMemoryPartnerRegistry($partners),
            $gateway,
            $metrics ?? new RecordingMetricsRecorder(),
            new FakeClock(ReferenceDates::frozen(), stepMs: 5),
            $deadlineMs,
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
}
