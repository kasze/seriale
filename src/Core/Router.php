<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Router
{
    private array $routes = [];

    public function __construct(
        private View $view,
        private Logger $logger,
        private Config $config
    ) {
    }

    public function get(string $path, callable|array $handler, bool $auth = false): void
    {
        $this->map('GET', $path, $handler, $auth);
    }

    public function post(string $path, callable|array $handler, bool $auth = false): void
    {
        $this->map('POST', $path, $handler, $auth);
    }

    public function map(string $method, string $path, callable|array $handler, bool $auth = false): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'auth');
    }

    public function dispatch(Request $request): Response
    {
        try {
            foreach ($this->routes as $route) {
                if ($route['method'] !== $request->method()) {
                    continue;
                }

                $routePath = rtrim($route['path'], '/') ?: '/';
                $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $routePath);
                $pattern = '#^' . str_replace('/', '\/', $pattern) . '$#';

                if (preg_match($pattern, $request->path(), $matches) !== 1) {
                    continue;
                }

                if ($route['auth'] && !app(\App\Services\AuthService::class)->check()) {
                    return Response::redirect('/login');
                }

                $params = array_filter($matches, static fn ($key) => !is_int($key), ARRAY_FILTER_USE_KEY);
                $handler = $route['handler'];

                if (is_array($handler)) {
                    [$class, $method] = $handler;
                    $controller = app($class);

                    return $controller->{$method}($request, ...array_values($params));
                }

                return $handler($request, ...array_values($params));
            }

            throw new HttpException(404, 'Nie znaleziono strony.');
        } catch (HttpException $exception) {
            return $this->renderError($exception->statusCode(), $exception->getMessage());
        } catch (Throwable $throwable) {
            $this->logger->error($throwable->getMessage(), [
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);

            return $this->renderError(500, $this->config->bool('app.debug', false) ? $throwable->getMessage() : 'Wystapil nieoczekiwany blad aplikacji.');
        }
    }

    private function renderError(int $status, string $message): Response
    {
        $template = $status === 404 ? 'errors/404' : 'errors/500';

        return Response::html($this->view->render($template, [
            'status' => $status,
            'message' => $message,
        ]), $status);
    }
}
