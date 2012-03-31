<?php

class Ham {
    private $_compiled_routes;
    public $routes;
    public $config;
    public $name;
    public $cache;
    public $parent;
    public $template_paths = array('./templates/');

    /**
     * Create a Ham application.
     * @param $name a canonical name for this app. Must not be shared between
     *  apps or cache collisions will happen. Unless you want that.
     */
    public function __construct($name='default', $cache=False) {
        $this->name = $name;
        if($cache === False) {
            $cache = create_cache($this->name);
        }
        $this->cache = $cache;
    }

    public function route($uri, $callback, $request_methods=array('GET')) {
        if($this === $callback) {
            return False;
        }
        $wildcard = False;
        if($callback instanceof Ham) {
            $callback->prefix = $uri;
            $wildcard = True;
        }

        $this->routes[] = array(
            'uri' => $uri,
            'callback' => $callback,
            'request_methods' => $request_methods,
            'wildcard' => $wildcard
        );
    }

    /**
     * Calls route and outputs it to STDOUT
     */
    public function run() {
        echo $this();
    }

    /**
     * Invoke method allows the application to be mounted as a closure.
     * @param $parent a parent application that can be referenced by 
     * $app->parent
     */
    public function __invoke($app=False) {
        $this->parent = $app;
        return $this->_route($_SERVER['REQUEST_URI']);
    }

    /**
     * Makes sure the routes are compiled then scans through them
     * and calls whichever one is approprate.
     */
    protected function _route($request_uri) {
        $uri = parse_url(str_replace($this->config['APP_URI'], '', $request_uri));
        $path = $uri['path'];
        $_k = "found_uri:{$path}";
        $found = $this->cache->get($_k);
        if(!$found) {
            $found = $this->_find_route($path);
            $this->cache->set($_k, $found, 10);
        }
        if(!$found) {
            return abort(404);
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
        return False;
    }

    protected function _get_compiled_routes() {
        $_k = 'compiled_routes';
        $compiled = $this->cache->get($_k);
        if($compiled)
            return $compiled;

        $compiled = array();
        foreach($this->routes as $route) {
            $route['compiled'] = $this->_compile_route($route['uri'], $route['wildcard']);
            $compiled[] = $route;
        }
        $this->cache->set($_k, $compiled);
        return $compiled;
    }

    /**
     * Takes a route in simple syntax and makes it into a regular expression.
     */
    protected function _compile_route($uri, $wildcard) {
        $route = $this->_escape_route_uri(rtrim($uri, '/'));
        $types = array(
            '<int>' => '([0-9\-]+)',
            '<float>' => '([0-9\.\-]+)',
            '<string>' => '([a-zA-Z0-9\-_]+)',
            '<path>' => '([a-zA-Z0-9\-_\/])'
        );
        foreach($types as $k => $v) {
            $route =  str_replace(preg_quote($k), $v, $route);
        }
        if($wildcard)
            $wc = '(.*)?';
        else
            $wc = '';
        $ret = '/^' . $this->_escape_route_uri($this->prefix) . $route . '\/?' . $wc . '$/';
        return  $ret;
    }

    protected function _escape_route_uri($uri) {
        return str_replace('/', '\/', preg_quote($uri));
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

    /**
     * Allows configuration file to be specified by environement variable,
     * to make deployment easy.
     */
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
    if(php_sapi_name() != 'cli')
        header("Status: {$code}", False, $code);
    return "<h1>{$code}</h1><p>{$message}</p>";
}

class XCache extends HamCache {
    public function get($key) {
        return xcache_get($this->_p($key));
    }
    public function set($key, $value, $ttl=1) {
        return xcache_set($this->_p($key), $value, $ttl);
    }
    public function inc($key, $interval=1) {
        return xcache_inc($this->_p($key), $interval);
    }
    public function dec($key, $interval=1) {
        return xcache_dec($this->_p($key), $interval);
    }
}

class APC extends HamCache {
    public function get($key) {
        if(!apc_exists($this->_p($key)))
            return False;
        return apc_fetch($this->_p($key));
    }
    public function set($key, $value, $ttl=1) {
        try {
            return apc_store($this->_p($key), $value, $ttl);
        } catch(Exception $e) {
            apc_delete($this->_p($key));
            return False;
        }
    }
    public function inc($key, $interval=1) {
        return apc_inc($this->_p($key), $interval);
    }
    public function dec($key, $interval=1) {
        return apc_dec($this->_p($key), $interval);
    }
}

class Dummy extends HamCache {
    public function get($key) {
        return False;
    }
    public function set($key, $value, $ttl=1) {
        return False;
    }
    public function inc($key, $interval=1) {
        return False;
    }
    public function dec($key, $interval=1) {
        return False;
    }
}

abstract class HamCache {
    public $prefix;

    public function __construct($prefix=False) {
        $this->prefix = $prefix;
    }
    protected function _p($key) {
        if($this->prefix)
            return $this->prefix . ':' . $key;
        else
            return $key;
    }
    abstract public function set($key, $value, $ttl=1);
    abstract public function get($key);
    abstract public function inc($key, $interval=1);
    abstract public function dec($key, $interval=1);
}

/**
 * Cache factory, be it XCache or APC.
 */
function create_cache($prefix, $dummy=False) {
    if(function_exists('xcache_set') && !$dummy) {
        return new XCache($prefix);
    } else if(function_exists('apc_fetch') && !$dummy) {
        return new APC($prefix);
    } else {
        return new Dummy($prefix);
    }
}
