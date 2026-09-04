<?php

declare(strict_types=1);

namespace App\Simulator\Config;

/**
 * Reads environment variables the way Symfony's own env var processor does:
 * `$_ENV`, then `$_SERVER`, then the process environment.
 *
 * The last fallback is not optional. Under every web SAPI, `variables_order`
 * excludes `E` and the request rebuilds `$_SERVER` from the request rather than
 * from the process, so a variable set on the container is visible to `getenv()`
 * and to nothing else. Reading only the superglobals works under the CLI and
 * then silently ignores the variable in the container it was meant for.
 *
 * This exists because the two variables involved name partners, which are not
 * known when the container is compiled, or gate the routing file, which is
 * loaded before any service exists. Everything else goes through `%env()%`.
 */
final class EnvVars
{
    public static function get(string $name): ?string
    {
        /** @var mixed $value */
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;

        if (is_string($value)) {
            return $value;
        }

        $value = getenv($name);

        return false === $value ? null : $value;
    }

    /**
     * Every variable whose name matches, from all three sources.
     *
     * @return list<string> matching names
     */
    public static function namesMatching(string $pattern): array
    {
        $names = [];
        $environment = getenv();

        foreach ([$environment, $_SERVER, $_ENV] as $source) {
            /** @var mixed $value */
            foreach ($source as $name => $value) {
                if (is_string($name) && is_string($value) && '' !== $value && 1 === preg_match($pattern, $name)) {
                    $names[$name] = true;
                }
            }
        }

        return array_keys($names);
    }
}
