# A Modern PHP Router

![[Minimum PHP Version](https://www.php.net)](https://img.shields.io/badge/PHP->%3D8.0-9cf)
![GitHub License](https://img.shields.io/github/license/james-minor/php-router)
![Router File Size](https://img.shields.io/github/size/james-minor/php-router/src/Router.php)

Built with modern PHP 8.0 in mind, this routing library is single-file, 
object-oriented, and built to be as easy as possible for developers to use.

## Features

- Single-page Router class
- Supports HTTP `GET`, `POST`, `PUT`, and `DELETE` requests.
- Contains shorthand methods for each HTTP request type.
- Add named route parameters using `{curly-braces}`.
- Use wildcards in routes using `*`.
- Allows for inserting before and after middleware **per route**.
- Allows for inserting before and after middleware for the **whole router**.
- Easily adding custom 404 callbacks.

## Examples

Creating a simple route for every route in the base directory:
```php
$router = new JamesMinor\Routing\Router();

$router->get('', function()
{
    echo 'Hello easy PHP routing!';
});

$router->run();
```

Accessing named parameters using the passed `$parameters` array:
```php
$router = new JamesMinor\Routing\Router();

$router->get('/articles/{slug}', function(array $parameters)
{
    echo 'Welcome to article with the slug: ' . $parameters['slug'];
});

$router->run();
```

Creating a custom 404 callback:
```php
$router = new JamesMinor\Routing\Router();

$router->setHttp404Callback(function()
{
    echo 'A custom 404 error message!';
});

$router->run();
```

Adding router-wide middleware:
```php
$router = new JamesMinor\Routing\Router();

$router->addBeforeRouterMiddleware(function()
{
    echo 'Look ma!';
});

$router->addAfterRouterMiddleware(function()
{
    echo 'some middleware!';
});

$router->get('', function()
{
    echo ' I found ';
});

$router->run();
```

Adding route-specific middleware:
```php
$router = new JamesMinor\Routing\Router();

$router->addBeforeMiddleware(['GET'], '', function()
{
    echo 'Before route middleware!';
});

$router->addAfterMiddleware(['GET'], '', function()
{
    echo 'After route middleware!';
});

$router->get('', function()
{
    echo 'Hello world!';
});

$router->run();
```

## Funding

I would greatly appreciate if you could [buy me a coffee](https://www.buymeacoffee.com/jamesminor), 
so I can keep making free, open-source projects like this one!
