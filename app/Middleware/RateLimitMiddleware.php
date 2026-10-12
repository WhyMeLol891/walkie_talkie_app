<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\RateLimiter;

/**
 * Class RateLimitMiddleware
 * Protects endpoints from excessive requests.
 */
class RateLimitMiddleware
{
    private RateLimiter $limiter;

    public function __construct()
    {
        $this->limiter = new RateLimiter();
    }

    public function handle(Request $request): bool
    {
        $ip = $request->getIp();
        $allowed = $this->limiter->hit($ip, 60, 10); // 60 requests per 10 seconds per IP

        if (!$allowed) {
            if ($request->isJson()) {
                Response::json(['error' => 'Rate limit exceeded. Please slow down.'], 429);
            } else {
                http_response_code(429);
                echo "<h1>429 Too Many Requests</h1><p>Please wait a moment and refresh.</p>";
            }
            return false;
        }

        return true;
    }
}
