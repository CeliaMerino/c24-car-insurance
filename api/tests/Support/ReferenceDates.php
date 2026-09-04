<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Shared\ReferenceDate;
use App\Simulator\Clock\SimulatorDate;

final class ReferenceDates
{
    /** Fixed test clock from specs/06-testing.md section 2. */
    public static function frozen(): ReferenceDate
    {
        return new ReferenceDate(2026, 8, 31);
    }

    /**
     * The same instant for the simulator, which has its own clock because it
     * imports nothing from Domain.
     */
    public static function frozenSimulatorDate(): SimulatorDate
    {
        return new SimulatorDate(2026, 8, 31);
    }
}
