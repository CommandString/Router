<?php

use CommandString\Router\Router;
use CommandString\Testing\Home;

require "./vendor/autoload.php";

$router = new Router();

$router->get("/", Home::new());

$router->run();