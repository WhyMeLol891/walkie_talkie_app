<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class Env
 * Simple .env file loader for pure PHP without external dependencies.
 */
class Env
{
    /** @var array<string, string> */
    private static array $variables = [];
    private static bool $loaded = false;

    /**
     * Load environment variables from a .env file
     */
    public static function load(string $path): void
    {
        if (self::$loaded || !file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Strip surrounding quotes
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }

                self::$variables[$key] = $value;
                if (!isset($_SERVER[$key])) {
                    $_SERVER[$key] = $value;
                }
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with optional default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$variables)) {
            return self::parseValue(self::$variables[$key]);
        }

        if (array_key_exists($key, $_ENV)) {
            return self::parseValue($_ENV[$key]);
        }

        if (array_key_exists($key, $_SERVER)) {
            return self::parseValue($_SERVER[$key]);
        }

        return $default;
    }

    /**
     * Parse string value to appropriate type (bool, int, null)
     */
    private static function parseValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value
        };
    }
}
