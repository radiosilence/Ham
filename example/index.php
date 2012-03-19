<?php
require '../ham/ham.php';

$app = new Ham();
$app->config_from_file('settings.php');

$app->route('/<string>', function($app, $name) {
    return $name;
});

$app->route('/', function($app) {
    return $app->render('hello.html', array(
        'name' => 'world'
    ));
});

$app->run();