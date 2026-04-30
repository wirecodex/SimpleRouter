<?php

declare(strict_types=1);

namespace ProcessWire;

if (!function_exists('ProcessWire\simplerouter')) {
    /**
     * Get the SimpleRouter instance
     *
     * @return \SimpleWire\Router\Router
     */
    function simplerouter(): \SimpleWire\Router\Router
    {
        return wire()->simplerouter;
    }
}

if (!function_exists('ProcessWire\router')) {
    /**
     * Get the Router singleton instance
     *
     * @return \SimpleWire\Router\Router
     */
    function router(): \SimpleWire\Router\Router
    {
        return wire()->simplerouter;
    }
}

if (!function_exists('ProcessWire\route')) {
    /**
     * Register a route with the Router
     *
     * @param string $definition Route definition (e.g., "GET:/path" or "/path")
     * @param callable $handler Route handler
     * @return \SimpleWire\Router\Router
     */
    function route(string $definition, callable $handler): \SimpleWire\Router\Router
    {
        if (str_contains($definition, ':')) {
            [$method, $path] = explode(':', $definition, 2);
        } else {
            $method = 'GET';
            $path   = $definition;
        }

        return wire()->simplerouter->add($method, $path, $handler);
    }
}
