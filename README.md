# CommandString/Router

A router package built that uses ReactPHP/HTTP under the hood

# Table of Contents

- [Getting Started](#getting-started)
- [Creating routes](#routing)
- [Patterns](#route-patterns)
- [Controllers](#controllers)
- [Middleware](#middleware)
- [Dev Mode](#dev-mode)
- [Nodemon](#nodemon)

## Installation

```
composer require commandstring/router
```

## Getting started
You first need to create a ReactPHP SocketServer

```php
$socket = new \React\Socket\SocketServer("127.0.0.1:8000");
```

Then create a router instance

```php
$router = new \Router\Http\Router($socket, true);
```

The second parameter is whether dev mode should be enabled or not you can read about dev mode [here](#dev-mode)

## Routing

You can then add routes by using the match method

```php
use Router\Http\Methods;

$router->match([Methods::GET], "/", function() { /* ... */ });
```

You can listen for more methods by adding them to the array

```php
$router->match([Methods::GET, Methods::POST], "/", function() { /* ... */ });
```

## Routing Shorthands

Shorthands for single request methods are provided

```php
$router->get('pattern', function() { /* ... */ });
$router->post('pattern', function() { /* ... */ });
$router->put('pattern', function() { /* ... */ });
$router->delete('pattern', function() { /* ... */ });
$router->options('pattern', function() { /* ... */ });
$router->patch('pattern', function() { /* ... */ });
$router->head('pattern', function() { /* ... */ });
```

You can use this shorthand for a route that can be accessed using any method:

```php
$router->all('pattern', function() { /* ... */ });
```

# Route Patterns

Route Patterns can be static or dynamic:

- __Static Route Patterns__ contain no dynamic parts and must match exactly against the `path` part of the current URL.
- __Dynamic Route Patterns__ contain dynamic parts that can vary per request. The varying parts are named __subpatterns__ and are defined using either Perl-compatible regular expressions (PCRE) or by using __placeholders__

## Static Route Patterns

A static route pattern is a regular string representing a URI. It will be compared directly against the `path` part of the current URL.

Examples:

-  `/about`
-  `/contact`

Usage Examples:

```php
$router->get('/about', function($req, $res) {
    $res->getBody()->write("Hello World");
    return $res;
});
```

## Dynamic PCRE-based Route Patterns

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
$router->get('/hello/\w+', function($req, $res, $name) {
    $res->getBody()->write('Hello '.htmlentities($name));
    return $res;
});

// Good
$router->get('/hello/(\w+)', function($req, $res, $name) {
    $res->getBody()->write('Hello '.htmlentities($name));
    return $res;
});
```

Note: The leading `/` at the very beginning of a route pattern is not mandatory, but is recommended.

When multiple subpatterns are defined, the resulting __route handling parameters__ are passed into the route handling function in the order they are defined in:

```php
$router->get('/movies/(\d+)/photos/(\d+)', function($req, $res, $movieId, $photoId) {
    $res->getBody()->write('Movie #'.$movieId.', photo #'.$photoId);
    return $res;
});
```

## Dynamic Placeholder-based Route Patterns

This type of Route Patterns are the same as __Dynamic PCRE-based Route Patterns__, but with one difference: they don't use regexes to do the pattern matching but they use the more easy __placeholders__ instead. Placeholders are strings surrounded by curly braces, e.g. `{name}`. You don't need to add parens around placeholders.

Examples:

- `/movies/{id}`
- `/profile/{username}`

Placeholders are easier to use than PRCEs, but offer you less control as they internally get translated to a PRCE that matches any character (`.*`).

```php
$router->get('/movies/{movieId}/photos/{photoId}', function($req, $res, $movieId, $photoId) {
    $res->getBody()->write('Movie #'.$movieId.', photo #'.$photoId);
    return $res;
});
```

Note: the name of the placeholder does not need to match with the name of the parameter that is passed into the route handling function:

```php
$router->get('/movies/{foo}/photos/{bar}', function($req, $res, $movieId, $photoId) {
    $res->getBody()->write('Movie #'.$movieId.', photo #'.$photoId);
    return $res;
});
```

### Optional Route Subpatterns

Route subpatterns can be made optional by making the subpatterns optional by adding a `?` after them. Think of blog URLs in the form of `/blog(/year)(/month)(/day)(/slug)`:

```php
$router->get(
	'/blog(/\d+)?(/\d+)?(/\d+)?(/[a-z0-9_-]+)?',
	function($req, $res, $year = null, $month = null, $day = null, $slug = null) {
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
$router->get('/blog(/\d+(/\d+(/\d+(/[a-z0-9_-]+)?)?)?)?', function($req, $res, $year = null, $month = null, $day = null, $slug = null) {
    // ...
});
```

Note: It is highly recommended to __always__ define successive optional parameters.

To make things complete use [quantifiers](http://www.php.net/manual/en/regexp.reference.repetition.php) to require the correct amount of numbers in the URL:

```php
$router->get('/blog(/\d{4}(/\d{2}(/\d{2zz}(/[a-z0-9_-]+)?)?)?)?', function($req, $res, $year = null, $month = null, $day = null, $slug = null) {
    // ...
});
```

# Controllers

When defining a route you can either pass an anonymous function or an array that contains a class along with a static method to invoke. Additionally your controller must return an implementation of the PSR7 Response Interface

## Anonymous Function Controller

```php
$router->get("/home", function ($req, $res) {
    $res->getBody()->write("Welcome home!");
    return $res;
});
```

## Class Controller

I have a class with a **static** method, your handler MUST be a static method!

```php
class Home {
	public static function handler($req, $res) {
		$res->getBody()->write("Welcome home!");
		return $res;
	}
}
```

I then replace the anonymous function with an array the first item being the class string and the second key being the name of the static method.

```php
$router->get("/home", [Home::class, "handler"]);
```

## 404 Handler

Defining a 404 handler is **required** and is similar to creating a route. You can also have different 404 pages for different patterns.

To setup a 404 handler you can invoke the map404 method and insert a pattern for the first parameter and then your controller as the second.

```php
$router->map404("/(.*)", function ($req, $res) {
	$res->getBody()->write("{$req->getRequestTarget()} is not a valid route");
	return $res;
});
```

## 500 handler

Defining a 500 handler is **recommended** and is exactly same to mapping a 404 handler.

```php
$router->map500("/(.*)", function ($req, $res) {
	$res->getBody()->write("An error has happened internally :(");
	return $res;
});
```
*Note that when in development mode your 500 error handler will be overrode*

# Middleware

Middleware is software that connects the model and view in an MVC application, facilitating the communication and data flow between these two components while also providing a layer of abstraction, decoupling the model and view and allowing them to interact without needing to know the details of how the other component operates.

A good example is having before middleware that makes sure the user is an administrator before they go to a restricted page. You could do this in your routes controller for every admin page but that would be redundant. Or for after middleware, you may have a REST API that returns a JSON response. You can have after middleware to make sure to make sure the JSON response isn't malformed.

## Before Middleware

You can define before middleware similar to a route by providing a method, pattern, and controller.

```php
use HttpSoft\Response\RedirectResponse;

$router->beforeMiddleware([METHOD::ALL], "/admin?(.*)", function ($req, $res) {
	if (!isAdmin()) {
		return new RedirectResponse("/", 403);
	}

	return $res;
});
```

## After Middleware

The only difference between defining after and before middleware is the method you use.

```php
use HttpSoft\Response\RedirectResponse;

$router->afterMiddleware([METHOD::ALL], "/admin?(.*)", function ($req, $res) {
	if (!isAdmin()) {
		return new RedirectResponse("/", 403);
	}

	return $res;
});
```

# Template Engine Integration

You can use [CommandString/Env](https://github.com/commandstring/env) to store your template engine object in a singleton. Then you can easily get it without trying to pass it around to your controller

```php
use CommandString\Env\Env;

$env = new Env;
$env->twig = new Environment(new \Twig\Loader\FilesystemLoader("/path/to/views"));

// ...

$router->get("/home", function ($req, $res) {
	return new HtmlResponse($env->get("twig")->render("home.html"));\\\
});
```

# Responding to requests
All response handlers **MUST** return an instance of `\Psr\Http\Message\ResponseInterface`. You can use the `$response` object passed into each handler *or* instantiate your own. I recommend taking a look at [HttpSoft/Response](https://httpsoft.org/docs/response/v1/#usage) for prebuilt response types. This is also included with the route as it's used for the dev mode
```php
$response = new HttpSoft\Response\HtmlResponse('<p>HTML</p>');
$response = new HttpSoft\Response\JsonResponse(['key' => 'value']);
$response = new HttpSoft\Response\JsonResponse("{key: 'value'}");
$response = new HttpSoft\Response\TextResponse('Text');
$response = new HttpSoft\Response\XmlResponse('<xmltag>XML</xmltag>');
$response = new HttpSoft\Response\RedirectResponse('https/example.com');
$response = new HttpSoft\Response\EmptyResponse();
```

# Dev Mode

As of now dev mode does one thing. When an exception is thrown on your route it returns the exception with the stack trace as a response rather than dumping it into the console.

# Nodemon
I would recommend using nodemon when developing as it will restart your server with every file change. To install nodemon you'll need nodejs and npm.

`npm install -g nodemon`

then in root of your project directory create a new file named nodemon.json and put the following contents into it
```json
{
    "verbose": false,
    "ignore": [
        ".git",
        ".idea"
    ],
    "execMap": {
        "php": "php"
    },
    "restartable": "r",
    "ext": "php,html,json"
}
```

Afterwards instead of using `php index.php` to start your server use `nodemon index.php` and change a file. You'll see that it says the server is restarting due to a file change. And now you don't have to repeatedly restart the server when you change files! You can also enter r into the console to restart manually if needed!
