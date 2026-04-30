<?php

declare(strict_types=1);

namespace SimpleWire\Router;

use ProcessWire\Wire404Exception;

class Router
{
    protected static bool $singleton = true;

    /** @var \ProcessWire\ProcessWire */
    protected $wire;

    protected array $routes = [];
    protected array $cachedPatterns = [];
    protected string $cacheFile;
    protected string $cacheHashFile;
    protected int $cacheTTL = 3600;
    protected bool $cacheModified = false;
    protected int $lastCacheSave = 0;
    protected int $cacheSaveInterval = 5;
    protected $notFoundHandler = null;
    protected bool $handle404 = false;

    protected static array $typeAliases = [
        'integer' => '[0-9]+',
        'float' => '[0-9]+\.[0-9]+',
        'number' => '[0-9]*\.?[0-9]+',
        'alpha' => '[a-zA-Z]+',
        'alphanumeric' => '[a-zA-Z0-9]+',
        'unicode' => '[\p{Letter}\p{Mark}]+',
        'slug' => '[a-zA-Z0-9\-_]+',
        'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
        'date' => '[0-9]{4}-[0-9]{2}-[0-9]{2}',
        'year' => '[0-9]{4}',
        'month' => '0[1-9]|1[0-2]',
        'day' => '0[1-9]|[12][0-9]|3[01]',
    ];

    /**
     * Dependency Injection via Constructor
     */
    public function __construct(\ProcessWire\ProcessWire $wire, array $config = [])
    {
        $this->wire = $wire;

        $this->cacheTTL = (int) ($config['router_cacheTTL'] ?? 3600);
        $this->handle404 = (bool) ($config['router_handle404'] ?? false);

        $template = $this->wire->page ? $this->wire->page->template->name : 'default';
        $cachePath = $this->wire->config->paths->cache . 'SimpleWire/Router/';

        if (!is_dir($cachePath)) {
            $this->wire->files->mkdir($cachePath, true);
        }

        $this->cacheFile = $cachePath . $template . '.cache.php';
        $this->cacheHashFile = $this->cacheFile . '.hash';

        if (!empty($config['router_enableCache'])) {
            $this->loadCache();
        }
    }

    // ========================================
    // Route Registration
    // ========================================

    public function add(string $method, string $path, callable $handler): self
    {
        $methods = explode('|', strtoupper($method));
        foreach ($methods as $m) {
            $this->routes[] = [
                'method' => $m,
                'path' => $this->transformTypeAliases($path),
                'handler' => $handler,
                'originalPath' => $path,
            ];
        }
        $this->cacheModified = true;
        return $this;
    }

    public function get(string $path, callable $handler): self { return $this->add('GET', $path, $handler); }
    public function post(string $path, callable $handler): self { return $this->add('POST', $path, $handler); }
    public function put(string $path, callable $handler): self { return $this->add('PUT', $path, $handler); }
    public function patch(string $path, callable $handler): self { return $this->add('PATCH', $path, $handler); }
    public function delete(string $path, callable $handler): self { return $this->add('DELETE', $path, $handler); }
    public function any(string $path, callable $handler): self { return $this->add('GET|POST|PUT|PATCH|DELETE', $path, $handler); }

    public function setNotFoundHandler(callable $handler): self
    {
        $this->notFoundHandler = $handler;
        return $this;
    }

    // ========================================
    // Dispatch
    // ========================================

    public function dispatch(): mixed
    {
        if ($this->cacheModified && (time() - $this->lastCacheSave) > $this->cacheSaveInterval) {
            $this->saveCache();
        }

        $method = strtoupper($this->wire->input->requestMethod());
        $segments = $this->wire->input->urlSegments();
        $requestUri = count($segments) ? implode('/', $segments) : '';
        $requestUri = trim($requestUri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== 'ANY' && $route['method'] !== $method) continue;

            $params = $this->match($route['path'], $requestUri);
            if ($params !== false) {
                return call_user_func_array($route['handler'], is_array($params) ? $params : []);
            }
        }

        if ($this->handle404) {
            if (is_callable($this->notFoundHandler)) return call_user_func($this->notFoundHandler);
            throw new Wire404Exception();
        }

