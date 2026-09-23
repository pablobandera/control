<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{0: string, 1: string, 2: array{0: class-string, 1: string}}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, array $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, array $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array $handler): void
    {
        $this->routes[] = [$method, $pattern, $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }

            $regex = '#^' . preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $matches)) {
                array_shift($matches);
                [$class, $methodName] = $handler;
                $controller = new $class();
                $controller->$methodName(...$matches);
                return;
            }
        }

        Response::error('Ruta no encontrada.', 404);
    }
}
