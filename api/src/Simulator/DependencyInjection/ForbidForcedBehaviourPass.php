<?php

declare(strict_types=1);

namespace App\Simulator\DependencyInjection;

use App\Simulator\Behaviour\ForcedBehaviourReader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * `PARTNER_{ID}_FORCE` must fail startup outside test and dev rather than be
 * silently ignored (specs/04-providers.md section 5.2). The container is
 * compiled when the application starts with a cold cache, which for a
 * production image is the build or the deploy.
 */
final class ForbidForcedBehaviourPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $environment = $container->getParameter('kernel.environment');

        if (!is_string($environment) || in_array($environment, ForcedBehaviourReader::PERMITTED_ENVIRONMENTS, true)) {
            return;
        }

        ForcedBehaviourReader::rejectAnyOverride($environment);
    }
}
