<?php

// Handlers are called from this file, so it keeps coercive typing: route captures
// arrive as strings, and handlers may still declare int or float parameters.

class Ham
{
    private const array TYPES = [
        'int' => '([0-9\-]+)',
        'float' => '([0-9\.\-]+)',
        'string' => '([a-zA-Z0-9\-_]+)',
        'path' => '([a-zA-Z0-9\-_\/.]+)',
    ];

    /** @var list<array{uri: string, callback: callable, request_methods: list<string>|null, wildcard: bool, compiled: string}> */
    public array $routes = [];

    /** @var array<string, mixed> */
    public array $config = [];

    public HamCache $cache;

    public ?HamLogger $logger = null;

    public ?Ham $parent = null;

    public ?string $prefix = null;

    /** Layout for render(); null means layout.php, false disables it. */
    public string|false|null $layout = null;

    /** @var list<string> */
    public array $template_paths = ['./templates/'];

    private ?Closure $errorFunc = null;

    private ?string $errorMessage = null;

    /**
     * @param string $name A canonical name for this app. Must not be shared between apps or cache collisions will happen. Unless you want that.
     * @param HamCache|false|null $cache Detected with create_cache() when not given.
     * @param string|false|null $log Path of a log file to write to.
     */
    public function __construct(
        public string $name = 'default',
        HamCache|false|null $cache = false,
        string|false|null $log = false,
    ) {
        $this->cache = $cache ?: static::create_cache($name);
        if ($log) {
            $this->logger = static::create_logger($log);
        }
    }

    /**
     * Add a route, or mount another app beneath it.
     *
     * Routes accept any request method unless given a list. A mounted app dispatches
     * the rest of the path itself.
     *
     * @param list<string>|null $request_methods
     */
    public function route(string $uri, callable $callback, ?array $request_methods = null): bool
    {
        if ($callback === $this) {
            return false;
        }
        $wildcard = $callback instanceof self;
        if ($wildcard) {
            $callback->prefix = $uri;
            $callback->parent = $this;
        }

        $this->routes[] = [
            'uri' => $uri,
            'callback' => $callback,
            'request_methods' => $request_methods === null ? null : array_map(strtoupper(...), $request_methods),
            'wildcard' => $wildcard,
            'compiled' => self::compile_route($uri, $wildcard),
        ];

        return true;
    }

    /** Respond to the current request. */
    public function run(): void
    {
        $response = $this();
        if ($response !== null && !is_scalar($response) && !$response instanceof Stringable) {
            throw new UnexpectedValueException('Handlers must return a string, number, Stringable or null, not ' . get_debug_type($response) . '.');
        }
        echo $response;
    }

    /**
     * Dispatch the current request and return the handler's result.
     *
     * @param Ham|false|null $app Parent application, available to handlers as $app->parent.
     */
    public function __invoke(Ham|false|null $app = false): mixed
    {
        if ($app instanceof self) {
            $this->parent = $app;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? null;
        $method = $_SERVER['REQUEST_METHOD'] ?? null;
        $base = $this->config['APP_URI'] ?? '';

        $path = (is_string($uri) ? $uri : '/')
            |> (static fn ($uri) => parse_url($uri, PHP_URL_PATH) ?: '/')
            |> rawurldecode(...);

        foreach ([is_string($base) ? rtrim($base, '/') : '', rtrim($this->prefix ?? '', '/')] as $strip) {
            if ($strip !== '' && str_starts_with($path, $strip)) {
                $path = substr($path, strlen($strip)) ?: '/';
            }
        }

        return $this->dispatch(is_string($method) ? strtoupper($method) : 'GET', $path);
    }

    /** Set the response for unmatched paths. If $logMessage is given, it is logged as an error each time. */
    public function onError(callable $closure_callback, ?string $logMessage = null): void
    {
        $this->errorFunc = $closure_callback(...);
        $this->errorMessage = $logMessage;
    }

    private function dispatch(string $method, string $path): mixed
    {
        $methodMismatch = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['compiled'], $path, $matches)) {
                continue;
            }
            $args = array_slice($matches, 1);
            if ($route['callback'] instanceof self) {
                return $route['callback']->dispatch($method, array_pop($args) ?: '/');
            }
            if (!self::allows($route['request_methods'], $method)) {
                $methodMismatch = true;
                continue;
            }

