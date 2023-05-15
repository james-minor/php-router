<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

use JamesMinor\Routing\Router;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Router::class)]
#[UsesClass(Router::class)]
class RouterTest extends TestCase
{

	public static function validHttpMethodProvider(): array
	{
		return [
			['GET'],
			['POST'],
			['DELETE'],
			['PUT'],
			['get'],
			['post'],
			['delete'],
			['put']
		];
	}

	public static function invalidHttpMethodProvider(): array
	{
		return [
			[''],
			['1234'],
			[1234],
			['delet'],
			['foo'],
			['BAR']
		];
	}

	#[DataProvider('invalidHttpMethodProvider')]
	public function testMappingInvalidRouteMethods(mixed $method)
	{
		$this->expectException(\DomainException::class);

		$router = new Router();
		$router->map([$method], '', function() {});
		$router->run();
	}

	public function testMappingEmptyRouteArray()
	{
		$this->expectException(\DomainException::class);

		$router = new Router();
		$router->map([], '', function() {});
	}

	#[DataProvider('validHttpMethodProvider')]
	public function testMappingValidRouteMethods(string $method)
	{
		$this->expectNotToPerformAssertions();

		$router = new Router();
		$router->map([$method], '', function() {});
		$router->run();
	}

	#[DataProvider('validHttpMethodProvider')]
	public function testAddingRoutes(string $method)
	{
		$this->expectOutputString('test');

		$_SERVER['REQUEST_URI'] = '/';
		$_SERVER['REQUEST_METHOD'] = strtoupper($method);

		$router = new Router();
		$router->{strtolower($method)}('', function()
		{
			echo 'test';
		});
		$router->run();
	}

	public static function routePatternProvider(): array
	{
		return [
			['*.txt', '/test.txt', 200],
			['/articles', '/articles', 200],
			['/articles/{slug}', '/articles/example-slug', 200],
			['/foo/bar/*', '/foo/bar/baz/fizz/buzz', 200],
			['*', '/foo/bar/baz/fizz/buzz', 200],
			['/', '/', 200],
			['articles', '/articles', 200],
			['*.txt', '/test......txt', 404],
			['/articles', '/particles', 404],
			['/foo/bar', '/foo/bar/baz', 404]
		];
	}

	#[DataProvider('routePatternProvider')]
	public function testRoutePatterns(string $pattern, string $requestURI, int $expected)
	{
		$_SERVER['REQUEST_URI'] = $requestURI;

		$router = new Router();
		$router->get($pattern, function() {});
		$router->run();

		$this->assertEquals($expected, http_response_code());
	}

	public function testRunningRouter()
	{
		$this->expectNotToPerformAssertions();

		$router = new Router();
		$router->get('', function() {});
		$router->run();
	}

	public function testNoRouteFoundCallback()
	{
		$this->expectOutputString('<h1>404</h1><span>Page not found.</span>');

		$router = new Router();
		$router->run();
	}

	public function testSetting404Callback()
	{
		$this->expectOutputString('custom callback');

		$router = new Router();
		$router->setHttp404Callback(function()
		{
			echo 'custom callback';
		});
		$router->run();
	}

	public function testAddBeforeRouterMiddleware()
	{
		$this->expectOutputString('before router middleware');

		$_SERVER['REQUEST_URI'] = '/';

		$router = new Router();
		$router->addBeforeRouterMiddleware(function() { echo 'before router middleware'; });
		$router->get('', function() {});
		$router->run();
	}

	public function testAddAfterRouterMiddleware()
	{
		$this->expectOutputString('after router middleware');

		$_SERVER['REQUEST_URI'] = '/';

		$router = new Router();
		$router->addAfterRouterMiddleware(function() { echo 'after router middleware'; });
		$router->get('', function() {});
		$router->run();
	}

	public function testRequestURIWithQueryParameter()
	{
		$this->expectOutputString('test GET page');

		$_SERVER['REQUEST_URI'] = '/?test_parameter=1';

		$router = new Router();
		$router->get('', function()
		{
			echo 'test GET page';
		});
		$router->run();
	}

	public function testAddBeforeMiddleware()
	{
		$this->expectOutputString('before middleware');

		$_SERVER['REQUEST_URI'] = '/';

		$router = new Router();
		$router->addBeforeMiddleware(['GET'], '', function()
		{
			echo 'before middleware';
		});
		$router->get('', function() {});
		$router->run();
	}

	public function testAddAfterMiddleware()
	{
		$this->expectOutputString('after middleware');

		$_SERVER['REQUEST_URI'] = '/';

		$router = new Router();
		$router->addAfterMiddleware(['GET'], '', function()
		{
			echo 'after middleware';
		});
		$router->get('', function() {});
		$router->run();
	}

	public function testGettingRouteParameters()
	{
		$this->expectOutputString('test-slug');

		$_SERVER['REQUEST_URI'] = '/articles/test-slug';

		$router = new Router();
		$router->get('/articles/{slug}', function(array $parameters)
		{
			echo $parameters['slug'];
		});
		$router->run();
	}

	public function testParsingRoutesWithTrailingSlash()
	{
		$this->expectOutputString('articles');

		$_SERVER['REQUEST_URI'] = '/articles/';

		$router = new Router();
		$router->get('/articles/', function()
		{
			echo 'articles';
		});
		$router->run();
	}
}