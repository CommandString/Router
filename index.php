<?php

require_once "./vendor/autoload.php";

$env = new \CommandString\Router\Environment("./env.example.json"); // default: ./env.json

$env->twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader("/path/to/views"), [
    "cache" => "/path/to/cache"
]);