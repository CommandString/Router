<?php

namespace Router\Http\Exceptions;

class InvalidRoute extends \Exception {
    public function __construct(string $route) {
        parent::__construct("Route $route has not been mapped and no 404 handler exists for this pattern!");
    }
}