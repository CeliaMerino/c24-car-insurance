<?php

declare(strict_types=1);

namespace App\Domain\Quote;

enum CoverageLevel: string
{
    case ThirdParty = 'third_party';
    case ThirdPartyPlus = 'third_party_plus';
    case Comprehensive = 'comprehensive';
}
