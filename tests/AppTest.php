<?php

declare(strict_types=1);

namespace Ham\Tests;

use Ham\App;
use Ham\FileLogger;
use Ham\NullCache;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class AppTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        $beans = new App('beans', new NullCache())
            ->route('/', fn () => 'beans')
            ->route('/baked', fn () => 'yum')
            ->route('/whose', fn (App $app) => $app->parent?->name);

        $this->app = new App('default', new NullCache())
            ->route('/', fn () => 'hello world')
            ->route('/hello/<string>', fn (App $app, string $name) => "hello {$name}")
            ->route('/timestwo/<int>', fn (App $app, int $n) => $n * 2)
            ->route('/add/<int>/<int>', fn (App $app, int $a, int $b) => $a + $b)
            ->route('/divide/<float>/<float>', fn (App $app, float $a, float $b) => $b == 0 ? 'NaN' : $a / $b)
            ->route('/files/<path>', fn (App $app, string $path) => $path)
            ->route('/submit', fn () => 'submitted', ['post'])
            ->route('/beans', $beans);
        $this->app->templatePaths = [__DIR__ . '/templates'];
    }

    public function testHelloWorld(): void
    {
        $this->assertSame('hello world', $this->app->handle('/'));
    }

    public function testQueryStringIsIgnored(): void
    {
        $this->assertSame('hello world', $this->app->handle('/?page=2'));
    }

    public function testNotFound(): void
    {
        $this->assertStringContainsString('404', $this->app->handle('/asdlkad8o7'));
    }

    public function testCustomNotFound(): void
    {
        $this->app->notFound(fn (App $app) => "Burnt bacon in {$app->name}.");

        $this->assertSame('Burnt bacon in default.', $this->app->handle('/nope'));
    }

    public function testMethodNotAllowed(): void
    {
        $this->assertStringContainsString('405', $this->app->handle('/submit'));
        $this->assertSame('submitted', $this->app->handle('/submit', 'POST'));
    }

    public function testStringParameter(): void
    {
        $this->assertSame('hello bort', $this->app->handle('/hello/bort'));
    }

    #[TestWith([1, '2'])]
    #[TestWith([0, '0'])]
    #[TestWith([-3, '-6'])]
    public function testIntParameter(int $input, string $expected): void
    {
        $this->assertSame($expected, $this->app->handle("/timestwo/{$input}"));
    }

    #[TestWith([1, 0, '1'])]
    #[TestWith([5, -2, '3'])]
    #[TestWith([3, -10, '-7'])]
    public function testMultipleIntParameters(int $a, int $b, string $expected): void
    {
        $this->assertSame($expected, $this->app->handle("/add/{$a}/{$b}"));
    }

    #[TestWith(['1.6', '2.5', 0.64])]
    #[TestWith(['8.3', '-1.25', -6.64])]
    #[TestWith(['0', '20', 0.0])]
    public function testFloatParameters(string $a, string $b, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, (float) $this->app->handle("/divide/{$a}/{$b}"), 1e-9);
    }

    public function testFloatDivisionByZero(): void
    {
        $this->assertSame('NaN', $this->app->handle('/divide/3/0'));
    }

    public function testPathParameter(): void
    {
        $this->assertSame('a/b/c.txt', $this->app->handle('/files/a/b/c.txt'));
    }

    #[TestWith(['/beans', 'beans'])]
    #[TestWith(['/beans/', 'beans'])]
    #[TestWith(['/beans/baked', 'yum'])]
    #[TestWith(['/beans/baked/', 'yum'])]
    #[TestWith(['/beans/whose', 'default'])]
    public function testMountedApp(string $uri, string $expected): void
    {
        $this->assertSame($expected, $this->app->handle($uri));
    }

    public function testMountRequiresSegmentBoundary(): void
    {
        $this->assertStringContainsString('404', $this->app->handle('/beansprout'));
    }

    public function testCannotMountOnItself(): void
    {
        $this->expectException(\LogicException::class);
        $this->app->route('/me', $this->app);
    }

    public function testBasePath(): void
    {
        $this->app->basePath = '/sub/dir/';

        $this->assertSame('hello bort', $this->app->handle('/sub/dir/hello/bort'));
        $this->assertSame('hello world', $this->app->handle('/sub/dir'));
    }

    public function testRenderWrapsInLayout(): void
    {
        $this->assertSame('<main>Hello, <b>bort</b>!</main>', $this->app->render('hello.php', ['name' => '<b>bort</b>']));
    }

    public function testRenderWithoutLayout(): void
    {
        $this->assertSame('Hello, bort!', $this->app->render('hello.php', ['name' => 'bort'], layout: false));
    }

    public function testMissingTemplateThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->app->partial('missing.php');
    }

    public function testJson(): void
    {
        $this->assertSame('{"pork":"delicious"}', $this->app->json(['pork' => 'delicious']));
    }

    public function testAbortEscapesMessage(): void
    {
        $this->assertSame('<h1>401</h1><p>&lt;nope&gt;</p>', $this->app->abort(401, '<nope>'));
    }

    public function testConfigFromFile(): void
    {
        $this->app->configFromFile(__DIR__ . '/config.php');

        $this->assertSame(['DEBUG' => true, 'APP_NAME' => 'Testing Application'], $this->app->config);
    }

    #[TestWith(['log'])]
    #[TestWith(['info'])]
    #[TestWith(['error'])]
    public function testLogging(string $severity): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ham');
        $logger = new FileLogger($file);

        $logger->$severity('message');
        $logger->$severity('again');

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        unlink($file);
        $this->assertCount(2, $lines);
        $this->assertMatchesRegularExpression("/^\\d{4}-\\d{2}-\\d{2} \\d{2}:\\d{2}:\\d{2}\t{$severity}\tmessage$/", $lines[0]);
    }

    public function testUnwritableLogFileThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        new FileLogger('/nonexistent/dir/log.txt');
    }
}
