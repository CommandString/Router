<?php

require_once "../vendor/autoload.php";

use CommandString\Router\Environment as Env;
use CommandString\Router\Router;
use HttpSoft\Response\EmptyResponse;
use HttpSoft\Response\JsonResponse;
use HttpSoft\Response\RedirectResponse;
use HttpSoft\Response\TextResponse;

$env = new Env(false);
$router = new Router();

$env->twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader("./views"), [
    "cache" => false
]);

$router->set404("/api(/.*)?", function() {
	return new JsonResponse(["status" => 404, "status_text" => "{$_SERVER['REQUEST_URI']} endpoint not defined"], 404);
});

$router->before("POST", "/api(/*.)?", function() {
	if (!isset($_POST["api_key"])) {
		return new JsonResponse(["error_message", "You must provide an API key!"], 403);
	}
});

$router->post('/api(/*.)?', function () {
	$res_array = [];
	// do some stuff
	return new JsonResponse($res_array);
});

$router->run();