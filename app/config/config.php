<?php

declare(strict_types=1);

return [
    'database' => [
        'adapter'  => 'Mysql',
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => (int) (getenv('DB_PORT') ?: 3306),
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'dbname'   => getenv('DB_NAME') ?: 'products_api',
        'charset'  => 'utf8mb4',
    ],
    'application' => [
        'baseUri'        => getenv('APP_BASE_URI') ?: '/',
        'timezone'       => getenv('APP_TIMEZONE') ?: 'UTC',
        'debug'          => filter_var(getenv('APP_DEBUG') ?: '0', FILTER_VALIDATE_BOOLEAN),
    ],
];
