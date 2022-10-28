
# commandstring/router

 [![Source](http://img.shields.io/badge/source-commandstring/router-blue.svg?style=flat-square)](https://github.com/commandstring/router) [](https://packagist.org/packages/commandstring/router/stats) [![License](https://img.shields.io/github/license/commandstring/router?style=flat-square)](https://github.com/commandstring/router/blob/master/LICENSE)

A lightweight and simple object oriented PHP Router.

## Table of Contents

- Supports `GET`, `POST`, `PUT`, `DELETE`, `OPTIONS`, `PATCH` and `HEAD` request methods
- [Routing shorthands such as `get()`, `post()`, `put()`, …](#routing-shorthands)
- [Static Route Patterns](#route-patterns)
- Dynamic Route Patterns: [Dynamic PCRE-based Route Patterns](#dynamic-pcre-based-route-patterns) or [Dynamic Placeholder-based Route Patterns](#dynamic-placeholder-based-route-patterns)
- [Optional Route Subpatterns](#optional-route-subpatterns)
- [Supports `X-HTTP-Method-Override` header](#overriding-the-request-method)
- [Subrouting / Mounting Routes](#subrouting--mounting-routes)
- [Allowance of `Class@Method` calls](#classmethod-calls)
- [Custom 404 handling](#custom-404)
- [Before Route Middlewares](#before-route-middlewares)
- [Before Router Middlewares / Before App Middlewares](#before-router-middlewares)
- [After Router Middlewares](#after-router-middlewares)
- [Works fine in subfolders](#subfolder-support)
- [Using Template Libraries](#template-library-integration)
- [Responding to requests](#responding-to-requests)



## Prerequisites/Requirements

- PHP 8.1 or greater
- Composer
- [URL Rewriting](https://gist.github.com/bramus/5332525)



## Installation
```
composer require commandstring/router
```

## Usage

Create an instance of `\CommandString\Router\Router`, define some routes onto it, and run it.

```php
// Require composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Create Router instance
$router = new \CommandString\Router\Router();

// Define routes
// ...

// Run it!
$router->run();
```


### Routing

Hook __routes__ (a combination of one or more HTTP methods and a pattern) using `$router->match(method(s), pattern, function)`:

```php
$router->match('GET|POST', 'pattern', function() { /* ... */ });
```

`commandstring/router` supports `GET`, `POST`, `PUT`, `PATCH`, `DELETE`, `HEAD` _(see [note](#a-note-on-making-head-requests))_, and `OPTIONS` HTTP request methods. Pass in a single request method, or multiple request methods separated by `|`.

When a route matches against the current URL (e.g. `$_SERVER['REQUEST_URI']`), the attached __route handling function__ will be executed. The route handling function must be a [callable](http://php.net/manual/en/language.types.callable.php). Only the first route matched will be handled. When no matching route is found, a 404 handler will be executed.

### Routing Shorthands

Shorthands for single request methods are provided:
```php
$router->get('pattern', function() { /* ... */ });
$router->post('pattern', function() { /* ... */ });
$router->put('pattern', function() { /* ... */ });
$router->delete('pattern', function() { /* ... */ });
$router->options('pattern', function() { /* ... */ });
$router->patch('pattern', function() { /* ... */ });
```

You can use this shorthand for a route that can be accessed using any method:

```php
$router->all('pattern', function() { /* ... */ });
```

Note: Routes must be hooked before `$router->run();` is being called.

Note: There is no shorthand for `match()` as `commandstring/router` will internally re-route such requests to their equivalent `GET` request, in order to comply with RFC2616 _(see [note](#a-note-on-making-head-requests))_.

### Route Patterns

Route Patterns can be static or dynamic:

- __Static Route Patterns__ contain no dynamic parts and must match exactly against the `path` part of the current URL.
- __Dynamic Route Patterns__ contain dynamic parts that can vary per request. The varying parts are named __subpatterns__ and are defined using either Perl-compatible regular expressions (PCRE) or by using __placeholders__

#### Static Route Patterns

A static route pattern is a regular string representing a URI. It will be compared directly against the `path` part of the current URL.

Examples:

-  `/about`
-  `/contact`

Usage Examples:

```php
// This route handling function will only be executed when visiting http(s)://www.example.org/about
$router->get('/about', function($res) {
    $res->getBody()->write("Hello World");
    return $res;
});
```

#### Dynamic PCRE-based Route Patterns

This type of Route Patterns contain dynamic parts which can vary per request. The varying parts are named __subpatterns__ and are defined using regular expressions.

Examples:

- `/movies/(\d+)`
- `/profile/(\w+)`

Commonly used PCRE-based subpatterns within Dynamic Route Patterns are:

- `\d+` = One or more digits (0-9)
- `\w+` = One or more word characters (a-z 0-9 _)
- `[a-z0-9_-]+` = One or more word characters (a-z 0-9 _) and the dash (-)
- `.*` = Any character (including `/`), zero or more
- `[^/]+` = Any character but `/`, one or more

Note: The [PHP PCRE Cheat Sheet](https://courses.cs.washington.edu/courses/cse154/15sp/cheat-sheets/php-regex-cheat-sheet.pdf) might come in handy.

The __subpatterns__ defined in Dynamic PCRE-based Route Patterns are converted to parameters which are passed into the route handling function. Prerequisite is that these subpatterns need to be defined as __parenthesized subpatterns__, which means that they should be wrapped between parens:

```php
// Bad
$router->get('/hello/\w+', function($res, $name) {
    $res->getBody()->write('Hello '.htmlentities($name));
    return $res;
});

// Good
$router->get('/hello/(\w+)', function($res, $name) {
    $res->getBody()->write('Hello '.htmlentities($name));
    return $res;
});
```

Note: The leading `/` at the very beginning of a route pattern is not mandatory, but is recommended.

When multiple subpatterns are defined, the resulting __route handling parameters__ are passed into the route handling function in the order they are defined in:

```php
$router->get('/movies/(\d+)/photos/(\d+)', function($res, $movieId, $photoId) {
    $res->getBody()->write('Movie #'.$movieId.', photo #'.$photoId);
    return $res;
});
```

#### Dynamic Placeholder-based Route Patterns

This type of Route Patterns are the same as __Dynamic PCRE-based Route Patterns__, but with one difference: they don't use regexes to do the pattern matching but they use the more easy __placeholders__ instead. Placeholders are strings surrounded by curly braces, e.g. `{name}`. You don't need to add parens around placeholders.

Examples:

- `/movies/{id}`
- `/profile/{username}`

Placeholders are easier to use than PRCEs, but offer you less control as they internally get translated to a PRCE that matches any character (`.*`).

```php
$router->get('/movies/{movieId}/photos/{photoId}', function($res, $movieId, $photoId) {
    $res->getBody()->write('Movie #'.$movieId.', photo #'.$photoId);
    return $res;
});
```

Note: the name of the placeholder does not need to match with the name of the parameter that is passed into the route handling function:

```php
$router->get('/movies/{foo}/photos/{bar}', function($res, $movieId, $photoId) {
    $res->getBody()->write('Movie #'.$movieId.', photo #'.$photoId);
    return $res;
});
```


### Optional Route Subpatterns

Route subpatterns can be made optional by making the subpatterns optional by adding a `?` after them. Think of blog URLs in the form of `/blog(/year)(/month)(/day)(/slug)`:

```php
$router->get(
	'/blog(/\d+)?(/\d+)?(/\d+)?(/[a-z0-9_-]+)?',
	function($res, $year = null, $month = null, $day = null, $slug = null) {
		if (!$year) { 
			$res->getBody()->write("Blog Overview");
			return $res;
		}
		
		if (!$month) {
			$res->getBody()->write("Blog year overview");
			return $res;
		}
		
		if (!$day) {
			$res->getBody()->write("Blog month overview");
			return $res;
		}
		
		if (!$slug) {
			$res->getBody()->write("Blog day overview");
			return $res;
		}
		
		$res->getBody()->write('Blogpost ' . htmlentities($slug) . ' detail');
		return $res;
	}
);
```

The code snippet above responds to the URLs `/blog`, `/blog/year`, `/blog/year/month`, `/blog/year/month/day`, and `/blog/year/month/day/slug`.

Note: With optional parameters it is important that the leading `/` of the subpatterns is put inside the subpattern itself. Don't forget to set default values for the optional parameters.

The code snipped above unfortunately also responds to URLs like `/blog/foo` and states that the overview needs to be shown - which is incorrect. Optional subpatterns can be made successive by extending the parenthesized subpatterns so that they contain the other optional subpatterns: The pattern should resemble `/blog(/year(/month(/day(/slug))))` instead of the previous `/blog(/year)(/month)(/day)(/slug)`:
```php
$router->get('/blog(/\d+(/\d+(/\d+(/[a-z0-9_-]+)?)?)?)?', function($year = null, $month = null, $day = null, $slug = null) {
    // ...
});
```

Note: It is highly recommended to __always__ define successive optional parameters.

To make things complete use [quantifiers](http://www.php.net/manual/en/regexp.reference.repetition.php) to require the correct amount of numbers in the URL:

```php
$router->get('/blog(/\d{4}(/\d{2}(/\d{2}(/[a-z0-9_-]+)?)?)?)?', function($year = null, $month = null, $day = null, $slug = null) {
    // ...
});
```

### Subrouting / Mounting Routes

Use `$router->mount($baseroute, $fn)` to mount a collection of routes onto a subroute pattern. The subroute pattern is prefixed onto all following routes defined in the scope. e.g. Mounting a callback `$fn` onto `/movies` will prefix `/movies` onto all following routes.

```php
$router->mount('/movies', function() use ($router) {
	// will result in '/movies/'
	$router->get('/', function($res) {
		$res->getBody()->write("Movies overview");
		return $res;
	});

	// will result in '/movies/id'
	$router->get('/(\d+)', function($res) {
		$res->getBody()->write('movie id ' . htmlentities($id));
		return $res;
	});
});
```

Nesting of subroutes is possible, just define a second `$router->mount()` in the callable that's already contained within a preceding `$router->mount()`.


### `Class@Method` calls
We can route to the class action like so:
```php
$router->get('/(\d+)', '\App\Controllers\User@showProfile');
```

When a request matches the specified route URI, the `showProfile` method on the `User` class will be executed. The defined route parameters will be passed to the class method.

The method can be static (recommended) or non-static (not-recommended). In case of a non-static method, a new instance of the class will be created.

If most/all of your handling classes are in one and the same namespace, you can set the default namespace to use on your router instance via `setNamespace()`

```php
$router->setNamespace('\App\Controllers');
$router->get('/users/(\d+)', 'User@showProfile');
$router->get('/cars/(\d+)', 'Car@showProfile');
```

### Custom 404

The default 404 handler sets a 404 status code and exits. You can override this default 404 handler by using `$router->set404(callable);`

```php
$router->set404("/", function(ResponseInterface $res) {
	$res->withStatus(404);
	$res->getBody()->write("HTTP STATUS CODE: 404<br>URI: {$_SERVER['REQUEST_URI']}<br>METHOD: {$_SERVER['REQUEST_METHOD']}");
	return $res;
});
```

You can also define multiple custom routes e.x. you want to define an `/api` route, you can print a custom 404 page:

```php
$router->set404("/api(/.*)?", function() {
	return new JsonResponse(["status" => 404, "status_text" => "Endpoint not defined"], 404);
});
```

`Class@Method` callables are also supported:
```php
$router->set404('\App\Controllers\Error@notFound');
```

The 404 handler will be executed when no route pattern was matched to the current URL.


### Before Route Middlewares

`commandstring/router` supports __Before Route Middlewares__, which are executed before the route handling is processed.

Like route handling functions, you hook a handling function to a combination of one or more HTTP request methods and a specific route pattern.

```php
$router->before("POST", "/api(/*.)?", function() {
	if (!isset($_POST["api_key"])) {
		return new JsonResponse(["error_message", "You must provide an API key!"], 403);
	}
});
```

Unlike route handling functions, more than one before route middleware is executed when more than one route match is found.


### Before Router Middlewares

Before route middlewares are route specific. Using a general route pattern (viz. _all URLs_), they can become __Before Router Middlewares__ _(in other projects sometimes referred to as before app middlewares)_ which are always executed, no matter what the requested URL is.

```php
$router->before('GET', '/.*', function() {
	// ... this will always be executed
});
```

### After Route Middlewares

`commandstring/router` supports __After Route Middlewares__, which are executed after the route handling is processed but before it's emitted.

Like route handling functions, you hook a handling function to a combination of one or more HTTP request methods and a specific route pattern.

```php
$router->before('GET', '/', function() {
	if (!isset($_SESSION['user'])) {
		return new RedirectResponse("/login");
	}
});
```

Similar to before middleware, more than one after route middleware is executed when more than one route match is found.

### After Router Middlewares

After route middlewares are route specific. Using a general route pattern (viz. _all URLs_), they can become **After Router Middlewares** *(in other projects sometimes referred to as after app middlewares)* which are always executed, no matter what the requested URL is.

```php
$router->after('GET', '/.*', function() {
	// ... this will always be executed
});
```

### Overriding the request method

Use `X-HTTP-Method-Override` to override the HTTP Request Method. Only works when the original Request Method is `POST`. Allowed values for `X-HTTP-Method-Override` are `PUT`, `DELETE`, or `PATCH`.


### Subfolder support

Out-of-the box `commandstring/router` will run in any (sub)folder you place it into … no adjustments to your code are needed. You can freely move your _entry script_ `index.php` around, and the router will automatically adapt itself to work relatively from the current folder's path by mounting all routes onto that __basePath__.

Say you have a server hosting the domain `www.example.org` using `public_html/` as its document root, with this little _entry script_ `index.php`:

```php
$router->get('/', function($res) {
	$res->getBody()->write("Hello World!");
	return $res;
});

$router->get('/hello', function() {
	$res->getBody()->write("Hello World!");
	return $res;
});
```
- If your were to place this file _(along with its accompanying `.htaccess` file or the like)_ at the document root level (e.g. `public_html/index.php`), `commandstring/router` will mount all routes onto the domain root (e.g. `/`) and thus respond to `https://www.example.org/` and `https://www.example.org/hello`.

- If you were to move this file _(along with its accompanying `.htaccess` file or the like)_ into a subfolder (e.g. `public_html/demo/index.php`), `commandstring/router` will mount all routes onto the current path (e.g. `/demo`) and thus repsond to `https://www.example.org/demo` and `https://www.example.org/demo/hello`. There's **no** need for `$router->mount(…)` in this case.

#### Disabling subfolder support

In case you **don't** want `commandstring/router` to automatically adapt itself to the folder its being placed in, it's possible to manually override the _basePath_ by calling `setBasePath()`. This is necessary in the _(uncommon)_ situation where your _entry script_ and your _entry URLs_ are not tightly coupled _(e.g. when the entry script is placed into a subfolder that does not need be part of the URLs it responds to)_.
```php
// Override auto base path detection
$router->setBasePath('/');

$router->get('/', function() {
	$res->getBody()->write("Hello World!");
	return $res;
});

$router->get('/hello', function() {
	$res->getBody()->write("Hello World!");
	return $res;
});

$router->run();
```

If you were to place this file into a subfolder (e.g. `public_html/some/sub/folder/index.php`), it will still mount the routes onto the domain root (e.g. `/`) and thus respond to `https://www.example.org/` and `https://www.example.org/hello` _(given that your `.htaccess` file – placed at the document root level – rewrites requests to it)_

## Template library integration

Integrate other libraries with `commandstring/router` by using the Environment class. Bind your template loader to a property in the Env class then use the static method get to retrieve the instance and the property. You can use the HttpSoft's HTML Response class for returning a proper HTML response.
```php
use CommandString\Router\Environment as Env;
use CommandString\Router\Router;
use HttpSoft\Response\HtmlResponse;

$env = new Env(false);
$router = new Router();

$env->twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader("/path/to/views"), [
	"cache" => "/path/to/cache"
]);

$router->get('/', function() {
	return new HtmlResponse(Env::get()->twig->render("index.html"));
});

$router->run();
```

## Responding to requests
All response handlers **MUST** return an instance of `\Psr\Http\Message\ResponseInterface`. You can use the `$response` object passed into each handler *or* instantiate your own. I recommend taking a look at [HttpSoft/Response](https://httpsoft.org/docs/response/v1/#usage) for prebuilt response types.
```php
$response = new HttpSoft\Response\HtmlResponse('<p>HTML</p>');
$response = new HttpSoft\Response\JsonResponse(['key' => 'value']);
$response = new HttpSoft\Response\JsonResponse("{key: 'value'}");
$response = new HttpSoft\Response\TextResponse('Text');
$response = new HttpSoft\Response\XmlResponse('<xmltag>XML</xmltag>');
$response = new HttpSoft\Response\RedirectResponse('https/example.com');
$response = new HttpSoft\Response\EmptyResponse();
```

## Environment Variables
```php
use CommandString\Router\Environment as Env; // You can use the as keyword to change the class name to Env, I'll understand

$env = new Env("/path/to/environment.json"); // default: "./env.json" additionally if you set the parameter to false it will not load any files and instead create a blank stdClass.

$env->mysql->username; // getting defined environment variables
$env->mysql->username = "New Username"; // you cannot change environment variables once they've been defined.

$env->newVariable = "something"; // you can declare new environment variables outside your environment.json
```

## A note on working with PUT
There's no such thing as `$_PUT` in PHP. One must fake it:
```php
$router->put('/movies/(\d+)', function($res, $id) {
	// Fake $_PUT
	$_PUT = array();
	parse_str(file_get_contents('php://input'), $_PUT);

	// ...
});
```

## A note on making HEAD requests
When making `HEAD` requests all output will be buffered to prevent any content trickling into the response body, as defined in [RFC2616 (Hypertext Transfer Protocol -- HTTP/1.1)](http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4):

> The HEAD method is identical to GET except that the server MUST NOT return a message-body in the response. The meta information contained in the HTTP headers in response to a HEAD request SHOULD be identical to the information sent in response to a GET request. This method can be used for obtaining meta information about the entity implied by the request without transferring the entity-body itself. This method is often used for testing hypertext links for validity, accessibility, and recent modification.

To achieve this, `commandstring/router` but will internally re-route `HEAD` requests to their equivalent `GET` request and automatically suppress all output.

## Acknowledgements
`commandstring/router` a fork of `bramus/router` with integration for HttpSoft's PSR-7 interface implementation and emmiter.

## License
`commandstring/router` is released under the MIT public license. See the enclosed `LICENSE` for details.
