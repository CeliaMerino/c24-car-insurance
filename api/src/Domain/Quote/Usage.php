<?php

declare(strict_types=1);

namespace App\Domain\Quote;

enum Usage: string
{
    case Private = 'private';
    case Commercial = 'commercial';
}
