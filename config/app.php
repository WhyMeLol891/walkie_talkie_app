<?php
declare(strict_types=1);

use App\Core\Env;

$stunEnv = (string) Env::get('STUN_SERVERS', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302');
$stunServers = array_filter(array_map('trim', explode(',', $stunEnv)));

$iceServers = [];
foreach ($stunServers as $server) {
    if ($server !== '') {
        $iceServers[] = ['urls' => $server];
    }
}

return [
    'name' => (string) Env::get('APP_NAME', 'Walkie Talkie PWA'),
    'env' => (string) Env::get('APP_ENV', 'local'),
    'debug' => (bool) Env::get('APP_DEBUG', true),
    
    // Explicit base path or empty string for auto-detection
    'base_path' => (string) Env::get('APP_BASE_PATH', ''),

    // Security & Token Secret
    'secret' => (string) Env::get('SIGNAL_SECRET', 'walkie_talkie_secret_salt_2026'),

    // Room & Peer limits
    'max_peers' => (int) Env::get('SIGNAL_MAX_PEERS', 8),
    'floor_timeout_ms' => (int) Env::get('SIGNAL_FLOOR_TIMEOUT_MS', 30000), // 30 seconds
    'peer_heartbeat_timeout_sec' => (int) Env::get('PEER_HEARTBEAT_TIMEOUT_SEC', 12), // 12 seconds

    // Payload limits
    'max_body' => (int) Env::get('SIGNAL_MAX_BODY', 32768),
    'max_data' => (int) Env::get('SIGNAL_MAX_DATA', 12288),

    // WebRTC ICE / STUN servers
    'ice_servers' => $iceServers,

    // Storage Paths
    'paths' => [
        'root' => dirname(__DIR__),
        'storage' => dirname(__DIR__) . '/storage',
        'rooms' => dirname(__DIR__) . '/storage/rooms',
        'logs' => dirname(__DIR__) . '/storage/logs',
        'ratelimit' => dirname(__DIR__) . '/storage/ratelimit',
        'views' => dirname(__DIR__) . '/views',
        'public' => dirname(__DIR__) . '/public',
    ],
];
