<?php

declare(strict_types=1);

namespace App\Simulator\Quote;

enum AnnualMileage: string
{
    case Under5k = 'under_5k';
    case From5kTo15k = '5k_15k';
    case From15kTo30k = '15k_30k';
    case Over30k = 'over_30k';
}
