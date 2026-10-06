<?php

declare(strict_types=1);

namespace Ham;

class App
{
    /** @var list<Route> */
    private array $routes = [];

    private ?\Closure $notFoundHandler = null;

    public private(set) ?App $parent = null;

    /** @var array<string, mixed> */
    public array $config = [];

    /** @var list<string> */
    public array $templatePaths = ['./templates'];

    /** Template wrapped around every render() unless overridden; false disables it. */
    public string|false $layout = 'layout.php';

    /** Prefix stripped from request paths when the app is served from a subdirectory. */
    public string $basePath = '' {
        set => rtrim($value, '/');
    }

    public readonly Cache $cache;

    /**
     * @param string $name Distinct per app, as it namespaces the default cache.
     */
    public function __construct(
        public readonly string $name = 'default',
        ?Cache $cache = null,
        public readonly ?Logger $logger = null,
    ) {
        $this->cache = $cache ?? (function_exists('apcu_enabled') && apcu_enabled()
            ? new ApcuCache("{$name}:")
            : new NullCache());
    }

    /**
     * Register a handler for a URI pattern, or mount another app beneath it.
     *
     * Handlers receive the app followed by the pattern's captures. A mounted app
     * dispatches the rest of the path itself, so $methods does not apply to it.
     *
     * @param list<string> $methods
     */
    public function route(string $pattern, callable|self $handler, array $methods = ['GET']): static
    {
        if ($handler === $this) {
            throw new \LogicException('An app cannot be mounted on itself.');
        }
        if ($handler instanceof self) {
            $handler->parent = $this;
        }

        $this->routes[] = new Route(
            $pattern,
            $handler instanceof self ? $handler : $handler(...),
            array_map(strtoupper(...), $methods),
        );

        return $this;
    }

    /** Respond to the current request. */
    public function run(): void
    {
        $uri = $_SERVER['REQUEST_URI'] ?? null;
        $method = $_SERVER['REQUEST_METHOD'] ?? null;

        echo $this->handle(is_string($uri) ? $uri : '/', is_string($method) ? $method : 'GET');
    }

    public function handle(string $uri, string $method = 'GET'): string
    {
        $path = $uri
            |> (static fn ($uri) => parse_url($uri, PHP_URL_PATH) ?: '/')
            |> rawurldecode(...);

        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath)) ?: '/';
        }

        return $this->dispatch(strtoupper($method), $path);
    }

    private function dispatch(string $method, string $path): string
    {
        $methodMismatch = false;

        foreach ($this->routes as $route) {
            $args = $route->match($path);
            if ($args === null) {
                continue;
            }
            if ($route->handler instanceof self) {
                return $route->handler->dispatch($method, (string) array_pop($args) ?: '/');
            }
            if (!in_array($method, $route->methods, true)) {
                $methodMismatch = true;
                continue;
            }

            return self::body(($route->handler)($this, ...$args));
        }

        if ($methodMismatch) {
            return $this->abort(405);
        }
        if ($this->notFoundHandler) {
            http_response_code(404);

            return self::body(($this->notFoundHandler)($this));
        }

        return $this->abort(404);
    }

    private static function body(mixed $result): string
    {
        return match (true) {
            $result === null => '',
            is_string($result), is_int($result), is_float($result), $result instanceof \Stringable => (string) $result,
            default => throw new \UnexpectedValueException('Handlers must return a string, number, Stringable or null, not ' . get_debug_type($result) . '.'),
        };
    }

    /** Replace the default 404 response. The handler receives the app. */
    public function notFound(callable $handler): static
    {
        $this->notFoundHandler = $handler(...);

        return $this;
    }

    /**
     * Render a template without the layout. Templates are plain PHP with $data extracted into scope.
     *
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
    public function partial(string $view, array $data = []): string
    {
        $path = array_find(
            array_map(static fn ($dir) => rtrim($dir, '/') . '/' . $view, $this->templatePaths),
            static fn ($path) => is_file($path),
        ) ?? throw new \RuntimeException("Template not found: {$view}");

        $include = function (string $__path, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__path;
        };

        ob_start();
        try {
            $include($path, $data);

            return trim((string) ob_get_contents());
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Render a template inside a layout, which receives the result as $content.
     *
     * @param array<string, mixed> $data
     */
    #[\NoDiscard]
    public function render(string $view, array $data = [], string|false|null $layout = null): string
    {
        $content = $this->partial($view, $data);
        $layout ??= $this->layout;

        return $layout === false ? $content : $this->partial($layout, [...$data, 'content' => $content]);
    }

    #[\NoDiscard]
    public function json(mixed $data, int $code = 200): string
    {
        http_response_code($code);
        header('Content-Type: application/json');

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    #[\NoDiscard]
    public function abort(int $code, string $message = ''): string
    {
        http_response_code($code);

        return sprintf('<h1>%d</h1><p>%s</p>', $code, htmlspecialchars($message));
    }

    /** Merge in configuration from a PHP file that returns an array. */
    public function configFromFile(string $path): static
    {
        $config = require $path;
        if (!is_array($config)) {
            throw new \UnexpectedValueException("Configuration file must return an array: {$path}");
        }

        /** @var array<string, mixed> $config */
        $this->config = [...$this->config, ...$config];

        return $this;
    }

    /** Load configuration from the file named by an environment variable, so deployments choose their own. */
    public function configFromEnv(string $var): static
    {
        return $this->configFromFile(getenv($var) ?: throw new \RuntimeException("Environment variable {$var} is not set."));
    }
}
