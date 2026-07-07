<?php

namespace App\Core;

/**
 * Minimal HTTP router. Supports route params (e.g. /workflows/{id}),
 * per-route middleware stacks, and grouping by HTTP verb.
 */
class Router
{
    private array $routes = [];

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function patch(string $path, $handler, array $middleware = []): void
    {
        $this->add('PATCH', $path, $handler, $middleware);
    }

    public function delete(string $path, $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, $handler, array $middleware): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    private function pathToRegex(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }
            $regex = $this->pathToRegex($route['path']);
            if (preg_match($regex, $request->path, $matches)) {
                $params = array_filter($matches, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);

                try {
                    // Run middleware stack; each middleware can short-circuit by
                    // sending a response and returning false.
                    foreach ($route['middleware'] as $middleware) {
                        $result = $middleware($request);
                        if ($result === false) {
                            return;
                        }
                    }
                    call_user_func($route['handler'], $request, $params);
                } catch (\Throwable $e) {
                    Logger::error('Unhandled exception: ' . $e->getMessage(), [
                        'path' => $request->path,
                        'trace' => $e->getTraceAsString(),
                    ]);
                    Response::error('Internal server error.', 500);
                }
                return;
            }
        }
        Response::error('Route not found.', 404);
    }
}
