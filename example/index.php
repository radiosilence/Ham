<?php 
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