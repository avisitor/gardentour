<?php

declare(strict_types=1);

/**
 * Environment Variable Loader
 *
 * Thin delegating shim over the canonical avisitor/env-loader package
 * (Avisitor\Env\Loader). Keeps the app's EnvLoader class API and its
 * fail-fast behavior (throws when .env is missing), while all parsing and
 * lookup is done by the shared package.
 */

if (!class_exists('EnvLoader')) {

class EnvLoader
{
    private static bool $loaded = false;
    private static string $envPath;

    /**
     * Load environment variables from .env file
     */
    public static function load(string $path = null): void
    {
        if (self::$loaded) {
            return;
        }
        if (!class_exists('Avisitor\\Env\\Loader')) {
            return; // canonical package not autoloaded yet; get() retries
        }

        self::$envPath = $path ?? __DIR__ . '/.env';

        if (!file_exists(self::$envPath)) {
            throw new RuntimeException(
                "Environment file not found: " . self::$envPath . "\n" .
                "Please copy .env.example to .env and configure your settings."
            );
        }

        // Snapshot pre-existing env vars: the previous local implementation
        // never overwrote values already in the environment. Restore them
        // after the canonical loader runs.
        $before = getenv();
        $env = \Avisitor\Env\Loader::load(self::$envPath);
        foreach ($env as $key => $value) {
            if (isset($before[$key])) {
                putenv("$key={$before[$key]}");
                $_ENV[$key] = $before[$key];
            }
            $_SERVER[$key] = $_ENV[$key] ?? $value;
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with optional default
     */
    public static function get(string $key, $default = null)
    {
        if (!self::$loaded) {
            self::load();
        }

        return \Avisitor\Env\Loader::get($key, $default);
    }

    /**
     * Get a required environment variable (throws if not set)
     */
    public static function getRequired(string $key)
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Required environment variable not set: $key");
        }

        return $value;
    }

    /**
     * Check if environment is loaded
     */
    public static function isLoaded(): bool
    {
        return self::$loaded;
    }
}

// Auto-load environment on include (only when the canonical package is
// already available; otherwise load() is triggered lazily by get()).
if (class_exists('Avisitor\\Env\\Loader')) {
    EnvLoader::load();
}

// Helper function for quick access
if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        return EnvLoader::get($key, $default);
    }
}

} // end if (!class_exists('EnvLoader'))
