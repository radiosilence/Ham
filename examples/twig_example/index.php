<?php
require_once './vendor/autoload.php';

class HamTwig extends Ham {

    private $_twig_env;

    public function __construct($name,$cache=null,$logger=null){
        $this->_twig_env = new Twig_Environment(new Twig_Loader_Filesystem($this->template_paths));
        parent::__construct();
    }

    public function render($view,$data,$layout=null){
        return $this->_twig_env->render($view,$data);
    }
}



$app = new HamTwig('app',false,"logger");


$app->route('/',function() use ($app) {
    $title = "home";
    $content = "hi from the home page";

    return $app->render('home.html',array(
                                        "page_title"=>$title,
                                        "content"=>$content
    ));
});

$app->route('/<string>',function($app,$title){    
    $content = "hi from the $title page";

    return $app->render('home.html',array(
                                        "page_title"=>$title,
                                        "content"=>$content
    ));
});

$app->run();
