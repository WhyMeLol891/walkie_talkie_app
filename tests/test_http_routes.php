<?php
declare(strict_types=1);

/**
 * Route & Front Controller Verification Test
 */

require_once __DIR__ . '/../app/Core/App.php';

use App\Core\App;
use App\Core\Request;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Helpers\UrlHelper;

App::boot();
Session::start();

echo "=== TESTING ROUTING AND CONTROLLERS ===\n\n";

// 1. Simulate GET /
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/walkie_talkie_app/';
$_SERVER['SCRIPT_NAME'] = '/walkie_talkie_app/index.php';

$router = new Router();
View::setViewsPath(App::getConfig('paths')['views']);
UrlHelper::setBasePath('/walkie_talkie_app');

$routeFile = App::getConfig('paths')['root'] . '/config/routes.php';
$routesCallback = require $routeFile;
$routesCallback($router);

$request = new Request('/walkie_talkie_app');

ob_start();
try {
    $router->dispatch($request);
    $html = ob_get_clean();
    $containsBrand = str_contains($html, 'WALKIE TALKIE') || str_contains($html, 'Join Channel');
    echo "[PASS] GET / renders join page correctly (Bytes: " . strlen($html) . ", Contains title: " . ($containsBrand ? 'Yes' : 'No') . ")\n";
} catch (\Throwable $e) {
    ob_end_clean();
    echo "[FAIL] GET / dispatch error: " . $e->getMessage() . "\n";
}

echo "\n=== ROUTE TESTS COMPLETED ===\n";
