<?php
require '../ham/ham.php';

$app = new Ham();
$app->config_from_file('settings.php');

$app->route('/', function($app) {
    return $app->render('derp.php', array(
        'title' => "Whee!"
    ));
});

$app->run();
exit("END");