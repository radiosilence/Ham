<?php
 
class HamTest extends PHPUnit_Framework_TestCase {
    protected $app;

    protected function setUp() {
        $cache1 = Ham::create_cache('default', True);
        $app = new Ham('default', $cache1, 'log.txt');
        $app->route('/', function($app) {
            return 'hello world';
        });
        $app->route('/hello/<string>', function($app, $name) {
            return "hello {$name}";
        });

        $app->route('/timestwo/<int>', function($app, $int) {
            return $int * 2;
        });
        $app->route('/add/<int>/<int>', function($app, $a, $b) {
            return $a + $b;
        });
        $app->route('/dividefloat/<float>/<float>', function($app, $a, $b) {
            if($b == 0)
                return 'NaN';
            return $a / $b;
        });

        $beans = new Ham('beans', $cache1);
        $beans->route('/', function($app) {
            return "beans";
        });
        $beans->route('/baked', function($app) {
            return "yum";
        });
        $app->route('/beans', $beans);
        $this->app = $app;
    }

    protected function tearDown() {

    }

    public function testHelloWorld() {
        $app = $this->app;
        $_SERVER['REQUEST_URI'] = '/';
        $this->assertEquals('hello world', $app());
    }
    public function test404() {
        $app = $this->app;
        $_SERVER['REQUEST_URI'] = '/asdlkad8o7';
        $this->assertContains('404', $app());

    }

    public function testStringParameter() {
        $app = $this->app;
        $_SERVER['REQUEST_URI'] = '/hello/bort';
        $this->assertContains('bort', $app());
    }

    public function testIntParameter() {
        $app = $this->app;
        $inputs = array(1, 0, 5, 3);
        $outputs = array(2, 0, 10, 6);
        foreach($inputs as $k => $v) {
            $_SERVER['REQUEST_URI'] = "/timestwo/{$v}";
            $this->assertEquals($outputs[$k], $app());
        }
    }

    public function testMultiIntParameter() {
        $app = $this->app;
        $inputs_a = array(1, 5,  2, 6,  3);
        $inputs_b = array(0, -2, 7, 20, -10);
        $outputs = array( 1, 3,  9, 26, -7);
        foreach($inputs_a as $k => $v) {
            $_SERVER['REQUEST_URI'] = "/add/{$v}/{$inputs_b[$k]}";
            $this->assertEquals($outputs[$k], $app());
        }
    }

    public function testSubAppHome() {
        $app = $this->app;
        $uris = array('/beans', '/beans/');
        foreach($uris as $uri){
            $_SERVER['REQUEST_URI'] = $uri;
            $this->assertEquals('beans', $app());
        }
    }
    public function testSubAppPage() {
        $app = $this->app;
        $uris = array('/beans/baked', '/beans/baked/');
        foreach($uris as $uri){
            $_SERVER['REQUEST_URI'] = $uri;
            $this->assertEquals('yum', $app());
        }
    }

    public function testFloatParameter() {
        $app = $this->app;

        $inputs_a = array(1.2, 8.3,   1.176, 0,   3);
        $inputs_b = array(23,  -1.25,  4.2, 20,  0);
        $outputs = array(
            0.052173913,
            -6.64, 
            0.28,
            0,
            'NaN'
        );
        foreach($inputs_a as $k => $v) {
            $_SERVER['REQUEST_URI'] = "/dividefloat/{$v}/{$inputs_b[$k]}";
            $this->assertEquals($outputs[$k], $app());
        }
        $_SERVER['REQUEST_URI'] = '/dividefloat/1.6/2.5';
        $this->assertEquals('0.64', $app());
    }

    public function testLogging() {
        $app = $this->app;

        foreach ( array('log', 'info', 'error') as $type ) {
            $pre_lines = count(file('log.txt'));

            $app->logger->$type('message');

            // First, test that a line was added
            $post_lines = count(file('log.txt'));
            $this->assertEquals($post_lines, $pre_lines + 1);

            // ...and second, that the the line actually logged the expected value
            $match = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\t' . $type . '\tmessage\n/m', file_get_contents('log.txt'));
            $this->assertEquals($match, 1);
        }
    }
}