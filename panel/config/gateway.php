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

    'pm2' => [
        'app_name'    => env('PM2_APP_NAME', 'wa-gateway'),
        'config_file' => env('PM2_CONFIG_FILE', dirname(base_path()) . DIRECTORY_SEPARATOR . 'ecosystem.config.js'),
        'workdir'     => env('PM2_WORKDIR', dirname(base_path())),
        'binary'      => env('PM2_BINARY', 'pm2'),
        'run_as_user' => env('PM2_RUN_AS_USER', ''),
    ],

    // Comma-separated session IDs allowed to send password reset messages.
    'password_reset_sessions' => array_filter(array_map('trim', explode(',', env('PASSWORD_RESET_SESSIONS', '')))),
];
