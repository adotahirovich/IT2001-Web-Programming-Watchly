<?php
// Copy this file to config.php and replace the values. Never commit config.php.
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'watchly',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'jwt_secret' => 'CHANGE_THIS_TO_A_LONG_RANDOM_SECRET',
    'external_api_timeout' => 5,
    'external_api_base' => 'https://api.frankfurter.dev/v1',
];
