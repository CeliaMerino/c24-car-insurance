<?php

declare(strict_types=1);

namespace App\Domain\Comparison;

enum PartnerStatus: string
{
    case Ok = 'ok';
    case Timeout = 'timeout';
    case Error = 'error';
    case Skipped = 'skipped';
}