            return ($route['callback'])($this, ...$args);
        }

        if ($methodMismatch) {
            return $this->abort(405);
        }
        if ($this->errorFunc === null) {
            return $this->abort(404);
        }

        http_response_code(404);
        if ($this->errorMessage !== null) {
            $this->logger?->error($this->errorMessage);
        }

        return ($this->errorFunc)($this);
    }

    /** @param list<string>|null $methods */
    private static function allows(?array $methods, string $method): bool
    {
        return $methods === null
            || in_array($method, $methods, true)
            || ($method === 'HEAD' && in_array('GET', $methods, true));
    }

    /** Compile a route such as `/add/<int>/<int>` to a regular expression. */
    private static function compile_route(string $uri, bool $wildcard): string
    {
        $parts = preg_split('/<(int|float|string|path)>/', rtrim($uri, '/'), flags: PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $i => $part) {
            $regex .= $i % 2 === 1 ? self::TYPES[$part] : preg_quote($part, '#');
        }

        return $wildcard ? "#^{$regex}((?:/.*)?)$#" : "#^{$regex}/?$#";
    }

    /**
     * Render a template without the layout. Templates are PHP files with $data extracted into scope.
     *
     * @param array<string, mixed>|null $data
     */
    #[\NoDiscard]
    public function partial(string $view, ?array $data = null): string
    {
        $path = array_find(
            array_map(static fn ($dir) => $dir . $view, $this->template_paths),
            static fn ($path) => is_file($path),
        );
        if ($path === null) {
            return $this->abort(500, 'Template not found');
        }

        $include = function (string $__path, array $__data): void {
            extract($__data, EXTR_SKIP);
            require $__path;
        };

        ob_start();
        try {
            $include($path, $data ?? []);

            return trim((string) ob_get_contents());
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Render a template inside a layout, which receives the result as $content.
     *
     * @param array<string, mixed>|null $data
     * @param string|false|null $layout Overrides $this->layout; false renders without one.
     */
    #[\NoDiscard]
    public function render(string $view, ?array $data = null, string|false|null $layout = null): string
    {
        $content = $this->partial($view, $data);
        $layout ??= $this->layout ?? 'layout.php';
        if ($layout === false) {
            return $content;
        }

        return $this->partial($layout, [...$data ?? [], 'content' => $content]);
    }

    /** Send $obj as JSON and end the request. */
    public function json(mixed $obj, int $code = 200): never
    {
        header('Content-type: application/json', true, $code);
        echo json_encode($obj, JSON_THROW_ON_ERROR);
        exit;
    }

    /** Merge in configuration from a PHP file, which may either declare variables or return an array. */
    public function config_from_file(string $filename): bool
    {
        $config = (static function (string $__file): array {
            $__returned = require $__file;
            $__vars = get_defined_vars();
            unset($__vars['__file'], $__vars['__returned']);

            return is_array($__returned) ? [...$__vars, ...$__returned] : $__vars;
        })($filename);

        /** @var array<string, mixed> $config */
        $this->config = [...$this->config, ...$config];

        return true;
    }

    /** Load configuration from the file named by an environment variable, so deployments choose their own. */
    public function config_from_env(string $var): bool
    {
        $filename = $_ENV[$var] ?? getenv($var);
        if (!is_string($filename) || $filename === '') {
            throw new RuntimeException("Environment variable {$var} is not set.");
        }

        return $this->config_from_file($filename);
    }

    /** Set the response status and return an error page, for callers without an app instance. */
    #[\NoDiscard]
    public static function _abort(int $code, string $message = '', ?Ham $app = null): string
    {
        http_response_code($code);
        $name = $app->name ?? 'App not set, call this function from the app or explicitly pass the $app as the last argument';

        return "<h1>{$code}</h1><p>{$message}</p><p>{$name}</p>";
    }

    /** Set the response status and return an error page naming this app. */
    #[\NoDiscard]
    public function abort(int $code, string $message = ''): string
    {
        return self::_abort($code, $message, $this);
    }

    /** Cache factory: APCu when enabled, then Redis, falling back to a cache that stores nothing. */
    public static function create_cache(string $prefix, bool $dummy = false, bool $redisFirst = false): HamCache
    {
        $apcu = function_exists('apcu_enabled') && apcu_enabled();
        $redis = class_exists('Redis');

        return match (true) {
            $dummy => new Dummy($prefix),
            $redisFirst && $redis => new RedisCache($prefix),
            $apcu => new APC($prefix),
            $redis => new RedisCache($prefix),
            default => new Dummy($prefix),
        };
    }

    /** Logger factory; creates the file if needed. */
    public static function create_logger(string $log_file): HamLogger
    {
        if (!file_exists($log_file) && (!is_writable(dirname($log_file)) || !touch($log_file))) {
            throw new RuntimeException("Log file couldn't be created: {$log_file}");
        }
        if (!is_writable($log_file)) {
            throw new RuntimeException("Log file isn't writable: {$log_file}");
        }

        return new FileLogger($log_file);
    }
}

abstract class HamCache
{
    public function __construct(public string|false $prefix = false) {}

    protected function _p(string $key): string
    {
        return $this->prefix ? "{$this->prefix}:{$key}" : $key;
    }

    abstract public function set(string $key, mixed $value, int $ttl = 1): bool;

    /** Returns false on a miss. */
    abstract public function get(string $key): mixed;

    abstract public function inc(string $key, int $interval = 1): int|false;

    abstract public function dec(string $key, int $interval = 1): int|false;
}

/** APCu, the successor to APC. */
class APC extends HamCache
{
    #[\Override]
    public function get(string $key): mixed
    {
        $value = apcu_fetch($this->_p($key), $found);

        return $found ? $value : false;
    }

    #[\Override]
    public function set(string $key, mixed $value, int $ttl = 1): bool
    {
        try {
            return apcu_store($this->_p($key), $value, $ttl);
        } catch (Exception) {
            apcu_delete($this->_p($key));

            return false;
        }
    }

    #[\Override]
    public function inc(string $key, int $interval = 1): int|false
    {
        return apcu_inc($this->_p($key), $interval);
    }

    #[\Override]
    public function dec(string $key, int $interval = 1): int|false
    {
        return apcu_dec($this->_p($key), $interval);
    }
}

/** Stores strings and numbers; other values are converted by phpredis. */
class RedisCache extends HamCache
{
    private Redis $conn;

    public function __construct(string|false $prefix = false, string $host = '127.0.0.1')
    {
        parent::__construct($prefix);
        $this->conn = new Redis();
        $this->conn->connect($host);
    }

    #[\Override]
    public function get(string $key): mixed
    {
        return $this->conn->get($this->_p($key));
    }

    /** A $ttl of 0 keeps the value until evicted. */
    #[\Override]
    public function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return (bool) ($ttl > 0
            ? $this->conn->setex($this->_p($key), $ttl, $value)
            : $this->conn->set($this->_p($key), $value));
    }

    #[\Override]
    public function inc(string $key, int $interval = 1): int|false
    {
        $value = $this->conn->incrBy($this->_p($key), $interval);

        return is_int($value) ? $value : false;
    }

    #[\Override]
    public function dec(string $key, int $interval = 1): int|false
    {
        $value = $this->conn->decrBy($this->_p($key), $interval);

        return is_int($value) ? $value : false;
    }
}

