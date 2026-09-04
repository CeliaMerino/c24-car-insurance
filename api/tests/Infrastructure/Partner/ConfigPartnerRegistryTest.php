<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Partner;

use App\Infrastructure\Partner\ConfigPartnerRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigPartnerRegistryTest extends TestCase
{
    #[Test]
    public function enabled_partners_are_resolved_against_the_simulator_base_url(): void
    {
        $registry = new ConfigPartnerRegistry(
            [
                'aurum' => [
                    'display_name' => 'Aurum Direct',
                    'enabled' => true,
                    'path' => '/sim/partners/aurum/quote',
                    'timeout_ms' => 2000,
                ],
            ],
            'http://simulator',
        );

        $partners = $registry->enabledPartners();

        self::assertCount(1, $partners);
        self::assertSame('aurum', $partners[0]->id->value);
        self::assertSame('Aurum Direct', $partners[0]->displayName);
        self::assertSame('http://simulator/sim/partners/aurum/quote', $partners[0]->quoteUrl);
        self::assertSame(2000, $partners[0]->timeoutMs);
    }
}
