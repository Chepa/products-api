<?php

declare(strict_types=1);

use Phalcon\Mvc\Router;

/** @var Router $router */
$router->removeExtraSlashes(true);

$router->addGet('/', [
    'controller' => 'index',
    'action'     => 'index',
]);

$router->addGet('/api/docs', [
    'controller' => 'index',
    'action'     => 'swagger',
]);

$api = [
    'namespace' => 'App\\Controllers\\Api\\V1',
];

$router->addPost('/api/v1/tokens/generate', $api + [
    'controller' => 'tokens',
    'action'     => 'generate',
]);

$router->addGet('/api/v1/tokens', $api + [
    'controller' => 'tokens',
    'action'     => 'index',
]);

$router->addDelete('/api/v1/tokens/{id:[0-9]+}', $api + [
    'controller' => 'tokens',
    'action'     => 'delete',
]);

$router->addGet('/api/v1/products', $api + [
    'controller' => 'products',
    'action'     => 'index',
]);

$router->addGet('/api/v1/products/{id:[0-9]+}', $api + [
    'controller' => 'products',
    'action'     => 'get',
]);

$router->addPost('/api/v1/products', $api + [
    'controller' => 'products',
    'action'     => 'create',
]);

$router->addPut('/api/v1/products/{id:[0-9]+}', $api + [
    'controller' => 'products',
    'action'     => 'update',
]);

$router->addPatch('/api/v1/products/{id:[0-9]+}', $api + [
    'controller' => 'products',
    'action'     => 'update',
]);

$router->addDelete('/api/v1/products/{id:[0-9]+}', $api + [
    'controller' => 'products',
    'action'     => 'delete',
]);

$router->addGet('/api/v1/categories', $api + [
    'controller' => 'categories',
    'action'     => 'index',
]);

$router->addGet('/api/v1/categories/tree', $api + [
    'controller' => 'categories',
    'action'     => 'tree',
]);

$router->addGet('/api/v1/categories/{id:[0-9]+}', $api + [
    'controller' => 'categories',
    'action'     => 'get',
]);

$router->addPost('/api/v1/categories', $api + [
    'controller' => 'categories',
    'action'     => 'create',
]);

$router->addPut('/api/v1/categories/{id:[0-9]+}', $api + [
    'controller' => 'categories',
    'action'     => 'update',
]);

$router->addPatch('/api/v1/categories/{id:[0-9]+}', $api + [
    'controller' => 'categories',
    'action'     => 'update',
]);

$router->addDelete('/api/v1/categories/{id:[0-9]+}', $api + [
    'controller' => 'categories',
    'action'     => 'delete',
]);
