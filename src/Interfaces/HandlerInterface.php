<?php

namespace CommandString\Router\Abstract;

use Psr\Http\Message\ResponseInterface;

interface HandlerInterface {
    public function handle(ResponseInterface $response): ResponseInterface;
}