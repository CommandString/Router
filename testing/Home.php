<?php

namespace CommandString\Testing;

use HttpSoft\Response\HtmlResponse as HtmlResponse;
use Psr\Http\Message\ResponseInterface;

class Home extends \CommandString\Router\Interfaces\HandlerInterface {
    public function handle(): ResponseInterface
    {
        $response = new HtmlResponse("<h1>Test</h1>");

        return $response;
    }
}