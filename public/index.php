<?php
declare(strict_types=1);

/**
 * Walkie-Talkie Communication System
 * Public Entry Point (for VirtualHosts or public document roots)
 */

require_once dirname(__DIR__) . '/app/Core/App.php';

use App\Core\App;

App::run();
