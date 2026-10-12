<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class Router
 * HTTP Route Registrar and Dispatcher with middleware support.
 */
class Router
{
    /** @var array<string, array<string, array{handler: mixed, middlewares: array}>> */
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, mixed $handler, array $middlewares = []): self
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
        return $this;
    }

    public function post(string $path, mixed $handler, array $middlewares = []): self
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
        return $this;
    }

    private function addRoute(string $method, string $path, mixed $handler, array $middlewares): void
    {
        $path = '/' . trim($path, '/');
        $this->routes[$method][$path] = [
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->getMethod();
        $path = '/' . trim($request->getPath(), '/');

        if (!isset($this->routes[$method])) {
            $this->sendNotFound();
            return;
        }

        // Exact route match
        if (isset($this->routes[$method][$path])) {
            $route = $this->routes[$method][$path];
            $this->executeRoute($route, $request);
            return;
        }

        // Try matching with /index.php stripped if needed
        $cleanPath = preg_replace('#^/index\.php#', '', $path) ?: '/';
        if (isset($this->routes[$method][$cleanPath])) {
            $route = $this->routes[$method][$cleanPath];
            $this->executeRoute($route, $request);
            return;
        }

        $this->sendNotFound();
    }

    private function executeRoute(array $route, Request $request): void
    {
        // Execute middlewares
        foreach ($route['middlewares'] as $middleware) {
            if (is_string($middleware) && class_exists($middleware)) {
                $instance = new $middleware();
                if (method_exists($instance, 'handle')) {
                    $result = $instance->handle($request);
                    if ($result === false) {
                        return; // Stopped by middleware
                    }
                }
            }
        }

        $handler = $route['handler'];

        if (is_callable($handler)) {
            $handler($request);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            if (class_exists($class)) {
                $controller = new $class();
                if (method_exists($controller, $method)) {
                    $controller->$method($request);
                    return;
                }
            }
        }

        // Handler error
        Response::json(['error' => 'Handler not found'], 500);
    }

    private function sendNotFound(): void
    {
        http_response_code(404);
        if (file_exists(dirname(__DIR__, 2) . '/views/errors/404.php')) {
            View::render('errors/404');
        } else {
            echo "<h1>404 Not Found</h1><p>The requested page was not found.</p>";
        }
        exit;
    }
}
