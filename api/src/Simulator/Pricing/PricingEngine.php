<?php

declare(strict_types=1);

namespace App\Simulator\Pricing;

use App\Simulator\Quote\QuoteInput;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One partner's underwriting. Implementations carry their own factor tables in
 * typed code and nothing else in the system knows the numbers
 * (specs/04-providers.md section 7).
 *
 * A price is a pure function of the input. Nothing here observes the clock, the
 * simulation seed or the behaviour drawn for the call.
 */
#[AutoconfigureTag('simulator.pricing_engine')]
interface PricingEngine
{
    public function partnerId(): string;

    public function priceCents(QuoteInput $input): int;
}
