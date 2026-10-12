<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Class CsrfMiddleware
 * Verifies CSRF token on POST requests.
 */
class CsrfMiddleware
{
    public function handle(Request $request): bool
    {
        if ($request->getMethod() !== 'POST') {
            return true;
        }

        $token = $request->input('_csrf_token') ?? $request->getHeader('x-csrf-token');

        if (!Session::verifyCsrf($token)) {
            if ($request->isJson()) {
                Response::json(['error' => 'Invalid or expired CSRF token.'], 403);
            } else {
                Session::flash('error', 'Your session expired. Please try again.');
                Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
            }
            return false;
        }

        return true;
    }
}
