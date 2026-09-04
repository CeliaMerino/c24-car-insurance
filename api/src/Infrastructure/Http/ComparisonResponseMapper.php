<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Port\PartnerRegistry;
use App\Domain\Comparison\Comparison;
use App\Domain\Comparison\PartnerOutcome;
use App\Domain\Offer\Money;
use App\Domain\Offer\Offer;
use LogicException;

/**
 * Maps a Comparison to the JSON of specs/05-api-contract.md section 2.2.
 */
final readonly class ComparisonResponseMapper
{
    public function __construct(
        private PartnerRegistry $partners,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function map(Comparison $comparison): array
    {
        $displayNames = $this->displayNames();

        return [
            'comparison_id' => $comparison->comparisonId,
            'coverage' => $comparison->coverage->value,
            'currency' => Money::CURRENCY,
            'duration_ms' => $comparison->durationMs,
            'offers' => array_map(
                fn (Offer $offer): array => $this->offer($offer, $displayNames),
                $comparison->offers,
            ),
            'partners' => array_map(
                static fn (PartnerOutcome $outcome): array => [
                    'partner' => $outcome->partnerId->value,
                    'status' => $outcome->status->value,
                    'duration_ms' => $outcome->durationMs,
                ],
                $comparison->partnerOutcomes,
            ),
        ];
    }

    /**
     * @param array<string, string> $displayNames
     *
     * @return array<string, mixed>
     */
    private function offer(Offer $offer, array $displayNames): array
    {
        $id = $offer->partnerId->value;
        $displayName = $displayNames[$id] ?? throw new LogicException(sprintf(
            'Offer from partner "%s" has no display name in the registry.',
            $id,
        ));

        $campaign = null;
        if (null !== $offer->discount) {
            $campaign = [
                'label' => $offer->discount->label,
                'percentage' => $offer->discount->percentage,
            ];
        }

        return [
            'partner' => $id,
            'partner_display_name' => $displayName,
            'base_annual_premium_cents' => $offer->basePrice->cents(),
            'final_annual_premium_cents' => $offer->finalPrice->cents(),
            'campaign' => $campaign,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function displayNames(): array
    {
        $names = [];
        foreach ($this->partners->enabledPartners() as $partner) {
            $names[$partner->id->value] = $partner->displayName;
        }

        return $names;
    }
}
