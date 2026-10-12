<?php
declare(strict_types=1);

namespace App\Helpers;

/**
 * Class UrlHelper
 * Resolves paths and URLs based on application base directory.
 */
class UrlHelper
{
    private static ?string $basePath = null;

    /**
     * Set or auto-detect application base path
     */
    public static function setBasePath(string $basePath): void
    {
        self::$basePath = rtrim($basePath, '/');
    }

    /**
     * Get the detected base path (e.g. '/walkie_talkie_app' or '')
     */
    public static function getBasePath(): string
    {
        if (self::$basePath !== null) {
            return self::$basePath;
        }

        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $scriptDir = str_replace('\\', '/', $scriptDir);
        if ($scriptDir === '/' || $scriptDir === '.') {
            self::$basePath = '';
        } else {
            $base = rtrim($scriptDir, '/');
            // Remove /public if accessed through root rewrite or direct public dir
            if (str_ends_with($base, '/public')) {
                $base = substr($base, 0, -7);
            }
            self::$basePath = $base;
        }

        return self::$basePath;
    }

    /**
     * Return root-relative URL for a path
     */
    public static function url(string $path = ''): string
    {
        $base = self::getBasePath();
        $path = '/' . ltrim($path, '/');
        if ($path === '/') {
            return $base === '' ? '/' : $base . '/';
        }
        return $base . $path;
    }

    /**
     * Return root-relative URL for a static asset
     */
    public static function asset(string $path): string
    {
        $base = self::getBasePath();
        $path = ltrim($path, '/');
        // If public folder is exposed in assets path
        return ($base === '' ? '' : $base) . '/assets/' . $path;
    }

    /**
     * Return absolute URL (e.g. for QR Code generation)
     */
    public static function fullUrl(string $path = ''): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 80) == 443
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host . self::url($path);
    }
}
