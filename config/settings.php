<?php

declare(strict_types=1);

return [
    'root_dir' => dirname(__DIR__),
    'session' => [
        'name' => 'indiewebify',
        'cookie_samesite' => 'Strict',
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'cache_expire' => 0,
    ],
    'logger' => [
        // 'path' => dirname(__DIR__) .  '/logs',
        // 'level' => Logger::DEBUG,
        // 'name' => 'app',
        // 'filename' => 'app.log',
        // 'file_permission' => 0666,
    ],
    'twig' => [
        'paths' => [
            dirname(__DIR__) . '/templates',
        ],
        'options' => [
            'cache_enabled' => false,
            'cache_path' => dirname(__DIR__) . '/tmp/twig',
        ],
    ],
];
