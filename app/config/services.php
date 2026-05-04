<?php

declare(strict_types=1);

use App\Plugins\BearerAuthPlugin;
use Phalcon\Di\FactoryDefault;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Url as UrlResolver;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt as VoltEngine;
use Phalcon\Events\Event;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Logger\Adapter\Stream as LoggerStream;
use Phalcon\Logger\Logger;

/** @var FactoryDefault $di */
$configPath = APP_PATH . '/config/config.php';
$config = require $configPath;

date_default_timezone_set($config['application']['timezone']);

$di->setShared('config', function () use ($configPath) {
    return require $configPath;
});

$di->setShared('logger', function () {
    $dir = BASE_PATH . '/cache/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $adapter = new LoggerStream($dir . '/app.log');

    return new Logger('app', ['main' => $adapter]);
});

$di->setShared('router', function () {
    $router = new Router(false);
    require APP_PATH . '/config/routes.php';
    return $router;
});

$di->setShared('url', function () use ($di) {
    $url = new UrlResolver();
    $url->setBaseUri($di->get('config')['application']['baseUri']);
    return $url;
});

$di->setShared('view', function () use ($di) {
    $view = new View();
    $view->setViewsDir(APP_PATH . '/views/');
    $view->registerEngines([
        '.volt' => function ($view) use ($di) {
            $volt = new VoltEngine($view, $di);
            $debug = (bool)($di->get('config')['application']['debug'] ?? false);
            $volt->setOptions([
                'path'       => BASE_PATH . '/cache/volt/',
                'always'     => $debug,
                'extension'  => '.php',
                'separator'  => '_',
            ]);
            return $volt;
        },
    ]);
    return $view;
});

$di->setShared('db', function () use ($di) {
    $c = $di->get('config')['database'];
    $class = 'Phalcon\Db\Adapter\Pdo\\' . $c['adapter'];

    return new $class([
        'host'     => $c['host'],
        'port'     => $c['port'] ?? 3306,
        'username' => $c['username'],
        'password' => $c['password'],
        'dbname'   => $c['dbname'],
        'charset'  => $c['charset'] ?? 'utf8mb4',
        'options'  => [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ],
    ]);
});

$di->setShared('categorySubtree', function () use ($di) {
    return new \App\Services\CategorySubtreeService($di->get('db'));
});

$di->setShared('tokenRateLimiter', function () {
    $limitEnv = getenv('TOKEN_GENERATE_LIMIT');
    $limit = $limitEnv === false ? 30 : (int) $limitEnv;
    $windowEnv = getenv('TOKEN_GENERATE_WINDOW');
    $window = $windowEnv === false ? 3600 : (int) $windowEnv;
    $dir = BASE_PATH . '/cache/ratelimit';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return new \App\Services\TokenGenerateRateLimiter($limit, $window, $dir);
});

$di->setShared('dispatcher', function () use ($di) {
    $dispatcher = new Dispatcher();
    $dispatcher->setDefaultNamespace('App\\Controllers');

    $eventsManager = new EventsManager();
    $eventsManager->attach('dispatch:beforeExecuteRoute', new BearerAuthPlugin());
    $eventsManager->attach('dispatch:beforeException', function (Event $event, $dispatcher, \Throwable $exception) use ($di) {
        if (!$di->has('request')) {
            return;
        }
        $uri = parse_url($di->request->getURI(), PHP_URL_PATH) ?: '/';
        if (strncmp($uri, '/api/', 5) !== 0) {
            return;
        }

        $debug = (bool)($di->get('config')['application']['debug'] ?? false);
        if ($di->has('logger')) {
            /** @var Logger $log */
            $log = $di->get('logger');
            $log->error(
                $exception->getMessage()
                . ' in ' . $exception->getFile() . ':' . $exception->getLine()
                . "\n" . $exception->getTraceAsString()
            );
        }

        $di->response->setStatusCode(500);
        $di->response->setContentType('application/json', 'UTF-8');
        $di->response->setJsonContent([
            'success' => false,
            'error'   => [
                'message' => $debug ? $exception->getMessage() : 'Internal server error',
                'type'    => 'server',
            ],
        ]);
        $event->stop();
    });

    $dispatcher->setEventsManager($eventsManager);
    return $dispatcher;
});
