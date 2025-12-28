<?php

return [
    'base_url' => env('WA_GATEWAY_BASE', 'http://localhost:5001'),
    'api_key' => env('WA_GATEWAY_KEY', ''),

    'node_env_path' => env('GATEWAY_NODE_ENV', dirname(base_path()) . DIRECTORY_SEPARATOR . '.env'),
    'session_config_path' => env('SESSION_CONFIG_PATH', dirname(base_path()) . DIRECTORY_SEPARATOR . 'wa_credentials' . DIRECTORY_SEPARATOR . 'session-config.json'),

    'npm' => [
        // Allow overriding via env; default command is built dynamically.
        'command' => env('NPM_SERVER_COMMAND'),
        'workdir' => env('NPM_SERVER_WORKDIR', dirname(base_path())),
    ],

    // Comma-separated session IDs allowed to send password reset messages.
    'password_reset_sessions' => array_filter(array_map('trim', explode(',', env('PASSWORD_RESET_SESSIONS', '')))),
];
