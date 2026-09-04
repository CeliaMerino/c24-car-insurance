<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Port\RegisteredPartner;
use App\Domain\Offer\PartnerId;

final class RegisteredPartners
{
    public static function aurum(string $url = 'http://simulator/sim/partners/aurum/quote', int $timeoutMs = 2000): RegisteredPartner
    {
        return new RegisteredPartner(new PartnerId('aurum'), 'Aurum Direct', $url, $timeoutMs);
    }

    public static function bastion(string $url = 'http://simulator/sim/partners/bastion/quote', int $timeoutMs = 2000): RegisteredPartner
    {
        return new RegisteredPartner(new PartnerId('bastion'), 'Bastion Insurance', $url, $timeoutMs);
    }

    public static function celeris(string $url = 'http://simulator/sim/partners/celeris/quote', int $timeoutMs = 2000): RegisteredPartner
    {
        return new RegisteredPartner(new PartnerId('celeris'), 'Celeris Seguros', $url, $timeoutMs);
    }

    public static function dorsal(string $url = 'http://simulator/sim/partners/dorsal/quote', int $timeoutMs = 2000): RegisteredPartner
    {
        return new RegisteredPartner(new PartnerId('dorsal'), 'Dorsal Mutual', $url, $timeoutMs);
    }

    /**
     * @return list<RegisteredPartner>
     */
    public static function four(int $timeoutMs = 2000): array
    {
        return [
            self::aurum(timeoutMs: $timeoutMs),
            self::bastion(timeoutMs: $timeoutMs),
            self::celeris(timeoutMs: $timeoutMs),
            self::dorsal(timeoutMs: $timeoutMs),
        ];
    }
}
