<?php

declare(strict_types=1);

namespace App\Domain\Offer;

enum PartnerId: string
{
    case Aurum = 'aurum';
    case Bastion = 'bastion';
    case Celeris = 'celeris';
    case Dorsal = 'dorsal';
}
