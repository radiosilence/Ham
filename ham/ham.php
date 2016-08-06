<?php

// Ham - https://github.com/radiosilence/Ham

class Router {

    public $routes;
    public $config;
    public $name;
    public $cache;
    public $logger;
    public $parent;
    public $prefix;
    public $layout = null;
    public $template_paths = array('./templates/');

    /**
     * Create new router
     * @param string $name a canonical name for this app. Must not be shared between apps or cache collisions will happen. Unless you want that.
     * @param mixed $cache
     * @param bool $log
     */
    public function __construct($name='default', $cache=False, $log=False) {
        $this->name = $name;
        if($cache === False) {
            $cache = static::create_cache($this->name);
        }
        $this->cache = $cache;
        if($log) {
            $this->logger = static::create_logger($log);
        }
    }

    /**
     * Add routes
     * @param $uri
     * @param $callback
     * @param array $request_methods
     * @return bool
     */
    public function route($uri, $callback, $request_methods=array('GET')) {
        if($this === $callback) {
            return False;
        }
        $wildcard = False;
        if($callback instanceof Router) {
            $callback->prefix = $uri;
            $wildcard = True;
        }

        $this->routes[] = array(
            'uri' => $uri,
            'callback' => $callback,
            'request_methods' => $request_methods,
            'wildcard' => $wildcard
        );

        return true;
    }

    /**
     * Calls route and outputs it to STDOUT
     */
    public function run() {
        echo $this();
    }

    /**
     * Invoke method allows the application to be mounted as a closure.
     * @param mixed|bool $app parent application that can be referenced by $app->parent
     * @return mixed|string
     */
    public function __invoke($app=False) {
        $this->parent = $app;
        return $this->_route($_SERVER['REQUEST_URI']);
    }

    /**
	* Exists only as a function to fill as a setter for 
	* A developer to add custom 404 pages
	* If a log message is set, it will append the error functio
	* to the developer-defined one. 
    */

    public function onError($closure_callback,$logMessage=NULL) {
    	if($logMessage) {
    		$closure_callback = function(){
    			call_user_func($closure_callback);
    			$this->error($logMessage);
    		};
    	}
		$this->errorFunc = $closure_callback;

    }

    /**
	* Called upon when 404 is deduced to be the only outcome
    */

    protected function page_not_found() {
    	if(isset($this->errorFunc)){
    		header("HTTP/1.0 404 Not Found");
    		return call_user_func($this->errorFunc);  // Dev defined Error
    	} else {
    		return static::abort(404); 			      // Generic Error
    	}
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
            return $this->page_not_found();
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

    public function partial($view, $data = null) {
        $path = $this->_get_template_path($view);
        if(!$path)
              return static::abort(500, 'Template not found');

        ob_start();
        if(is_array($data))
            extract($data);
        require $path;
        return trim(ob_get_clean());
    }

    /**
       * Returns the contents of a template, populated with the data given to it.
       */
    public function render($view, $data = null, $layout = null) {
        $content =  $this->partial($view, $data);

        if ($layout !== false) {

            if ($layout == null) {
                $layout = ($this->layout == null) ? 'layout.php' : $this->layout;
            }

            $data['content'] = $content;
            return $this->partial($layout, $data);
        } else {
            return $content;
        }
    }

    public function json($obj, $code = 200) {
        header('Content-type: application/json', true, $code);
        echo json_encode($obj);
        exit;
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

        return true;
    }

    /**
     * Allows configuration file to be specified by environment variable,
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

    /**
     * static version of abort
     * to allow for calling of abort by class
     * @param integer $code
     * @param string $message
     * @param Ham $app
     * @return string
     */
    public static function _abort($code, $message='',$app=null) {
        if(php_sapi_name() != 'cli')
            header("Status: {$code}", False, $code);
        $name = !is_null($app) ? 
                $app->name : 
                'App not set, call this function from the app or explicitly pass the $app as the last argument';
        return "<h1>{$code}</h1><p>{$message}</p><p>{$name}</p>";
    }

    /**
     * application specific Cancel method
     * @param integer $code
     * @param string $message
     * @return string
     */
    public function abort($code,$message=''){
        return self::_abort($code,$message,$this);
    }

    /**
     * Cache factory, be it XCache or APC.
     */
    public static function create_cache($prefix, $dummy=False,$redisFirst=False) {
        if($redisFirst){
            if(class_exists("Redis") && !$dummy){
                return new RedisCache($prefix);
            }else if(function_exists('xcache_set') && !$dummy) {
                return new XCache($prefix);
            } else if(function_exists('apc_fetch') && !$dummy) {
                return new APC($prefix);
            } else {
                return new Dummy($prefix);
            }
        }else{
            if(function_exists('xcache_set') && !$dummy) {
                return new XCache($prefix);
            } else if(function_exists('apc_fetch') && !$dummy) {
                return new APC($prefix);
            } else if(class_exists("Redis") && !$dummy){
                return new RedisCache($prefix);
            } else {
                return new Dummy($prefix);
            }
        }
    }

    /**
     * Logger factory; just FileLogger for now.
     */
    public static function create_logger($log_file) {
        if (!file_exists($log_file)) {
            if (is_writable(dirname($log_file))) {
                touch($log_file);
            } else {
                static::abort(500, "Log file couldn't be created.");
            }
        }

        if (!is_writable($log_file)) {
            static::abort(500, "Log file isn't writable.");
        }

        return new FileLogger($log_file);
    }
}

class XCache extends RouterCache {
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

class APC extends RouterCache {
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

class RedisCache extends RouterCache{
    public function __construct($prefix=false,$host="127.0.0.1"){
        parent::__construct($prefix);
        $this->_conn = new Redis();
        $this->_conn->connect($host);
    }

    public function get($key){
        return $this->_conn->get($this->_p($key));
    }
    public function set($key, $val,$ttl=false){
        $ttl = $ttl ?  $ttl*1000 : null;
        if(is_null($ttl)){
            return $this->_conn->set($this->_p($key),$val);
        }else{
            return $this->_conn->set($this->_p($key),$val,$ttl);
        }
    }
    public function inc($key,$interval=1){
        $this->_conn->incr($this->_p($key),$interval);
    
    }
    public function dec($key,$interval=1){
        $this->_conn->decr($this->_p($key),$interval);
    }
}

class Dummy extends RouterCache {
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

abstract class RouterCache {
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

class FileLogger extends RouterLogger {
    public $file;

    public function __construct($file) {
        $this->file = $file;
    }

    public function write($message, $severity) {
        $message = date('Y-m-d H:i:s') . "\t$severity\t$message\n";
        if (!is_writable($this->file)) {
            return false;
        }
        file_put_contents($this->file, $message, FILE_APPEND | LOCK_EX);

        return true;
    }

    public function error($message) {
        return $this->write($message, 'error');
    }

    public function log($message) {
        return $this->write($message, 'log');
    }

    public function info($message) {
        return $this->write($message, 'info');
    }
}

abstract class RouterLogger {
    abstract public function error($message);
    abstract public function log($message);
    abstract public function info($message);
}
