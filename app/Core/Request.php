<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Class Request
 * Represents the incoming HTTP request.
 */
class Request
{
    private string $method;
    private string $uri;
    private string $path;
    /** @var array<string, mixed> */
    private array $query;
    /** @var array<string, mixed> */
    private array $post;
    /** @var array<string, mixed>|null */
    private ?array $json = null;
    /** @var array<string, string> */
    private array $headers = [];
    private string $rawBody = '';

    public function __construct(string $basePath = '')
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->query = $_GET;
        $this->post = $_POST;
        $this->parseHeaders();
        $this->parseBody();
        $this->normalizePath($basePath);
    }

    private function parseHeaders(): void
    {
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $this->headers[$name] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = str_replace('_', '-', strtolower($key));
                $this->headers[$name] = (string) $value;
            }
        }
    }

    private function parseBody(): void
    {
        $raw = file_get_contents('php://input');
        $this->rawBody = $raw !== false ? $raw : '';

        $contentType = $this->getHeader('content-type', '');
        if (str_contains($contentType, 'application/json') && !empty($this->rawBody)) {
            $decoded = json_decode($this->rawBody, true);
            if (is_array($decoded)) {
                $this->json = $decoded;
            }
        }
    }

    private function normalizePath(string $configuredBasePath = ''): void
    {
        $path = parse_url($this->uri, PHP_URL_PATH) ?? '/';

        // Auto-detect script base path if not explicitly configured
        if ($configuredBasePath === '') {
            $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
            $scriptDir = str_replace('\\', '/', $scriptDir);
            if ($scriptDir !== '/' && $scriptDir !== '.') {
                $configuredBasePath = rtrim($scriptDir, '/');
                // Remove /public if present in scriptDir when public is part of root
                if (str_ends_with($configuredBasePath, '/public')) {
                    $configuredBasePath = substr($configuredBasePath, 0, -7);
                }
            }
        }

        if ($configuredBasePath !== '' && str_starts_with($path, $configuredBasePath)) {
            $path = substr($path, strlen($configuredBasePath));
        }

        // Clean out /public prefix if URL directly hit public/index.php
        if (str_starts_with($path, '/public')) {
            $path = substr($path, 7);
        }

        $path = '/' . ltrim($path, '/');
        $this->path = $path === '' ? '/' : $path;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getQuery(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function getPost(string $key = '', mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->post;
        }
        return $this->post[$key] ?? $default;
    }

    public function getJson(string $key = '', mixed $default = null): mixed
    {
        if ($this->json === null) {
            return $key === '' ? [] : $default;
        }
        if ($key === '') {
            return $this->json;
        }
        return $this->json[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if ($this->json !== null && array_key_exists($key, $this->json)) {
            return $this->json[$key];
        }
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }
        return $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json ?? []);
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getBodySize(): int
    {
        return strlen($this->rawBody);
    }

    public function isJson(): bool
    {
        return str_contains($this->getHeader('content-type', ''), 'application/json');
    }

    public function getHeader(string $name, string $default = ''): string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    public function getIp(): string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }
}
