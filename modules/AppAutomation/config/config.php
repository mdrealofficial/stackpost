<?php

return [
    'name' => 'AppAutomation',
    'route_prefix' => 'portal/automation',
    'api_prefix' => 'api/automation/v1',
    'api' => [
        'post_rate_limit_per_minute' => 60,
        'idempotency_ttl_days' => 7,
    ],
    'runtime' => [
        // Deliberately independent of QUEUE_CONNECTION. This prevents a site
        // configured with the sync driver from running automation in a web
        // request and gives operations one worker lane to scale or pause.
        'connection' => env('AUTOMATION_QUEUE_CONNECTION', 'automation'),
        'queue' => env('AUTOMATION_QUEUE', 'automation'),
        'heartbeat_max_age_seconds' => 180,
        'max_attempts' => 3,
        'max_nodes_per_run' => 100,
        'lease_seconds' => 300,
        'backoff_seconds' => [30, 120, 600],
    ],
    'google_drive' => [
        'integration_key' => 'google_drive_automation',
        'client_id' => env('AUTOMATION_GOOGLE_DRIVE_CLIENT_ID', ''),
        'client_secret' => env('AUTOMATION_GOOGLE_DRIVE_CLIENT_SECRET', ''),
        'callback_url' => env('AUTOMATION_GOOGLE_DRIVE_CALLBACK_URL', ''),
        'webhook_url' => env('AUTOMATION_GOOGLE_DRIVE_WEBHOOK_URL', ''),
        'scopes' => [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/userinfo.email',
            'https://www.googleapis.com/auth/drive.readonly',
        ],
        'watch_ttl_seconds' => 518400,
        'renew_before_seconds' => 86400,
        'max_change_pages_per_poll' => 50,
    ],
];
