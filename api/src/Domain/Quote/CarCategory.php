<?php

declare(strict_types=1);

namespace App\Domain\Quote;

enum CarCategory: string
{
    case Compact = 'compact';
    case Sedan = 'sedan';
    case Suv = 'suv';
    case Van = 'van';
}
