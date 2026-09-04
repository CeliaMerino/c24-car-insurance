<?php

declare(strict_types=1);

namespace App\Simulator\Behaviour;

use App\Simulator\Config\EnvVars;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reads the `PARTNER_{ID}_FORCE` test seam of specs/04-providers.md section 5.2.
 *
 * The environment is read directly rather than through a bound container
 * parameter because partner ids are not known when the container is compiled: a
 * fifth partner must not require a configuration change here.
 *
 * The seam exists only in `test` and `dev`. Anywhere else an override present
 * in the environment is an error rather than something to ignore, which is also
 * checked when the container is compiled by
 * `App\Simulator\DependencyInjection\ForbidForcedBehaviourPass`. The check is
 * repeated here because a container compiled before the variable was set would
 * otherwise honour it.
 */
final readonly class ForcedBehaviourReader
{
    private const string ENV_PATTERN = '/^PARTNER_[A-Z0-9_]+_FORCE$/';

    /** @var list<string> */
    public const array PERMITTED_ENVIRONMENTS = ['test', 'dev'];

    public function __construct(
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the override names an outcome that does not exist
     * @throws LogicException           when an override is present outside test and dev
     */
    public function forPartner(string $partnerId): ?ForcedBehaviour
    {
        if (!in_array($this->environment, self::PERMITTED_ENVIRONMENTS, true)) {
            self::rejectAnyOverride($this->environment);

            return null;
        }

        $name = self::envName($partnerId);
        $raw = EnvVars::get($name);

        if (null === $raw || '' === $raw) {
            return null;
        }

        $forced = ForcedBehaviour::tryFrom($raw);

        if (null === $forced) {
            throw new InvalidArgumentException(sprintf(
                '%s is set to "%s", which is not one of: %s.',
                $name,
                $raw,
                implode('|', array_column(ForcedBehaviour::cases(), 'value')),
            ));
        }

        return $forced;
    }

    public static function envName(string $partnerId): string
    {
        return 'PARTNER_'.strtoupper($partnerId).'_FORCE';
    }

    /**
     * @throws LogicException when the environment carries any force override
     */
    public static function rejectAnyOverride(string $environment): void
    {
        $present = self::presentOverrides();

        if ([] === $present) {
            return;
        }

        throw new LogicException(sprintf(
            'Forced partner behaviour is a test seam and is not available in the "%s" environment, but %s %s set. Remove it rather than relying on it being ignored.',
            $environment,
            implode(', ', $present),
            1 === count($present) ? 'is' : 'are',
        ));
    }

    /**
     * Every force override present in the environment, whatever partner it names.
     *
     * @return list<string>
     */
    public static function presentOverrides(): array
    {
        return EnvVars::namesMatching(self::ENV_PATTERN);
    }
}
