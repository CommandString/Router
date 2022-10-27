<?php

require_once "./vendor/autoload.php";

use CommandString\Router\Environment as Env;
use CommandString\Router\Router;
use HttpSoft\Response\HtmlResponse;

$env = new Env("./env.example.json");
$router = new Router();

$env->twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader("./views"), [
    "cache" => false
]);

$router->get('/', function() {
	return new HtmlResponse(Env::get()->twig->render("index.html"));
});

$router->run();