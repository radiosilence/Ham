<?php

require_once __DIR__ . '/vendor/autoload.php';

class HamTwig extends Ham
{
    private ?\Twig\Environment $twig = null;

    #[\Override]
    public function render(string $view, ?array $data = null, string|false|null $layout = null)
    {
        $this->twig ??= new \Twig\Environment(new \Twig\Loader\FilesystemLoader($this->template_paths));

        return $this->twig->render($view, $data ?? []);
    }
}

$app = new HamTwig('app');
$app->template_paths = [__DIR__ . '/templates'];

$app->route('/', function ($app) {
    return $app->render('home.html', ['page_title' => 'home', 'content' => 'hi from the home page']);
});

$app->route('/<string>', function ($app, $title) {
    return $app->render('home.html', ['page_title' => $title, 'content' => "hi from the {$title} page"]);
});

$app->run();
