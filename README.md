Ham
===

PHP Microframework for use with whatever you like. Basically just a fast router
with nice syntax, and a cache singleton. Will add more things as I go, like
perhaps an extension system, autoloader and some other stuff to make developing
in PHP less irritating than it currently is.

Routes are converted to regex and cached so this process does not need to
happen every request.

Inspired entirely by Flask.


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


Have a gander at the example application for more details.