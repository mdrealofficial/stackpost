<?php

return [
    'default_area' => 'guest',

    'base_path' => resource_path('themes'),

    'vite' => [
        'hot_file' => public_path('hot'),
        'build_root' => 'build/themes',
        'entry_points' => [
            'assets/js/app.js',
        ],
    ],

    'areas' => [
        'guest' => [
            'fallback' => env('THEME_FRONTEND', 'default'),
            'option_key' => 'frontend_theme',
            'route_prefixes' => [],
            'route_names' => [
                'home',
                'login',
                'register',
                'password.*',
                'verification.*',
            ],
        ],

        'app' => [
            'fallback' => env('THEME_BACKEND', 'default'),
            'option_key' => 'backend_theme',
            'route_prefixes' => [
                'app',
                'dashboard',
                'settings',
            ],
            'route_names' => [
                'dashboard',
                'admin-languages.*',
                'admin-themes.*',
                'admin-users.*',
                'profile.*',
                'appearance.*',
                'security.*',
            ],
        ],
    ],
];
