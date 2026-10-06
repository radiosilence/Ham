<?php

declare(strict_types=1);

use Ham\App;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require __DIR__ . '/vendor/autoload.php';

final class TwigApp extends App
{
    private Environment $twig {
        get => $this->twig ??= new Environment(new FilesystemLoader($this->templatePaths));
    }

    #[\Override]
    public function render(string $view, array $data = [], string|false|null $layout = null): string
    {
        return $this->twig->render($view, $data);
    }
}

$app = new TwigApp('app')
    ->route('/', fn (App $app) => $app->render('home.html', ['page_title' => 'home', 'content' => 'hi from the home page']))
    ->route('/<string>', fn (App $app, string $title) => $app->render('home.html', ['page_title' => $title, 'content' => "hi from the {$title} page"]));

$app->templatePaths = [__DIR__ . '/templates'];
$app->run();
