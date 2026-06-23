<?php

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $uri, $action, array $middleware = []): void
    {
        $this->add('GET', $uri, $action, $middleware);
    }

    public function post(string $uri, $action, array $middleware = []): void
    {
        $this->add('POST', $uri, $action, $middleware);
    }

    public function put(string $uri, $action, array $middleware = []): void
    {
        $this->add('PUT', $uri, $action, $middleware);
    }

    public function delete(string $uri, $action, array $middleware = []): void
    {
        $this->add('DELETE', $uri, $action, $middleware);
    }

    private function add(string $method, string $uri, $action, array $middleware = []): void
    {
        $uri = '/' . trim($uri, '/');
        $uri = $uri === '//' ? '/' : $uri;

        $this->routes[$method][$uri] = [
            'action' => $action,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $requestUri): void
    {
        $method = strtoupper($method);
        $path = $this->normalizePath($requestUri);

        $route = $this->routes[$method][$path] ?? null;

        if ($route === null) {
            http_response_code(404);
            $title = 'Page introuvable';
            ob_start();
            require APP_PATH . '/Views/errors/404.php';
            $content = ob_get_clean();
            require APP_PATH . '/Views/layouts/auth.php';
            return;
        }

        if (!$this->runMiddleware($route['middleware'])) {
            return;
        }

        $this->runAction($route['action']);
    }

    private function normalizePath(string $requestUri): string
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($scriptDir !== '/' && strpos($path, $scriptDir) === 0) {
            $path = substr($path, strlen($scriptDir));
        }

        $path = '/' . trim($path, '/');

        return $path === '//' ? '/' : $path;
    }

    private function runAction($action): void
    {
        if (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            $instance = new $controller();

            if (!method_exists($instance, $method)) {
                http_response_code(500);
                echo 'Methode de controleur introuvable';
                return;
            }

            $instance->{$method}();
            return;
        }

        if (is_callable($action)) {
            call_user_func($action);
            return;
        }

        http_response_code(500);
        echo 'Action de route invalide';
    }

    private function runMiddleware(array $middleware): bool
    {
        foreach ($middleware as $item) {
            $params = [];

            if (is_array($item)) {
                $class = array_shift($item);
                $params = $item;
            } else {
                $class = $item;
            }

            $instance = new $class();

            if (!method_exists($instance, 'handle')) {
                http_response_code(500);
                echo 'Middleware invalide';
                return false;
            }

            if ($instance->handle(...$params) === false) {
                return false;
            }
        }

        return true;
    }
}
