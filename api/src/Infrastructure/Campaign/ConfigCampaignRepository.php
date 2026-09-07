<?php

declare(strict_types=1);

namespace App\Infrastructure\Campaign;

use App\Application\Port\CampaignRepository;
use App\Domain\Campaign\Campaign;
use App\Domain\Offer\PartnerId;
use App\Domain\Shared\ReferenceDate;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Campaigns from configuration, one optional window per partner
 * (specs/01-product-spec.md section 5.4). Validity is Domain's; this class
 * only turns YAML into Campaign values.
 */
#[AsAlias(CampaignRepository::class)]
final class ConfigCampaignRepository implements CampaignRepository
{
    /** @var array<string, Campaign> */
    private array $byPartner;

    /**
     * @param array<string, array<string, mixed>> $campaigns
     */
    public function __construct(
        #[Autowire('%app.campaigns%')]
        array $campaigns,
    ) {
        $byPartner = [];

        foreach ($campaigns as $partnerId => $config) {
            $byPartner[$partnerId] = $this->campaign($partnerId, $config);
        }

        $this->byPartner = $byPartner;
    }

    public function findForPartner(PartnerId $partnerId, ReferenceDate $on): ?Campaign
    {
        $campaign = $this->byPartner[$partnerId->value] ?? null;

        if (null === $campaign || !$campaign->isActiveAt($on)) {
            return null;
        }

        return $campaign;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function campaign(string $partnerId, array $config): Campaign
    {
        $percentage = $this->percentage($config, $partnerId);

        return new Campaign(
            new PartnerId($partnerId),
            $percentage,
            sprintf('CHECK24 pays %d%%', $percentage),
            $this->date($config, 'start_date', $partnerId),
            $this->date($config, 'end_date', $partnerId),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function percentage(array $config, string $partnerId): int
    {
        $value = $config['percentage'] ?? null;

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value) && (string) (int) $value === (string) $value) {
            return (int) $value;
        }

        throw new InvalidArgumentException(sprintf(
            'Campaign for partner "%s" is missing an integer percentage.',
            $partnerId,
        ));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function date(array $config, string $key, string $partnerId): ReferenceDate
    {
        $value = $config[$key] ?? null;

        if (!is_string($value) || '' === $value) {
            throw new InvalidArgumentException(sprintf(
                'Campaign for partner "%s" is missing a "%s" as YYYY-MM-DD.',
                $partnerId,
                $key,
            ));
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        if (false === $parsed || $parsed->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException(sprintf(
                'Campaign for partner "%s" has an invalid "%s": "%s".',
                $partnerId,
                $key,
                $value,
            ));
        }

        return new ReferenceDate(
            (int) $parsed->format('Y'),
            (int) $parsed->format('n'),
            (int) $parsed->format('j'),
        );
    }
}
