<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\PartnerGateway;
use App\Domain\Comparison\PartnerOutcome;
use App\Domain\Comparison\PartnerStatus;
use App\Domain\Offer\Money;
use App\Domain\Offer\Offer;
use App\Domain\Quote\QuoteRequest;

final class FakePartnerGateway implements PartnerGateway
{
    /** @var list<string> */
    public array $calledPartnerIds = [];

    public ?int $deadlineMs = null;

    /**
     * @param array<string, PartnerOutcome> $outcomes
     */
    public function __construct(
        private array $outcomes = [],
        private readonly bool $echoOffers = false,
    ) {
    }

    public static function echoing(): self
    {
        return new self(echoOffers: true);
    }

    public function fetchQuotes(QuoteRequest $request, array $partners, int $deadlineMs): array
    {
        $this->deadlineMs = $deadlineMs;
        $results = [];

        foreach ($partners as $partner) {
            $this->calledPartnerIds[] = $partner->id->value;

            if ($this->echoOffers) {
                $results[] = new PartnerOutcome(
                    $partner->id,
                    PartnerStatus::Ok,
                    10,
                    Offer::fromPartnerQuote($partner->id, $request->coverage, new Money(10_000)),
                );
                continue;
            }

            $results[] = $this->outcomes[$partner->id->value] ?? new PartnerOutcome(
                $partner->id,
                PartnerStatus::Error,
                1,
                null,
            );
        }

        return $results;
    }
}
