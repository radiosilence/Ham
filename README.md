Ham
===

A PHP microframework for use with whatever you like, inspired by Flask: a router
with a succinct syntax, mountable sub-applications, plain-PHP templates and a
small cache. PHP already provides sessions, cookies and templating, so Ham leaves
those to the language and only smooths over the parts that are awkward to use
directly.

Requires PHP 8.5. [APCu](https://pecl.php.net/package/APCu) backs the default
cache when installed.

```sh
composer require radiosilence/ham
```


Hello world
-----------

```php
use Ham\App;

require 'vendor/autoload.php';

new App('example')
    ->route('/', fn () => 'Hello, world!')
    ->run();
```

Point every request at this file, for example with `php -S localhost:8000 index.php`
during development or `try_files $uri /index.php` under nginx.


Routing
-------

Handlers receive the app followed by any captures from the pattern. Captures are
cast to their placeholder's type, so handlers can declare typed parameters.

| Placeholder | Matches                          | Passed as |
|-------------|----------------------------------|-----------|
| `<int>`     | `-?\d+`                          | `int`     |
| `<float>`   | `-?\d+(\.\d+)?`                  | `float`   |
| `<string>`  | letters, digits, `_` and `-`     | `string`  |
| `<path>`    | as `<string>`, plus `.` and `/`  | `string`  |

```php
$app->route('/add/<int>/<int>', fn (App $app, int $a, int $b) => $a + $b);
$app->route('/submit', fn () => 'Thanks.', ['POST']);
```

Routes answer `GET` unless given a list of methods. A path that matches a route
under a different method returns 405; an unmatched path returns 404, which
`notFound()` can replace:

```php
$app->notFound(fn (App $app) => 'Burnt bacon.');
```

`abort($code, $message)` and `json($data, $code)` set the status and return a
body, so return their result from the handler.

Patterns are compiled to regular expressions when registered. Earlier versions
cached compiled routes and route lookups in XCache or APC between requests; with
OPcache that costs more than matching a handful of expressions, so it was removed.


Mounting apps
-------------

An app mounted on a route dispatches everything beneath that prefix, and can
reach the app it is mounted on through `$app->parent`. This allows building an
admin app separately and mounting it at `/admin`.

```php
$beans = new App('beans')
    ->route('/', fn () => 'Beans home.')
    ->route('/baked', fn () => 'Yum!');

new App('example')
    ->route('/', fn () => 'App home.')
    ->route('/beans', $beans)
    ->run();
```

If the application is served from a subdirectory, set `$app->basePath` to it.


Templates
---------

Templates are PHP files found in `$app->templatePaths`. `partial()` renders one
with the given data extracted into scope; `render()` additionally wraps the
result in `$app->layout`, which receives it as `$content`.

```php
$app->route('/hello/<string>', fn (App $app, string $name) => $app->render('hello.php', ['name' => $name]));
```

Pass `layout: false` to skip the layout for one render, or set
`$app->layout = false` to disable it everywhere. To use another template engine,
subclass `App` and override `render()`; see `examples/twig_example`.


Configuration
-------------

Configuration files return an array, which is merged into `$app->config`.
`configFromEnv()` reads the file path from an environment variable, so each
deployment can supply its own.

```php
$app->configFromFile(__DIR__ . '/settings.php');
$app->configFromEnv('HAM_SETTINGS');
```


Cache and logging
-----------------

`$app->cache` is an `ApcuCache` namespaced by the app's name when APCu is
enabled, and a `NullCache` that stores nothing otherwise. Pass any `Ham\Cache`
implementation to the constructor to use another backend.

Logging is opt-in: pass a `Ham\Logger`, such as `FileLogger`, which appends
tab-separated lines to a file.

```php
$app = new App('example', logger: new FileLogger(__DIR__ . '/app.log'));
$app->logger?->info('Started.');
```


Development
-----------

```sh
composer install
composer analyse   # PHPStan at max level
composer test      # PHPUnit
```
