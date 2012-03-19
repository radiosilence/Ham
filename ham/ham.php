<?php
class HamCache {
    private static $_cache;

    /**
     * Making sure we have a cache loaded (APC or XCache), so we can provide
     * it as a singleton.
     */
    public static function init() {

    }

    public static function set($key, $value, $ttl=10) {
        return static::$_cache->set($key->value->$ttl);
    }
    public static function get($key) {
        return static::$_cache->get($key);
    }
}

class XCache implements HamCache {
    public function get($key) {
        return xcache_get($key);
    }
    public function set($key) {

    }
}

class Ham {
    private $_compiled_routes;
    public $routes;
    public $template_paths = array('./templates/');
    public function route($uri, $callback, $request_methods=array('GET')) {
        $this->routes[] = array(
            'uri' => $uri,
            'callback' => $callback,
            'request_methods' => $request_methods
        );
    }
    /**
     * Makes sure the routes are compiled then scans through them
     * and calls whichever one is approprate.
     */
    public function run() {

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

    public function config_from_file($filename) {
        require($filename);
        var_dump(get_defined_vars());
    }

    public function config_from_env($var) {
        return $this->config_from_file($_ENV[$var]);
    }

    protected function _get_template_path($name) {
        $k = "template_path:{$name}";
        $path = HamCache::get($k)
        if($path)
            return $path;
        foreach($this->template_paths as $dir) {
            $path = $dir . $name . '.php';
            if(file_exists($path)) {
                HamCache::set($k, $path);
                return $name;
            }
        }
        return False;
    }
}

function abort($code, $message='') {
    return "<h1>{$code}</h1><p>{$message}</p>";
}