<?php

namespace Router\Http;

use Closure;
use HttpSoft\Response\HtmlResponse;
use HttpSoft\Response\TextResponse;
use InvalidArgumentException;
use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\ServerRequest;
use React\Socket\SocketServer;
use ReflectionMethod;
use RingCentral\Psr7\Response;
use Twig\Environment;
use Router\Http\Exceptions\InvalidResponse;
use Router\Http\Exceptions\InvalidRoute;

final class Router {
    private array $beforeMiddleware = [];
    private array $afterMiddleware = [];
    private array $routes = [
        Methods::GET->value => [],
        Methods::POST->value => [],
        Methods::PATCH->value => [],
        Methods::DELETE->value => [],
        Methods::HEAD->value => [],
        Methods::OPTIONS->value => [],
        Methods::PUT->value => []
    ];
    private array $E404Handlers = [];
    private array $E500Handlers = [];
    private string $baseRoute = "";
    private Environment $twig;

    /**
     * @param SocketServer $socket
     * @param bool $dev
     */
    public function __construct(private SocketServer $socket, private bool $dev) {
        if ($this->dev) {
            $this->twig = new Environment(new \Twig\Loader\FilesystemLoader(__DIR__ . "/Exceptions/views"));
        }
    }

    public function setBaseRoute(string $baseRoute): self
    {
        $this->baseRoute = $baseRoute;
        return $this;
    }
    
    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @return Router
     */
    public function get(string $pattern, array|Closure $handler): self
    {
        return $this->map([Methods::GET], $pattern, $handler);
    }

    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @return Router
     */
    public function post(string $pattern, array|Closure $handler): self
    {
        return $this->map([Methods::POST], $pattern, $handler);
    }

    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @return Router
     */
    public function head(string $pattern, array|Closure $handler): self
    {
        return $this->map([Methods::HEAD], $pattern, $handler);
    }

    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @return Router
     */
    public function options(string $pattern, array|Closure $handler): self
    {
        return $this->map([Methods::OPTIONS], $pattern, $handler);
    }

    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @return Router
     */
    public function patch(string $pattern, array|Closure $handler): self
    {
        return $this->map([Methods::PATCH], $pattern, $handler);
    }

    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @return Router
     */
    public function delete(string $pattern, array|Closure $handler): self
    {
        return $this->map([Methods::DELETE], $pattern, $handler);
    }

    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @return Router
     */
    public function put(string $pattern, array|Closure $handler): self
    {
        return $this->map([Methods::PUT], $pattern, $handler);
    }

    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @return Router
     */
    public function all(string $pattern, array|Closure $handler): self
    {
        return $this->map([Methods::DELETE, Methods::GET, Methods::HEAD, Methods::OPTIONS, Methods::PATCH, Methods::POST, Methods::PUT] ,$pattern, $handler);
    }

    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @throws InvalidArgumentException 
     * @return self
     */
    public function map404(string $pattern, array|Closure $handler): self
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

        $this->E404Handlers[] = [
            "pattern" => $pattern,
            "fn" => $handler
        ];

