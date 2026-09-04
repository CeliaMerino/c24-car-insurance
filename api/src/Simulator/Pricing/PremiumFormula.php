<?php

declare(strict_types=1);

namespace App\Simulator\Pricing;

/**
 * The shared shape of every partner's premium, from specs/04-providers.md
 * section 3:
 *
 *     premium = base × age × category × usage × mileage × garage × region × coverage
 *
 * rounded half up to whole euros and expressed in cents.
 *
 * The arithmetic is integer. Every factor in the specification has at most two
 * decimals, so each one is scaled to hundredths before the product is formed
 * and the division happens once, at the end. A float product would put a
 * premium that lands exactly on half a euro on whichever side binary rounding
 * happened to fall, which is not "half up".
 */
final class PremiumFormula
{
    private const int FACTOR_SCALE = 100;

    private const int CENTS_PER_EURO = 100;

    /**
     * @param non-empty-list<float> $factors two-decimal multipliers, applied in the order of the formula above
     */
    public static function cents(int $baseEuros, array $factors): int
    {
        $numerator = $baseEuros;
        $divisor = 1;

        foreach ($factors as $factor) {
            $numerator *= (int) round($factor * self::FACTOR_SCALE);
            $divisor *= self::FACTOR_SCALE;
        }

        $euros = intdiv(2 * $numerator + $divisor, 2 * $divisor);

        return $euros * self::CENTS_PER_EURO;
    }
}
