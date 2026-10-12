<?php
declare(strict_types=1);

namespace App\Core;

use App\Helpers\UrlHelper;

/**
 * Class App
 * Main Application Bootstrapper.
 */
class App
{
    private static array $config = [];
    private static Router $router;

    public static function boot(): void
    {
        self::registerAutoloader();
        self::initEnvironment();
    }

    public static function run(): void
    {
        self::boot();
        self::initErrorHandling();

        Session::start();

        self::$router = new Router();

        // Configure View and UrlHelper paths
        View::setViewsPath(self::$config['paths']['views']);
        UrlHelper::setBasePath(self::$config['base_path']);

        // Register routes
        $routeFile = self::$config['paths']['root'] . '/config/routes.php';
        if (file_exists($routeFile)) {
            $routesCallback = require $routeFile;
            if (is_callable($routesCallback)) {
                $routesCallback(self::$router);
            }
        }

        // Handle request
        $request = new Request(self::$config['base_path']);
        self::$router->dispatch($request);
    }

    public static function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class) {
            $prefix = 'App\\';
            $baseDir = dirname(__DIR__) . '/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    private static function initEnvironment(): void
    {
        $rootDir = dirname(__DIR__, 2);
        Env::load($rootDir . '/.env');
        self::$config = require $rootDir . '/config/app.php';
    }

    private static function initErrorHandling(): void
    {
        error_reporting(E_ALL);

        set_error_handler(function (int $severity, string $message, string $file, int $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (\Throwable $exception) {
            self::logException($exception);

            if (!headers_sent()) {
                http_response_code(500);
            }

            $debug = self::$config['debug'] ?? false;
            if ($debug) {
                echo "<div style='font-family:sans-serif;padding:24px;background:#1a1a1a;color:#ff5555;border-radius:8px;'>";
                echo "<h2>Application Error</h2>";
                echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
                echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . " (line " . $exception->getLine() . ")</p>";
                echo "<pre style='background:#2a2a2a;color:#eee;padding:12px;overflow:auto;'>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
                echo "</div>";
            } else {
                if (file_exists(self::$config['paths']['views'] . '/errors/500.php')) {
                    View::render('errors/500');
                } else {
                    echo "<h1>500 Internal Server Error</h1><p>An unexpected error occurred. Please try again later.</p>";
                }
            }
            exit;
        });
    }

    public static function logException(\Throwable $exception): void
    {
        $logDir = self::$config['paths']['logs'] ?? (dirname(__DIR__, 2) . '/storage/logs');
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logFile = $logDir . '/app.log';
        $timestamp = date('Y-m-d H:i:s');
        $entry = sprintf(
            "[%s] %s: %s in %s:%d\nStack Trace:\n%s\n\n",
            $timestamp,
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    public static function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return self::$config;
        }

        return self::$config[$key] ?? $default;
    }
}
