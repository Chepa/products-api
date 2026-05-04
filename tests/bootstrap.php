<?php

declare(strict_types=1);

/**
 * Загрузка автозагрузчика и окружения перед PHPUnit: константы приложения и отключение лимита генерации токенов в CLI.
 */

define('BASE_PATH', dirname(__DIR__));
const APP_PATH = BASE_PATH . '/app';

require BASE_PATH . '/vendor/autoload.php';

if (is_readable(BASE_PATH . '/.env')) {
    Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
}

putenv('TOKEN_GENERATE_LIMIT=0');
$_ENV['TOKEN_GENERATE_LIMIT'] = '0';
