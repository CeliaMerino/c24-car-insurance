<?php

declare(strict_types=1);

namespace App\Infrastructure\Partner;

use App\Application\Port\PartnerRegistry;
use App\Application\Port\RegisteredPartner;
use App\Domain\Offer\PartnerId;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The API-side partner list of specs/04-providers.md section 7: who exists and
 * how to reach them. Nothing about pricing.
 */
#[AsAlias(PartnerRegistry::class)]
final class ConfigPartnerRegistry implements PartnerRegistry
{
    /** @var list<RegisteredPartner> */
    private array $enabled;

    /**
     * @param array<string, array<string, mixed>> $partners
     */
    public function __construct(
        #[Autowire('%app.partners%')]
        array $partners,
        #[Autowire(env: 'PARTNER_SIMULATOR_BASE_URL')]
        string $baseUrl,
    ) {
        $enabled = [];

        foreach ($partners as $id => $config) {
            if (true !== ($config['enabled'] ?? false)) {
                continue;
            }

            $enabled[] = new RegisteredPartner(
                new PartnerId($id),
                $this->string($config, 'display_name', $id),
                $this->quoteUrl($baseUrl, $this->string($config, 'path', $id)),
                $this->timeoutMs($config, $id),
            );
        }

        $this->enabled = $enabled;
    }

    public function enabledPartners(): array
    {
        return $this->enabled;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function string(array $config, string $key, string $partnerId): string
    {
        $value = $config[$key] ?? null;

        if (!is_string($value) || '' === $value) {
            throw new InvalidArgumentException(sprintf(
                'Partner "%s" is missing a non-empty "%s".',
                $partnerId,
                $key,
            ));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function timeoutMs(array $config, string $partnerId): int
    {
        $value = $config['timeout_ms'] ?? null;

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value;
        }

        throw new InvalidArgumentException(sprintf(
            'Partner "%s" is missing a positive timeout_ms.',
            $partnerId,
        ));
    }

    private function quoteUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
