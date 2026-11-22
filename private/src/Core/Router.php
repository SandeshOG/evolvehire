<?php
declare(strict_types=1);

namespace Evolve\Core;

/**
 * Route table with named params. Controllers are page_ functions (loaded
 * from private/views/) referenced by name; dispatch resolves them with
 * is_callable at call time.
 */
final class Router
{
    /** @var array<int, array{pattern:string, method:string, fn:callable|string}> */
    private array $routes = [];
    private string $prefix = '';

    public function get(string $pattern, callable|string $fn): void
    {
        $this->add('GET', $pattern, $fn);
    }

    public function post(string $pattern, callable|string $fn): void
    {
        $this->add('POST', $pattern, $fn);
    }

    public function add(string $method, string $pattern, callable|string $fn): void
    {
        $this->routes[] = ['pattern' => $this->prefix . $pattern, 'method' => $method, 'fn' => $fn];
    }

    /** Dispatch the current request path. Returns false when nothing matched. */
    public function dispatch(string $method, string $path): bool
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            $regex = $this->compile($route['pattern']);
            if (preg_match($regex, $path, $m) === 1) {
                $params = [];
                foreach ($m as $k => $v) {
                    if (is_string($k)) {
                        $params[$k] = $v;
                    }
                }
                $fn = $route['fn'];
                if (is_string($fn)) {
                    $fn($params);
                } else {
                    $fn($params);
                }
                return true;
            }
        }
        return false;
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[a-z0-9-]{1,190})', $pattern) ?? $pattern;
        return '#^' . $regex . '$#';
    }
}
