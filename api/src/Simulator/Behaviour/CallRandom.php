<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

use Random\Randomizer;

/**
 * The random source for one call.
 *
 * Probabilities are compared as integers per million rather than as floats, so
 * that whether a 1% partner fails never turns on binary rounding.
 */
final readonly class CallRandom
{
    public const int SCALE = 1_000_000;

    public function __construct(
        private Randomizer $randomizer,
    ) {
    }

    /**
     * A uniform draw in `[0, SCALE)`, to be compared against `scaled()`.
     */
    public function draw(): int
    {
        return $this->randomizer->getInt(0, self::SCALE - 1);
    }

    public function intBetween(int $min, int $max): int
    {
        return $this->randomizer->getInt($min, $max);
    }

    public static function scaled(float $probability): int
    {
        return (int) round($probability * self::SCALE);
    }
}
