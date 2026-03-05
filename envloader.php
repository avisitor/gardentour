<?php
/**
 * Environment Variable Loader
 * 
 * Loads configuration from .env file and makes variables available
 * via getenv(), $_ENV, and $_SERVER.
 * 
 * Include this file at the top of every PHP script that needs configuration.
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

        self::$envPath = $path ?? dirname(__FILE__) . '/.env';

        if (!file_exists(self::$envPath)) {
            throw new RuntimeException(
                "Environment file not found: " . self::$envPath . "\n" .
                "Please copy .env.example to .env and configure your settings."
            );
        }

        $lines = file(self::$envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse key=value pairs
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove surrounding quotes if present
                if (preg_match('/^(["\'])(.*)\\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                // Don't overwrite existing environment variables
                if (!getenv($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
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

        $value = getenv($key);
        return $value !== false ? $value : $default;
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

// Auto-load environment on include
EnvLoader::load();

// Helper function for quick access
if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        return EnvLoader::get($key, $default);
    }
}

} // end if (!class_exists('EnvLoader'))
