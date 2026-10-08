<?php
declare(strict_types=1);

return [
    'name' => \App\Core\Env::get('APP_NAME', 'Walkie Talkie'),
    'env' => \App\Core\Env::get('APP_ENV', 'production'),
    'debug' => \App\Core\Env::bool('APP_DEBUG', false),
    'base_path' => \App\Core\Env::get('APP_BASE_PATH', ''),
    'signal_secret' => \App\Core\Env::get('SIGNAL_SECRET', 'change-me'),
    'signal_max_peers' => (int) \App\Core\Env::get('SIGNAL_MAX_PEERS', '8'),
    'signal_floor_timeout_ms' => (int) \App\Core\Env::get('SIGNAL_FLOOR_TIMEOUT_MS', '30000'),
    'signal_max_body' => (int) \App\Core\Env::get('SIGNAL_MAX_BODY', '32768'),
    'signal_max_data' => (int) \App\Core\Env::get('SIGNAL_MAX_DATA', '12288'),
    'signal_rate_limit_window' => (int) \App\Core\Env::get('SIGNAL_RATE_LIMIT_WINDOW', '10'),
    'signal_rate_limit_max' => (int) \App\Core\Env::get('SIGNAL_RATE_LIMIT_MAX', '20'),
    'stun_servers' => [
        ['urls' => 'stun:stun.l.google.com:19302'],
        ['urls' => 'stun:stun1.l.google.com:19302'],
    ],
];
