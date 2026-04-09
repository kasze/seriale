<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

/** @var App\Core\Router $router */
$router = app(App\Core\Router::class);
$router->dispatch(app(App\Core\Request::class))->send();

