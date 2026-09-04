<?php

declare(strict_types=1);

namespace App\Simulator\Quote;

enum Usage: string
{
    case Private = 'private';
    case Commercial = 'commercial';
}
