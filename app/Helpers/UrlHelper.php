<?php
declare(strict_types=1);

namespace App\Helpers;

use App\Core\Env;

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
    public static function setBasePath(?string $basePath): void
    {
        if ($basePath !== null && trim($basePath) !== '') {
            self::$basePath = rtrim(trim($basePath), '/');
        } else {
            self::$basePath = null; // Forces auto-detection
        }
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
        if ($scriptDir === '/' || $scriptDir === '.' || $scriptDir === '') {
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
        $path = trim($path, '/');
        if ($path === '') {
            return $base === '' ? '/' : $base . '/';
        }
        return ($base === '' ? '' : $base) . '/' . $path;
    }

    /**
     * Return root-relative URL for a static asset
     */
    public static function asset(string $path): string
    {
        $base = self::getBasePath();
        $path = ltrim($path, '/');
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

        // A phone cannot resolve a URL containing localhost. Configure the
        // LAN address explicitly when the app is opened from a local machine.
        $configuredUrl = trim((string) Env::get('APP_PUBLIC_URL', ''));
        if ($configuredUrl !== '') {
            return rtrim($configuredUrl, '/') . self::url($path);
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $hostname = preg_replace('/:\d+$/', '', $host) ?: $host;
        if ($hostname === 'localhost' || $hostname === '127.0.0.1' || $hostname === '::1') {
            $lanHost = gethostbyname(gethostname());
            if ($lanHost !== '127.0.0.1') {
                $host = $lanHost . ((($_SERVER['SERVER_PORT'] ?? 80) != 80) ? ':' . $_SERVER['SERVER_PORT'] : '');
            }
        }

        return $protocol . $host . self::url($path);
    }
}
