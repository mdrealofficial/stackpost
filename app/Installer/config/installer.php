<?php

return [
    'required_php_version' => env('INSTALLER_REQUIRED_PHP_VERSION', '8.3.0'),
    'required_extensions' => [
        'ctype',
        'fileinfo',
        'filter',
        'hash',
        'json',
        'mbstring',
        'openssl',
        'pdo',
        'tokenizer',
        'xml',
    ],
    'writable_paths' => [
        '.env',
        'bootstrap/cache',
        'storage',
    ],
    'purchase_code_required' => env('INSTALLER_PURCHASE_CODE_REQUIRED', false),
    'purchase_verify_url' => env('INSTALLER_PURCHASE_VERIFY_URL', null),
    'final_session_driver' => env('INSTALLER_FINAL_SESSION_DRIVER', 'database'),
    'final_cache_store' => env('INSTALLER_FINAL_CACHE_STORE', 'database'),
    'final_queue_connection' => env('INSTALLER_FINAL_QUEUE_CONNECTION', 'database'),
    'admin_plan_slug' => env('INSTALLER_ADMIN_PLAN_SLUG', 'agency-lifetime'),
    'default_seeders' => [
        \Database\Seeders\StackPostsPlanSeeder::class,
        \Database\Seeders\AITemplateCategorySeeder::class,
        \Database\Seeders\AITemplateSeeder::class,
    ],
];
