<?php
declare(strict_types=1);

/**
 * Walkie-Talkie Communication System
 * Root Application Entry Point
 */

require_once __DIR__ . '/app/Core/App.php';

use App\Core\App;

App::run();