/** Stores nothing; the fallback when no cache backend is available. */
class Dummy extends HamCache
{
    #[\Override]
    public function get(string $key): mixed
    {
        return false;
    }

    #[\Override]
    public function set(string $key, mixed $value, int $ttl = 1): bool
    {
        return false;
    }

    #[\Override]
    public function inc(string $key, int $interval = 1): int|false
    {
        return false;
    }

    #[\Override]
    public function dec(string $key, int $interval = 1): int|false
    {
        return false;
    }
}

abstract class HamLogger
{
    abstract public function error(string $message): bool;

    abstract public function log(string $message): bool;

    abstract public function info(string $message): bool;
}

/** Appends tab-separated `timestamp, severity, message` lines to a file. */
class FileLogger extends HamLogger
{
    public function __construct(public string $file) {}

    public function write(string $message, string $severity): bool
    {
        if (!is_writable($this->file)) {
            return false;
        }

        return file_put_contents($this->file, date('Y-m-d H:i:s') . "\t{$severity}\t{$message}\n", FILE_APPEND | LOCK_EX) !== false;
    }

    #[\Override]
    public function error(string $message): bool
    {
        return $this->write($message, 'error');
    }

    #[\Override]
    public function log(string $message): bool
    {
        return $this->write($message, 'log');
    }

    #[\Override]
    public function info(string $message): bool
    {
        return $this->write($message, 'info');
    }
}
