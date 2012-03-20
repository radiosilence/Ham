<?php 

require '../ham/ham.php';

// Create our beans sub-app.
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