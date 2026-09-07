<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\Support\ForcedPartnerOutcomes;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L5. Operational endpoints of specs/05-api-contract.md section 3.
 */
final class OperationalApiTest extends WebTestCase
{
    protected function tearDown(): void
    {
        ForcedPartnerOutcomes::clear();

        parent::tearDown();
    }

    #[Test]
    public function health_is_200_and_does_not_call_partners(): void
    {
        ForcedPartnerOutcomes::all('timeout');

        $client = static::createClient();
        $startedNs = hrtime(true);
        $client->request('GET', '/health');
        $elapsedMs = (int) ((hrtime(true) - $startedNs) / 1_000_000);

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame(['status' => 'ok'], json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertLessThan(200, $elapsedMs);
    }
}
