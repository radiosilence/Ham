ham
===

PHP Microframework for use with Doctrine. Basically just a router, in all fairness. But it's a nice router!

Inspired by Flask.


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