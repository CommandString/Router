<?php

namespace CommandString\Router\Interfaces;

use Psr\Http\Message\ResponseInterface;

abstract class HandlerInterface {
    abstract public function handle(): ResponseInterface;

    public static function new(): self
    {
        $class_name = get_called_class();
        return new $class_name();
    }
}