        return $this;
    }

    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @throws InvalidArgumentException 
     * @return self
     */
    public function map500(string $pattern, array|Closure $handler): self
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

        $this->E500Handlers[] = [
            "pattern" => $pattern,
            "fn" => $handler
        ];

        return $this;
    }

    /**
     * @param array $methods
     * @param string $pattern
     * @param array|Closure $handler
     * @throws InvalidArgumentException 
     * @return self
     */
    public function map(array $methods, string $pattern, array|Closure $handler): self
    {
        $pattern = $this->baseRoute . '/' . trim($pattern, '/');
        $pattern = $this->baseRoute ? rtrim($pattern, '/') : $pattern;

        if (in_array(Methods::ALL, $methods)) {
            $methods = [
                Methods::DELETE,
                Methods::GET,
                Methods::HEAD,
                Methods::OPTIONS,
                Methods::PATCH,
                Methods::POST,
                Methods::PUT
            ];
        }

        foreach ($methods as $method) {
            if (!$method instanceof Methods) {
                throw new InvalidArgumentException("The methods array must only contain instances of \Router\Http\Methods");
            }

            $this->routes[$method->value][] = [
                "pattern" => $pattern,
                "fn" => $handler
            ];
        }

        return $this;
    }

    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @return Router
     */
    public function beforeMiddleware(string $pattern, array|Closure $handler): self
    {
        $this->beforeMiddleware[] = [
            "pattern" => $pattern,
            "fn" => $handler
        ];

        return $this;
    }

    /**
     * @param string $pattern
     * @param array|Closure $handler
     * @return Router
     */
    public function afterMiddleware(string $pattern, array|Closure $handler): self
    {
        $this->afterMiddleware[] = [
            "pattern" => $pattern,
            "fn" => $handler
        ];

        return $this;
    }

    /**
     * @return void
     */
    public function listen() {
        $http = new \React\Http\HttpServer(function (ServerRequestInterface $request) {
            try {
                return $this->handle($request);
            } catch (\Throwable $e) {
                if ($this->dev) {
                    return new HtmlResponse($this->twig->render("500.html", [
                        "Message" => $e->getMessage(),
                        "StackTrace" => $e->getTrace(),
                        "Main" => [
                            "file" => $e->getFile(),
                            "line" => $e->getLine()
                        ],
                        "Code" => $e->getCode()
                    ]), 500);
                } else {
                    echo "{$e->getMessage()}\n{$e->getTraceAsString()}";

                    $_500handler = $this->getMatchingRoutes($request, $this->E500Handlers, true)[0] ?? null;

                    if (is_null($_500handler)) {
                        return new TextResponse("An internal server error has occurred", TextResponse::STATUS_INTERNAL_SERVER_ERROR);
                    }

                    return $this->invoke($request, (new Response), $_500handler);
                }
            }
        });

        $http->listen($this->socket);
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param array $route
     * @throws LogicException 
     * @throws InvalidResponse 
     * @return ResponseInterface
     */
    private function invoke(ServerRequestInterface $request, ResponseInterface $response, array $route): ResponseInterface
    {
        if (is_callable($route["fn"])) {
            $return = call_user_func($route["fn"], $request, $response, ...$route["params"]);
        } else if (is_array($route["fn"])) {
            $method = new ReflectionMethod($route["fn"][0], $route["fn"][1]);
            
            if (!$method->isStatic()) {
                throw new LogicException("You controller must be a static method");
            }

            $return = $method->invoke(null, $request, $response, ...$route["params"]);
        }

        if (!$return instanceof ResponseInterface) {
            throw new InvalidResponse;
        }

        return $return;
    }

    /**
     * @param ServerRequestInterface $request
     * @throws InvalidRoute 
     * @return ResponseInterface
     */
    private function handle(ServerRequestInterface $request): ResponseInterface
    {
        $beforeMiddleware = $this->getMatchingRoutes($request, $this->beforeMiddleware);
        $targetRoute = $this->getMatchingRoutes($request, $this->routes[$request->getMethod()], true)[0] ?? null;
        $afterMiddleware = $this->getMatchingRoutes($request, $this->afterMiddleware);
        $response = new Response;

        if (is_null($targetRoute)) {
            $_404handler = $this->getMatchingRoutes($request, $this->E404Handlers, true)[0] ?? null;

            if (is_null($_404handler)) {
                throw new InvalidRoute($request->getRequestTarget());
            }

            $response = $this->invoke($request, $response, $_404handler);
        } else {
            foreach ($beforeMiddleware as $route) {
                $response = $this->invoke($request, $response, $route);
            }

            $response = $this->invoke($request, $response, $targetRoute);

            foreach ($afterMiddleware as $route) {
                $response = $this->invoke($request, $response, $route);
            }
        }

        return $response;
    }

    /**
     * @param string $pattern
     * @param string $uri
     * @param array|null $matches
     * @return bool
     */
    private function patternMatches(string $pattern, string $uri, array|null &$matches): bool
    {
      $pattern = preg_replace('/\/{(.*?)}/', '/(.*?)', $pattern);

      return boolval(preg_match_all('#^' . $pattern . '$#', $uri, $matches, PREG_OFFSET_CAPTURE));
    }
    
    /**
     * @param ServerRequest $request
     * @param array $routes
     * @return array
     */
    private function getMatchingRoutes(ServerRequest $request, array $routes, bool $findOne = false): array
    {
        $uri = $request->getRequestTarget();

        $matched = [];

        foreach ($routes as $route) {
            $is_match = $this->patternMatches($route['pattern'], $uri, $matches);

            if ($is_match) {
                $matches = array_slice($matches, 1);

                $params = array_map(function ($match, $index) use ($matches) {
                    if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                        if ($matches[$index + 1][0][1] > -1) {
                            return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                        }
                    }

                    return isset($match[0][0]) && $match[0][1] != -1 ? trim($match[0][0], '/') : null;
                }, $matches, array_keys($matches));

                $route["params"] = $params;
                $matched[] = $route;

                if ($findOne) {
                    break;
                }
            }
        }

        return $matched;
    }
}