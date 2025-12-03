<?php
return [
    'default' => 'file',
    'prefix' => 'app_',
    'stores' => [
        'file' => [
            'driver' => 'file',
            'path' => APP_PATH . 'temp/cache/'
        ],
        'memcached' => [
            'driver' => 'memcached',
            'servers' => [
                [
                    'host' => '127.0.0.1',
                    'port' => 11211,
                    'weight' => 100
                ]
            ]
        ],
        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'host' => '127.0.0.1',
            'port' => 6379,
            'password' => null,
            'database' => 0
        ]
    ],
    'ttl' => 3600, // Tiempo de vida por defecto en segundos
    'enabled' => true
];