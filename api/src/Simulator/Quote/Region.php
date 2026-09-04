<?php

declare(strict_types=1);

namespace App\Simulator\Quote;

enum Region
{
    case HighDensity;
    case Standard;

    /** specs/04-providers.md section 3. */
    private const array HIGH_DENSITY_PROVINCES = ['28', '08', '41', '46'];

    public static function fromPostalCode(string $postalCode): self
    {
        return in_array(substr($postalCode, 0, 2), self::HIGH_DENSITY_PROVINCES, true)
            ? self::HighDensity
            : self::Standard;
    }
}
