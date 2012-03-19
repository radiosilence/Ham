<?php
require 'Ham.php';

$app = ham();
$app->config_from_file('settings.php');
exit();
$app->route('/person/<int:id>', function($app, $id) {
    $person = Person::get($id);
    return Ham::render('person', array(
        'title' => 'Viewing Person',
        'person' => $person
    ));
}, array('GET', 'POST'));

require 'someotherroutes.php';
$app->route('/myotherroute', $myotherroutefunction);

$app->route('/', function($app) {
    return $app->render('derp.php', array(
        'title' => "Whee!"
    ));
});

$app->run();