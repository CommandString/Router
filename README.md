# CommandString/Router #
A PHP router

## Features
* Supports `GET`, `POST`, `PUT`, `DELETE`, `OPTIONS`, `PATCH` and `HEAD` request methods
* Routing shorthands:

## Usage
Instantiate an instance of \CommandString\Router\Router. Define your routes and invoke the run method.
```php
$router = new \CommandString\Router\Router();

// Define Routes
//...

$router->run();
```

### Routing
Define routes manually with the match method.
```php
$router->match('GET|POST', 'pattern', function() { … });
```
#### Short Hands
```php
$router->get("pattern" , function() { … });
$router->post("pattern" , function() { … });
$router->put("pattern" , function() { … });
$router->delete("pattern" , function() { … });
$router->options("pattern" , function() { … });
$router->patch("pattern" , function() { … });
$router->head("pattern" , function() { … });
```
There is also a shorthand for all.
```php
$router->all("pattern" , function() { … });
```

#### Static Routing
```php
$router->get("/home", function() { … });
```

#### Dynamic PCRE-based Route Patterns
This type of Route Patterns contain dynamic parts which can vary per request. The varying parts are named **subpatterns** and are defined using regular expressions.

Examples:
-   `/movies/(\d+)`
-   `/profile/(\w+)`

```php
$router->get('/movies/(\d+)/photos/(\d+)', function($response, $movieId, $photoId) {
    $response->getBody()->write("Movie #{$movieId} : Photo #{$photoId}");
    return $respones
});
```

Commonly used PCRE-based subpatterns within Dynamic Route Patterns are:
-   `\d+` = One or more digits (0-9)
-   `\w+` = One or more word characters (a-z 0-9 _)
-   `[a-z0-9_-]+` = One or more word characters (a-z 0-9 _) and the dash (-)
-   `.*` = Any character (including `/`), zero or more
-   `[^/]+` = Any character but `/`, one or more

#### Dynamic Placeholder-based Route Patterns

This type of Route Patterns are the same as **Dynamic PCRE-based Route Patterns**, but with one difference: they don't use regexes to do the pattern matching but they use the more easy **placeholders** instead. Placeholders are strings surrounded by curly braces, e.g. `{name}`. You don't need to add parenthesis around placeholders.

Examples:

-   `/movies/{id}`
-   `/profile/{username}`

Placeholders are easier to use than PRCEs, but offer you less control as they internally get translated to a PRCE that matches any character (`.*`).

```php
$router->get('/movies/{movieId}/photos/{photoId}', function($response, $movieId, $photoId) {
    $response->getBody()->write("Movie #{$movieId} : Photo #{$photoId}");
    return $response;
});
```

### Subrouting / Mounting Routes

Use `$router->mount($baseroute, $fn)` to mount a collection of routes onto a subroute pattern. The subroute pattern is prefixed onto all following routes defined in the scope. e.g. Mounting a callback `$fn` onto `/movies` will prefix `/movies` onto all following routes.

```php
$router->mount('/movies', function() use ($router) {
	// will result in '/movies/'
	$router->get('/', function($response) {
		$response->getBody()->write("movies");
		return $response;
	});

	// will result in '/movies/id'
	$router->get('/(\d+)', function($id) {
		$response->getBody()->write('movie id ' . htmlentities($id););
		return $response;
	});
});
```
Nesting of subroutes is possible, just define a second `$router->mount()` in the callable that's already contained within a preceding `$router->mount()`.

## Class Handlers

Extend the `abstract HandlerInterface class`
```php
// User.php
class User extends HandlerInterface {
    public function handle($response, $username = null): ResponseInterface
    {
        $response->getBody()->write("$username");
        return $response;
    }
}

// index.php
$router->get("/users/{username}", User::new());
```
Any uri parameters must have their value default to null to be compatible with the handle method.

## Responding to a request
All response handlers **MUST** return an instance of `\Psr\Http\Message\ResponseInterface`. You can use the `$response` object passed into each handler *or* instantiate your own. You can use [HttpSoft/Response](https://httpsoft.org/docs/response/v1/#usage), which is already included with this library, to create a response.
```php
$response = new HttpSoft\Response\HtmlResponse('<p>HTML</p>');

$response = new HttpSoft\Response\JsonResponse(['key' => 'value']);
$response = new HttpSoft\Response\JsonResponse("{key: 'value'}");

$response = new HttpSoft\Response\TextResponse('Text');

$response = new HttpSoft\Response\XmlResponse('<xmltag>XML</xmltag>');

$response = new HttpSoft\Response\RedirectResponse('https/example.com');

$response = new HttpSoft\Response\EmptyResponse();
```

## Middleware

```php
$router->before("method", "pattern", function ($response) {
	$response->getBody()->write("Hello");
	return $response;
}); // before middleware
$router->after("method", "pattern", function ($response) {
	$response->getBody()->write("World.");
	return $response;
}); // after middleware
```
Note: The ResponseInterface returned from middleware **WILL** be passed into the next piece of routing.

## 404 Handler
```php
$router->set404("pattern", function ($response) {
	$response->getBody()->write("404 :(");
	return $response;
});
```
Note: Middleware **WILL NOT** work if the page 404's

## Environment 
```php
$env = new \CommandString\Router\Environment("/path/to/environment.json"); // default: ./env.json

// Creating environment variables in the script
\CommandString\Router\Environment::get()->variable = "value";

echo \CommandString\Router\Environment::get()->variable; // output: value

\CommandString\Router\Environment::get()->variable = "value2";

echo \CommandString\Router\Environment::get()->variable; // output: value
// you cannot overwrite already existing environment variables
```

## Twig Template
I recommend reading the [Twig documentation](https://twig.symfony.com/doc/3.x/)
```php
use \CommandString\Router\Environment as Env;

$env = new Env();

$env->twig =  new  \Twig\Environment(new  \Twig\Loader\FilesystemLoader("/path/to/views"), [
	"cache"  =>  "/path/to/cache"
]);
```

## Acknowledgements
`\CommandString\Router\Router` is a fork of [Bramus\Router](https://github.com/bramus/router/blob/master/src/Bramus/Router/Router.php). Very glad I found [HttpSoft](https://httpsoft.org/) because I didn't want to try and implement PSR-7 myself.
