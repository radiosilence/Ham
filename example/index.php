<?php 

require '../ham/ham.php';


$app = new Ham('example');
$app->route('/', function($app) {
    return "Home.";
});

$app->route('/hello/<string>', function($app, $name) {
    return $app->render('hello.html', array(
        'name' => $name
    ));
});
        $app->route('/timestwo/<int>', function($app, $int) {
            return $int * 2;
        });
        $app->route('/add/<int>/<int>', function($app, $a, $b) {
            return $a + $b;
        });
        $app->route('/dividefloat/<float>/<float>', function($app, $a, $b) {
            return $a / $b;
        });

$app->route('/beans', $beans);
$app->run();