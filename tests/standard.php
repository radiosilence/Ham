<?php
require_once '../ham/ham.php';
 
class HamTest extends PHPUnit_Framework_TestCase {
    protected $app;

    protected function setUp() {
        $app = new Ham();
        $app->cache = create_cache($app, True);
        $app->route('/', function($app) {
            return 'hello world';
        });
        $app->route('/hello/<string>', function($app, $name) {
            return "hello {$name}";
        });
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
}