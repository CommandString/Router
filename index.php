<?php

use CommandString\Router\Router;
use CommandString\Testing\Home;
use HttpSoft\Response\TextResponse;

require "./vendor/autoload.php";

$router = new Router();

$router->get("/use", Home::new());

$router->set404("/users", function () {
    return new TextResponse("test", 404);
});

$router->run();