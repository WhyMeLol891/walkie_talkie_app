<?php
declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\ChannelController;
use App\Controllers\SignalingController;
use App\Middleware\CsrfMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Core\Router;

return function (Router $router): void {
    // Landing / Join Page
    $router->get('/', [HomeController::class, 'index']);
    $router->post('/join', [HomeController::class, 'join'], [CsrfMiddleware::class]);

    // Channel Walkie-Talkie Interface
    $router->get('/channel', [ChannelController::class, 'index']);

    // Leave Channel
    $router->get('/leave', [HomeController::class, 'leave']);
    $router->post('/leave', [HomeController::class, 'leave']);

    // Signaling Endpoints (HTTP polling & floor control)
    $router->post('/api/signal', [SignalingController::class, 'handle'], [RateLimitMiddleware::class]);
    $router->post('/signal', [SignalingController::class, 'handle'], [RateLimitMiddleware::class]);
};
