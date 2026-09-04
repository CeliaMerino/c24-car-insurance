<?php

declare(strict_types=1);

namespace App\Simulator\Quote;

enum CoverageLevel: string
{
    case ThirdParty = 'third_party';
    case ThirdPartyPlus = 'third_party_plus';
    case Comprehensive = 'comprehensive';
}
