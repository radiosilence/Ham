Ham
===

PHP Microframework for use with whatever you like. Basically just a fast router
with nice syntax, and a cache singleton. Will add more things as I go, like
perhaps an extension system, autoloader and some other stuff to make developing
in PHP less irritating than it currently is.

Routes are converted to regex and cached so this process does not need to
happen every request. Furthermore, the resolved route for a given URI is also
cached so on most requests thare is no regex matching involved.

PHP presents an interesting challenge because due to it's architecture,
everything has to be re-done each request, which is why I'm leveraging caching
with tiny TTLs to share the results of operations like route resolution
between requests.

Note: PHP already has many of the features that many microframeworks have, such
as session handling, cookies, and templating. An aim of this project is to
encourage the use of native functionality where possible or where it is good,
but make some parts nicer or extend upon them to bring it up to scratch with
the way I like things.

Note: For maximum speed gains, use the XCache extension because that supports
caching of closures, unlike APC.


Goals
-----

 * Make pretty much anything I/O related cached with XCache/APC
(whichever is installed) in order to prevent excessive disk usage or path 
searching on lots of requests.
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

* PHP 5.3
* XCache (preferred) or APC (still optional)
* Requests pointed at file that you put the app in (eg.
  index.php).


Hello World
-----------

    require '../ham/ham.php';

    $app = new Ham();

    $app->route('/', function($app) {
        return 'Hello, world!';
    });

    $app->run();


More Interesting Example
------------------------

    require '../ham/ham.php';

    $app = new Ham();
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


Multiple apps mounted on routes!
--------------------------------

    require '../ham/ham.php';

    // Create our beans sub-app.
    $beans = new Ham();
    $beans->route('/', function($app) {
        return "Beans home.";
    });
    $beans->route('/baked', function($app) {
        return "Yum!";
    });

    $app = new Ham();
    $app->route('/', function($app) {
        return "App home.";
    });
    $app->route('/beans', $beans);
    $app->run();


### /beans/

Beans home.

### /beans/baked

Yum!

### /

App home.


Have a gander at the example application for more details.


To-Dos
------

* Nice logging class and logging support with error levels, e-mailing, etc.
* Sub-application mounting (ala Flask "Blueprints").
* Sanitisation solution.
* CSRF tokens
* Extension API


Extension Ideas
---------------

* Form generation (3rd-party? Phorms)
* ORM integration (most likely Doctrine)
* Auth module (using scrypt or something)
* Admin extension