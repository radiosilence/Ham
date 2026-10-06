<?php

use PHPUnit\Framework\TestCase;

class HamTest extends TestCase
{
    protected Ham $app;

    protected string $log;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->log = (string) tempnam(sys_get_temp_dir(), 'ham');

        $cache1 = Ham::create_cache('default', true);
        $app = new Ham('default', $cache1, $this->log);
        $app->route('/', function ($app) {
            return 'hello world';
        });
        $app->route('/hello/<string>', function ($app, $name) {
            return "hello {$name}";
        });
        $app->route('/timestwo/<int>', function ($app, $int) {
            return $int * 2;
        });
        $app->route('/add/<int>/<int>', function ($app, $a, $b) {
            return $a + $b;
        });
        $app->route('/dividefloat/<float>/<float>', function ($app, $a, $b) {
            if ($b == 0) {
                return 'NaN';
            }

            return $a / $b;
        });

        $beans = new Ham('beans', $cache1);
        $beans->route('/', function ($app) {
            return 'beans';
        });
        $beans->route('/baked', function ($app) {
            return 'yum';
        });
        $beans->route('/whose', function ($app) {
            return $app->parent->name;
        });
        $app->route('/beans', $beans);
        $app->template_paths = [__DIR__ . '/fixtures/templates/'];
        $this->app = $app;
    }

    protected function tearDown(): void
    {
        unlink($this->log);
    }

    private function get(string $uri): mixed
    {
        $_SERVER['REQUEST_URI'] = $uri;

        return ($this->app)();
    }

    public function testHelloWorld(): void
    {
        $this->assertEquals('hello world', $this->get('/'));
    }

    public function test404(): void
    {
        $this->assertStringContainsString('404', $this->get('/asdlkad8o7'));
    }

    public function testStringParameter(): void
    {
        $this->assertStringContainsString('bort', $this->get('/hello/bort'));
    }

    public function testIntParameter(): void
    {
        foreach ([1 => 2, 0 => 0, 5 => 10, 3 => 6] as $input => $output) {
            $this->assertEquals($output, $this->get("/timestwo/{$input}"));
        }
    }

    public function testMultiIntParameter(): void
    {
        foreach ([[1, 0, 1], [5, -2, 3], [2, 7, 9], [6, 20, 26], [3, -10, -7]] as [$a, $b, $sum]) {
            $this->assertEquals($sum, $this->get("/add/{$a}/{$b}"));
        }
    }

    public function testSubAppHome(): void
    {
        foreach (['/beans', '/beans/'] as $uri) {
            $this->assertEquals('beans', $this->get($uri));
        }
    }

    public function testSubAppPage(): void
    {
        foreach (['/beans/baked', '/beans/baked/'] as $uri) {
            $this->assertEquals('yum', $this->get($uri));
        }
    }

    public function testFloatParameter(): void
    {
        foreach ([[1.2, 23, 0.052173913], [8.3, -1.25, -6.64], [1.176, 4.2, 0.28], [0, 20, 0], [1.6, 2.5, 0.64]] as [$a, $b, $quotient]) {
            $this->assertEqualsWithDelta($quotient, $this->get("/dividefloat/{$a}/{$b}"), 1e-9);
        }
        $this->assertEquals('NaN', $this->get('/dividefloat/3/0'));
    }

    public function testLogging(): void
    {
        foreach (['log', 'info', 'error'] as $type) {
            $pre_lines = count(file($this->log) ?: []);

            $this->app->logger?->$type('message');

            $this->assertCount($pre_lines + 1, file($this->log) ?: []);
            $this->assertMatchesRegularExpression("/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}\t{$type}\tmessage\n/m", (string) file_get_contents($this->log));
        }
    }

    public function testAbortHasName(): void
    {
        $this->assertStringContainsString($this->app->name, $this->app->abort(401, 'error'));
    }

    public function testStaticAbortHasNoName(): void
    {
        $this->assertStringContainsString('App not set, call this function from the app or explicitly pass the $app as the last argument', Ham::_abort(404, 'error'));
    }

    public function testCapturesArriveAsStrings(): void
    {
        $this->app->route('/type/<int>', fn ($app, $n) => get_debug_type($n));

        $this->assertSame('string', $this->get('/type/5'));
    }

    public function testTypedHandlerParameters(): void
    {
        $this->app->route('/typed/<int>/<float>', fn (Ham $app, int $n, float $f) => $n * $f);

        $this->assertEqualsWithDelta(7.5, $this->get('/typed/3/2.5'), 1e-9);
    }

    public function testPathParameter(): void
    {
        $this->app->route('/files/<path>', fn ($app, $path) => $path);

        $this->assertSame('a/b/c.txt', $this->get('/files/a/b/c.txt'));
    }

    public function testQueryStringIsIgnored(): void
    {
        $this->assertSame('hello world', $this->get('/?page=2'));
    }

    public function testSubAppSeesParent(): void
    {
        $this->assertSame('default', $this->get('/beans/whose'));
    }

    public function testMountRequiresSegmentBoundary(): void
    {
        $this->assertStringContainsString('404', $this->get('/beansprout'));
    }

    public function testCannotMountOnItself(): void
    {
        $this->assertFalse($this->app->route('/me', $this->app));
    }

    public function testRoutesAcceptAnyMethodByDefault(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->assertSame('hello world', $this->get('/'));
    }

    public function testExplicitMethodsAreEnforced(): void
    {
        $this->app->route('/submit', fn () => 'submitted', ['post']);

        $this->assertStringContainsString('405', $this->get('/submit'));
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->assertSame('submitted', $this->get('/submit'));
    }

    public function testHeadIsAllowedOnGetRoutes(): void
    {
        $this->app->route('/page', fn () => 'page', ['GET']);
        $_SERVER['REQUEST_METHOD'] = 'HEAD';

        $this->assertSame('page', $this->get('/page'));
    }

    public function testOnError(): void
    {
        $this->app->onError(fn () => 'Burnt Bacon.', 'Page not found.');

        $this->assertSame('Burnt Bacon.', $this->get('/nope'));
        $this->assertStringContainsString("error\tPage not found.", (string) file_get_contents($this->log));
    }

    public function testAppUri(): void
    {
        $this->app->config['APP_URI'] = '/sub/dir';

        $this->assertSame('hello bort', $this->get('/sub/dir/hello/bort'));
        $this->assertSame('hello world', $this->get('/sub/dir'));
    }

    public function testRenderWrapsInLayout(): void
    {
        $this->assertSame('<main>Hello, bort!</main>', $this->app->render('hello.php', ['name' => 'bort']));
    }

    public function testRenderWithoutLayout(): void
    {
        $this->assertSame('Hello, bort!', $this->app->render('hello.php', ['name' => 'bort'], false));
    }

    public function testMissingTemplate(): void
    {
        $this->assertStringContainsString('Template not found', $this->app->partial('missing.php'));
    }

    public function testConfigFromVariables(): void
    {
        $this->app->config_from_file(__DIR__ . '/fixtures/settings.php');

        $this->assertSame(['DEBUG' => true, 'APP_NAME' => 'Testing Application', 'DOMAIN_NAME' => 'localhost'], $this->app->config);
    }

    public function testConfigFromReturnedArray(): void
    {
        $this->app->config_from_file(__DIR__ . '/fixtures/settings_array.php');

        $this->assertSame(['DEBUG' => false], $this->app->config);
    }
}
