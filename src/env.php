<?php

/**
 * putenv()/getenv() alone aren't reliable here — some managed hosts
 * (Cloudways included) disable putenv() in php.ini for security, since it
 * affects the whole PHP-FPM worker process rather than just the current
 * request. So our own .env parser writes into $_ENV (a plain array write,
 * never disabled) instead of calling putenv(), and this helper checks
 * $_ENV first — falling back to real getenv() so an actual OS-level
 * environment variable (e.g. set via Cloudways' own env var UI, or in
 * local dev via `FOO=bar php -S ...`) still works too.
 */
function env(string $key, ?string $default = null): ?string
{
    if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    $value = getenv($key);
    return $value !== false && $value !== '' ? $value : $default;
}
