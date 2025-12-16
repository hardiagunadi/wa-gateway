<?php

return [
    'base_url' => env('WA_GATEWAY_BASE', 'http://localhost:5001'),
    'api_key' => env('WA_GATEWAY_KEY', ''),

    'node_env_path' => env('GATEWAY_NODE_ENV', dirname(base_path()) . DIRECTORY_SEPARATOR . '.env'),

    'npm' => [
        'command' => env('NPM_SERVER_COMMAND', 'npm run dev'),
        'workdir' => env('NPM_SERVER_WORKDIR', dirname(base_path())),
    ],
];
