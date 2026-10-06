<?php

declare(strict_types=1);

use Ham\App;
use Ham\FileLogger;

require __DIR__ . '/../../vendor/autoload.php';

$beans = new App('beans')
    ->route('/', fn () => 'Beans home.')
    ->route('/baked', fn () => 'Yum!');

$app = new App('example', logger: new FileLogger(__DIR__ . '/app.log'))
    ->configFromFile(__DIR__ . '/settings.php')
    ->configFromFile(__DIR__ . '/settings_local.php')
    ->route('/', function (App $app) {
        $app->logger?->log('Home requested');

        return 'Home.';
    })
    ->route('/hello/<string>', fn (App $app, string $name) => $app->render('hello.php', ['name' => $name]))
    ->route('/beans', $beans)
    ->notFound(fn () => 'Burnt bacon.');

$app->templatePaths = [__DIR__ . '/templates'];
$app->run();
