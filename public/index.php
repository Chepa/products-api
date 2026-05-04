<?php

declare(strict_types=1);

use Phalcon\Di\FactoryDefault;
use Phalcon\Http\Response;
use Phalcon\Logger\Logger;
use Phalcon\Mvc\Application;

define('BASE_PATH', dirname(__DIR__));
const APP_PATH = BASE_PATH . '/app';

require BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

$di = new FactoryDefault();

require APP_PATH . '/config/services.php';

$application = new Application($di);
$application->useImplicitView(false);

try {
    $response = $application->handle($_SERVER['REQUEST_URI'] ?? '/');
    $response->send();
} catch (\Throwable $e) {
    if ($di->has('logger')) {
        /** @var Logger $log */
        $log = $di->get('logger');
        $log->error(
            $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine()
            . "\n" . $e->getTraceAsString()
        );
    }

    $debug = false;
    if ($di->has('config')) {
        $debug = (bool)($di->get('config')['application']['debug'] ?? false);
    }

    if ($di->has('response')) {
        /** @var Response $r */
        $r = $di->get('response');
        $r->setStatusCode(500);
        $r->setContentType('application/json', 'UTF-8');
        $r->setJsonContent([
            'success' => false,
            'error'   => [
                'message' => $debug ? $e->getMessage() : 'Internal server error',
                'type'    => 'server',
            ],
        ]);
        $r->send();
    } else {
        echo 'Internal server error';
    }
}
