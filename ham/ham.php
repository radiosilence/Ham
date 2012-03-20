<?php

class Ham {
    private $_compiled_routes;
    public $routes;
    public $config;
    public $cache;
    public $template_paths = array('./templates/');

    public function __construct() {
        $this->cache = Cache::create();
    }

    public function route($uri, $callback, $request_methods=array('GET')) {
        $this->routes[] = array(
            'uri' => $uri,
            'callback' => $callback,
            'request_methods' => $request_methods
        );
    }

    /**
     * Calls route and outputs it to STDOUT
     */
    public function run() {
        echo $this->_route();
    }

    /**
     * Makes sure the routes are compiled then scans through them
     * and calls whichever one is approprate.
     */
    protected function _route() {
        $uri = parse_url(str_replace($this->config['APP_URI'], '', $_SERVER['REQUEST_URI']));
        $path = $uri['path'];
        $_k = "found_uri:{$path}";
        $found = $this->cache->get($_k);
        if(!$found) {
            $found = $this->_find_route($path);
            $this->cache->set($_k, $found, 10);
        }
        $found['args'][0] = $this;
        return call_user_func_array($found['callback'], $found['args']);
    }

    protected function _find_route($path) {
        $compiled = $this->_get_compiled_routes();
        foreach($compiled as $route) {
            if(preg_match($route['compiled'], $path, $args)) {
                $found = array(
                    'callback' => $route['callback'],
                    'args' => $args
                );
                return $found;
            }
        }
        return abort(404);
    }

    protected function _get_compiled_routes() {
        $_k = 'compiled_routes';
        $compiled = $this->cache->get($_k);
        if($compiled)
            return $compiled;

        $compiled = array();
        foreach($this->routes as $route) {
            $route['compiled'] = $this->_compile_route($route['uri']);
            $compiled[] = $route;
        }
        $this->cache->set($_k, $compiled);
        return $compiled;
    }

    /**
     * Takes a route in simple syntax and makes it into a regular expression.
     */
    protected function _compile_route($uri) {
        $route = str_replace('/', '\/', preg_quote($uri));
        $types = array(
            '<int>' => '(\d+)',
            '<string>' => '([a-zA-Z0-9\-_]+)',
            '<path>' => '([a-zA-Z0-9\-_\/])'
        );
        foreach($types as $k => $v) {
            $route = str_replace(preg_quote($k), $v, $route);
        }
        return  '/^' . $route . '$/';
    }

    /**
     * Returns the contents of a template, populated with the data given to it.
     */
    public function render($name, $data) {
        $path = $this->_get_template_path($name);
        if(!$path)
            return abort(500, 'Template not found');
        ob_start();
        extract($data);
        require $path;
        return ob_get_clean();
    }

    /**
     * Configure an application object from a file.
     */
    public function config_from_file($filename) {
        $_k = 'config';
        $this->config = $this->cache->get($_k);
        if($this->config) {
            return True;
        } 
        require($filename);
        $conf = get_defined_vars();
        unset($conf['filename']);
        foreach($conf as $k => $v) {
            $this->config[$k] = $v;
        }
        $this->cache->set($_k, $this->config);
    }

    public function config_from_env($var) {
        return $this->config_from_file($_ENV[$var]);
    }

    protected function _get_template_path($name) {
        $_k = "template_path:{$name}";
        $path = $this->cache->get($_k);
        if($path)
            return $path;
        foreach($this->template_paths as $dir) {
            $path = $dir . $name;
            if(file_exists($path)) {
                $this->cache->set($_k, $path);
                return $path;
            }
        }
        return False;
    }
}

function abort($code, $message='') {
    return "<h1>{$code}</h1><p>{$message}</p>";
}



class XCache implements HamCompatibleCache {
    public function get($key) {
        return xcache_get($key);
    }
    public function set($key, $value, $ttl=1) {
        return xcache_set($key, $value, $ttl);
    }
    public function inc($key, $interval=1) {
        return xcache_inc($key, $interval);
    }
    public function dec($key, $interval=1) {
        return xcache_dec($key, $interval);
    }
}

class APC implements HamCompatibleCache {
    public function get($key) {
        if(!apc_exists($key))
            return False;
        return apc_fetch($key);
    }
    public function set($key, $value, $ttl=1) {

        return apc_store($key, $value, $ttl);
    }
    public function inc($key, $interval=1) {
        return apc_inc($key, $interval);
    }
    public function dec($key, $interval=1) {
        return apc_dec($key, $interval);
    }
}

interface HamCompatibleCache {
    public function set($key, $value, $ttl=1);
    public function get($key);
    public function inc($key, $interval=1);
    public function dec($key, $interval=1);
}


class Cache {
    private static $_cache;
    /**
     * Returning a cache object, be it XCache or APC.
     */
    public static function create() {
        if(function_exists('xcache_set')) {
            static::$_cache = new XCache();
        } else if(function_exists('apc_add')) {
            static::$_cache = new APC();
        }
        return static::$_cache;
    }
}