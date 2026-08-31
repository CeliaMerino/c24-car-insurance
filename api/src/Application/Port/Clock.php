<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Domain\Shared\ReferenceDate;

interface Clock
{
    public function today(): ReferenceDate;
}
