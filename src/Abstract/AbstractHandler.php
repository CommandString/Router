<?php

namespace CommandString\Router\Abstract;

use Psr\Http\Message\ResponseInterface;

abstract class AbstractHandler {
    abstract public function handle(ResponseInterface $response): ResponseInterface;

    final public static function new(): self
    {
        $class_name = get_called_class();
        return new $class_name();
    }
}