<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Shared\ReferenceDate;

final class ReferenceDates
{
    /** Fixed test clock from specs/06-testing.md section 2. */
    public static function frozen(): ReferenceDate
    {
        return new ReferenceDate(2026, 8, 31);
    }
}
