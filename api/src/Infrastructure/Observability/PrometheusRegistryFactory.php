<?php

declare(strict_types=1);

namespace App\Infrastructure\Observability;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\APC;
use Prometheus\Storage\InMemory;
use RuntimeException;

/**
 * Shared storage for Prometheus samples (specs/07-observability.md section 2).
 * APCu across FrankenPHP workers in runtime; in-memory only under the test
 * environment where a single process owns every request.
 */
final class PrometheusRegistryFactory
{
    public static function create(string $environment): CollectorRegistry
    {
        if ('test' === $environment) {
            return new CollectorRegistry(new InMemory(), false);
        }

        if (!extension_loaded('apcu') || !apcu_enabled()) {
            throw new RuntimeException(
                'APCu is required for Prometheus metrics. An in-memory registry undercounts across FrankenPHP workers.',
            );
        }

        return new CollectorRegistry(new APC(), false);
    }
}
