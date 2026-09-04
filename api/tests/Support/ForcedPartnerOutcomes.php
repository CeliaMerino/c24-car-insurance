<?php

declare(strict_types=1);

namespace App\Tests\Support;

/**
 * The PARTNER_{ID}_FORCE seam of specs/04-providers.md section 5.2, for L5.
 */
final class ForcedPartnerOutcomes
{
    private const array PARTNERS = ['aurum', 'bastion', 'celeris', 'dorsal'];

    /**
     * @param array<string, string> $outcomes partner id => ForcedBehaviour value
     */
    public static function set(array $outcomes): void
    {
        self::clear();

        foreach ($outcomes as $partner => $outcome) {
            $name = self::envName($partner);
            $_ENV[$name] = $outcome;
            $_SERVER[$name] = $outcome;
            putenv($name.'='.$outcome);
        }
    }

    public static function all(string $outcome): void
    {
        self::set(array_fill_keys(self::PARTNERS, $outcome));
    }

    public static function clear(): void
    {
        foreach (self::PARTNERS as $partner) {
            $name = self::envName($partner);
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
    }

    private static function envName(string $partner): string
    {
        return 'PARTNER_'.strtoupper($partner).'_FORCE';
    }
}
