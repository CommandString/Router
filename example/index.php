<?php

use HttpSoft\Response\JsonResponse;
use HttpSoft\Response\TextResponse;
use React\Socket\SocketServer;
use Router\Http\Router;

require_once __DIR__ . "/vendor/autoload.php";

class Math {
    public static function addition($request, $response, $number1, $number2) {
        return new TextResponse("$number1 + $number2 = " . $number1 + $number2);
    }

    public static function subtraction($request, $response, $number1, $number2) {
        return new TextResponse("$number1 - $number2 = " . $number1 - $number2);
    }

    public static function multiplication($request, $response, $number1, $number2) {
        return new TextResponse("$number1 * $number2 = " . $number1 * $number2);
    }

    public static function division($request, $response, $number1, $number2) {
        return new TextResponse("$number1 / $number2 = " . $number1 / $number2);
    }
}

$router = new Router(new SocketServer("127.0.0.1:8000"), true);

$router
    ->get("/(\d+)/plus/(\d+)", [Math::class, "addition"])
    ->get("/(\d+)/minus/(\d+)", [Math::class, "subtraction"])
    ->get("/(\d+)/multiply/(\d+)", [Math::class, "multiplication"])
    ->get("/(\d+)/divide/(\d+)", [Math::class, "division"])
    ->map404("/(.*)", function () {
        $routes = [
            "/{number}/plus/{number}",
            "/{number}/minus/{number}",
            "/{number}/multiply/{number}",
            "/{number}/divide/{number}",
        ];

        return new JsonResponse($routes, 404);
    })
    ->map500("/(.*)", function () {
        return new TextResponse("An internal error has occurred :(", 500);
    })
;

$router->listen();

echo "Listening on 127.0.0.1:8000\n";