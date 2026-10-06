Ham
===

PHP Microframework for use with whatever you like. Basically just a fast router
with nice syntax, and a cache singleton.

Routes are converted to regex once, when they are registered. Earlier versions
also cached compiled routes and resolved URIs in XCache or APC between requests;
under OPcache, matching a handful of expressions is cheaper than the cache round
trip, so that was removed.

There is also now the ability to mount apps on routes within apps, so one could
make an administrator app, then mount it on the main app at /admin.

Note: PHP already has many of the features that many microframeworks have, such
as session handling, cookies, and templating. An aim of this project is to
encourage the use of native functionality where possible or where it is good,
but make some parts nicer or extend upon them to bring it up to scratch with
the way I like things.

Goals
-----

 * Provide a succinct syntax that means less magic and less code to read
 through and learn, without compromising speed or code length, by using native
 PHP methods and features.
 * Promote a simple, flat way of building applications that don't need
 massive levels of abstraction.
 * Encourage use of excellent third-party libraries such as Doctrine to prevent
 developers writing convoluted, unmaintainable code that people like me have to
 pick up and spend hours poring over just to get an idea of what on earth is
 going on.
 * Define and document development patterns that allow for new developers to
 get up to speed quickly and write new code that isn't hacky.


Inspired entirely by Flask.


Requirements
------------

* PHP 8.5
* APCu or Redis for `$app->cache` (optional; without either it stores nothing)
* Requests pointed at file that you put the app in (eg.
  index.php, or `php -S localhost:8000 index.php` during development).

Version 2 is a drop-in replacement for applications written against version 1:
the classes, methods and configuration format are unchanged. `CHANGELOG.md`
lists the behaviour that was fixed along the way.


Hello World
-----------

```php
require '../ham/ham.php';

$app = new Ham('example');

$app->route('/', function($app) {
    return 'Hello, world!';
});

$app->run();
```


More Interesting Example
------------------------

```php
require '../ham/ham.php';

$app = new Ham('example');
$app->config_from_file('settings.php');

$app->route('/pork', function($app) {
    return "Delicious pork.";
});

$hello = function($app, $name='world') {
    return $app->render('hello.html', array(
        'name' => $name
    ));
};
$app->route('/hello/<string>', $hello);
$app->route('/', $hello);

$app->run();
```

Captures are passed to handlers as strings. Handlers may declare `int` or
`float` parameters and PHP converts them.

Routes answer any request method unless given a list, in which case other
methods get a 405:

```php
$app->route('/submit', function($app) {
    return 'Thanks.';
}, array('POST'));
```

Multiple apps mounted on routes!
--------------------------------

```php
require '../ham/ham.php';

$beans = new Ham('beans');

$beans->route('/', function($app) {
    return "Beans home.";
});

$beans->route('/baked', function($app) {
    return "Yum!";
});

$app = new Ham('example');
$app->route('/', function($app) {
    return "App home.";
});
$app->route('/beans', $beans);
$app->run();
```

Custom Error Handling
---------------------

The handler replaces the 404 page. If a message is given, it is logged as an
error each time.

```php
$app->onError(function($app) {
    return "Burnt Bacon.";
}, "Page not found.");
```

Output of the mounted apps example with this handler:

#### /beans/

Beans home.

#### /beans/baked

Yum!

#### /

App home.

#### /definitely_not_the_page_you_were_looking_for

Burnt Bacon.

Have a gander at the example application for more details.


To-Dos
------

* Nice logging class and logging support with error levels, e-mailing, etc.
* Sanitisation solution.
* CSRF tokens
* Extension API


Extension Ideas
---------------

* Form generation (3rd-party? Phorms)
* ORM integration (most likely Doctrine)
* Auth module (using scrypt or something)
* Admin extension


Development
-----------

```sh
composer install
composer analyse   # PHPStan at max level
composer test      # PHPUnit
```