        return null;
    }

    // ========================================
    // Matching
    // ========================================

    protected function match(string $path, string $request): bool|array
    {
        $path = trim($path, '/');
        if ($path === $request) return true;

        $pathSegments = explode('/', $path);
        $requestSegments = $request === '' ? [] : explode('/', $request);
        $requestCount = count($requestSegments);
        $params = [];

        // Count required (non-optional) segments to validate the request length.
        $requiredCount = 0;
        foreach ($pathSegments as $seg) {
            if (!preg_match('/^\[.+\]$/', $seg)) $requiredCount++;
        }

        if ($requestCount < $requiredCount || ($requestCount > count($pathSegments) && !str_contains($path, '{*}'))) {
            return false;
        }

        $ri = 0; // tracks position in request segments independently
        foreach ($pathSegments as $segment) {
            $isOptional = (bool) preg_match('/^\[(.+)\]$/', $segment, $optMatches);
            $inner = $isOptional ? $optMatches[1] : $segment;
            $val   = $requestSegments[$ri] ?? null;

            if ($inner === '{*}') {
                $params['tail'] = array_slice($requestSegments, $ri);
                return $params;
            }

            // Named parameter with pattern: {name:pattern}
            if (preg_match('/^\{(\w+):(.+?)\}$/', $inner, $matches)) {
                if ($val !== null && preg_match('/^' . $matches[2] . '$/', $val)) {
                    $params[$matches[1]] = $val;
                    $ri++;
                    continue;
                }
                if ($isOptional && $val === null) continue;
                return false;
            }

            // Named parameter: {name}
            if (preg_match('/^\{(\w+)\}$/', $inner, $matches)) {
                if ($val !== null) {
                    $params[$matches[1]] = $val;
                    $ri++;
                    continue;
                }
                if ($isOptional) continue;
                return false;
            }

            // Named options: (planet:earth|mars|jupiter)
            if (preg_match('/^\((\w+):([^\)]+)\)$/', $inner, $matches)) {
                $options = explode('|', $matches[2]);
                if ($val !== null && in_array($val, $options, true)) {
                    $params[$matches[1]] = $val;
                    $ri++;
                    continue;
                }
                if ($isOptional && $val === null) continue;
                return false;
            }

            // Simple options: (earth|mars|jupiter) — requires at least two options
            if (preg_match('/^\(([^\):]+(?:\|[^\):]+)+)\)$/', $inner, $matches)) {
                $options = explode('|', $matches[1]);
                if ($val !== null && in_array($val, $options, true)) {
                    $params[] = $val;
                    $ri++;
                    continue;
                }
                if ($isOptional && $val === null) continue;
                return false;
            }

            // Mixed content: prefix{name}suffix (e.g. v{major}, file.{ext})
            if (preg_match('/^(.*?)\{(\w+)\}(.*?)$/', $inner, $matches)) {
                [, $prefix, $paramName, $suffix] = $matches;
                if (
                    $val !== null &&
                    str_starts_with($val, $prefix) &&
                    (empty($suffix) || str_ends_with($val, $suffix))
                ) {
                    $params[$paramName] = substr($val, strlen($prefix), strlen($val) - strlen($prefix) - strlen($suffix));
                    $ri++;
                    continue;
                }
                if ($isOptional && $val === null) continue;
                return false;
            }

            // Literal segment
            if ($inner !== $val) {
                if ($isOptional && $val === null) continue;
                return false;
            }
            $ri++;
        }

        // Ensure all request segments were consumed
        if ($ri !== $requestCount) return false;

        return count($params) ? $params : true;
    }

    protected function transformTypeAliases(string $path): string
    {
        return preg_replace_callback('/\{(\w+)<(\w+)>\}/', function ($matches) {
            $regex = self::$typeAliases[$matches[2]] ?? $matches[2];
            return '{' . $matches[1] . ':' . $regex . '}';
        }, $path);
    }

    // ========================================
    // Cache
    // ========================================

    protected function loadCache(): void
    {
        if (!file_exists($this->cacheFile) || !file_exists($this->cacheHashFile)) return;
        if (sha1_file($this->cacheFile) !== file_get_contents($this->cacheHashFile)) return;

        $cache = include $this->cacheFile;
        if (!is_array($cache) || (time() - $cache['timestamp']) > $this->cacheTTL) return;

        $this->cachedPatterns = $cache['patterns'] ?? [];
    }

    protected function saveCache(): void
    {
        $patterns = [];
        foreach ($this->routes as $route) {
            $patterns[] = ['method' => $route['method'], 'path' => $route['path']];
        }

        $data = ['patterns' => $patterns, 'timestamp' => time()];
        $content = '<?php return ' . var_export($data, true) . ';';

        $this->wire->files->filePutContents($this->cacheFile, $content);
        $this->wire->files->filePutContents($this->cacheHashFile, sha1($content));
        $this->cacheModified = false;
        $this->lastCacheSave = time();
    }
}
