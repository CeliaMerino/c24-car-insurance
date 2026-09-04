<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Partner;

use App\Domain\Offer\PartnerId;
use App\Domain\Quote\CoverageLevel;
use App\Infrastructure\Partner\PartnerResponseParser;
use App\Tests\Support\QuoteRequestBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The contract test of specs/06-testing.md section 6: the simulator's real
 * success body fed into the adapter's parser. In-process, not a network call.
 */
final class SimulatorAdapterContractTest extends WebTestCase
{
    #[Test]
    public function the_simulator_success_body_parses_as_an_offer(): void
    {
        $_ENV['PARTNER_AURUM_FORCE'] = 'ok';
        $_SERVER['PARTNER_AURUM_FORCE'] = 'ok';

        $client = self::createClient();
        $client->request(
            'POST',
            '/sim/partners/aurum/quote',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(QuoteRequestBuilder::vectorA()->toPayload(), JSON_THROW_ON_ERROR),
        );

        $response = $client->getResponse();
        self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $offer = (new PartnerResponseParser())->parse(
            new PartnerId('aurum'),
            CoverageLevel::ThirdPartyPlus,
            200,
            (string) $response->getContent(),
        );

        self::assertSame(56900, $offer->basePrice->cents());
        self::assertSame('aurum', $offer->partnerId->value);
    }

    protected function tearDown(): void
    {
        unset($_ENV['PARTNER_AURUM_FORCE'], $_SERVER['PARTNER_AURUM_FORCE']);

        parent::tearDown();
    }
}
