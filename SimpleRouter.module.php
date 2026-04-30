<?php

declare(strict_types=1);

namespace ProcessWire;

/** @property \ProcessWire\ProcessWire $wire */
class SimpleRouter extends WireData implements Module, ConfigurableModule
{
    /** @var \SimpleWire\Router\Router */
    protected $router;

    public static function getModuleInfo(): array
    {
        return [
            'title'    => 'SimpleRouter',
            'version'  => '0.1.0',
            'summary'  => 'URL routing with pattern matching and caching for ProcessWire.',
            'icon'     => 'road',
            'author'   => 'WireCodex',
            'autoload' => true,
            'singular' => true,
            'requires' => 'ProcessWire>=3.0.200,PHP>=8.1',
        ];
    }

    // ========================================
    // Lifecycle
    // ========================================

    public function init(): void
    {
        spl_autoload_register(function (string $class): void {
            $prefix = 'SimpleWire\\Router\\';
            if (!str_starts_with($class, $prefix)) return;
            $file = __DIR__ . '/classes/' . substr($class, strlen($prefix)) . '.php';
            if (file_exists($file)) require_once $file;
        });

        $config = array_merge(
            ['router_enableCache' => true, 'router_cacheTTL' => 3600, 'router_handle404' => false],
            (array) $this->wire('modules')->getConfig($this)
        );

        $this->router = new \SimpleWire\Router\Router($this->wire, $config);

        $this->wire('simplerouter', $this->router);

        require_once __DIR__ . '/functions.php';
    }

    public function ready(): void
    {
        $this->addHookMethod('Page::route', function (HookEvent $event): void {
            $definition = $event->arguments(0);
            $handler    = $event->arguments(1);

            if (str_contains($definition, ':')) {
                [$method, $path] = explode(':', $definition, 2);
            } else {
                $method = 'GET';
                $path   = $definition;
            }

            $this->router->add($method, $path, $handler);
            $event->return = $event->object;
        });

        $this->addHookMethod('Page::dispatchRoutes', function (HookEvent $event): void {
            $event->return = $this->router->dispatch();
        });
    }

    // ========================================
    // Config UI
    // ========================================

    public static function getModuleConfigInputfields(array $data): InputfieldWrapper
    {
        $modules = wire()->modules;
        $wrapper = new InputfieldWrapper();

        /** @var InputfieldCheckbox $field */
        $field = $modules->get('InputfieldCheckbox');
        $field->name = 'router_enableCache';
        $field->label = 'Enable Router Cache';
        $field->checked = !empty($data['router_enableCache']);
        $field->columnWidth = 33;
        $wrapper->add($field);

        /** @var InputfieldInteger $field */
        $field = $modules->get('InputfieldInteger');
        $field->name = 'router_cacheTTL';
        $field->label = 'Cache TTL (seconds)';
        $field->value = $data['router_cacheTTL'] ?? 3600;
        $field->showIf = 'router_enableCache=1';
        $field->columnWidth = 33;
        $wrapper->add($field);

        /** @var InputfieldCheckbox $field */
        $field = $modules->get('InputfieldCheckbox');
        $field->name = 'router_handle404';
        $field->label = 'Router handles 404';
        $field->checked = !empty($data['router_handle404']);
        $field->columnWidth = 34;
        $wrapper->add($field);

        return $wrapper;
    }

    // ========================================
    // Install / Uninstall
    // ========================================

    public function ___install(): void
    {
        $cachePath = $this->wire('config')->paths->cache . 'SimpleWire/Router/';
        if (!is_dir($cachePath)) {
            wireMkdir($cachePath, true);
        }
    }

    public function ___uninstall(): void
    {
        $cacheDir = $this->wire('config')->paths->cache . 'SimpleWire/Router/';
        if (is_dir($cacheDir)) {
            wireRmdir($cacheDir, true);
        }
    }
}
