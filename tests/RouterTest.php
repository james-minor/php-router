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

	public static function httpMethodProvider(): array
	{
		return [
			['GET', true],
			['POST', true],
			['DELETE', true],
			['PUT', true],
			['PATCH', true],
			['OPTIONS', true],
			['HEAD', true],
			['get', true],
			['post', true],
			['delete', true],
			['put', true],
			['patch', true],
			['options', true],
			['head', true],
			['', false],
			['1234', false],
			[1234, false],
			['delet', false],
			['foo', false],
			['BAR', false],
			[array(), false],
			[[], false]
		];
	}

	#[DataProvider('httpMethodProvider')]
	public function testMappingMethodRoutes(mixed $method, bool $validMethod)
	{
		if(!$validMethod)
		{
			$this->expectException(\DomainException::class);
		}

		$_SERVER['REQUEST_METHOD'] = $method;

		$router = new Router();
		$router->map([$method], '/', function() {});
		$router->run();

		if($validMethod)
		{
			$this->assertEquals(200, http_response_code());
		}
	}

	public static function shorthandMethodProvider(): array
	{
		return [
			['get', 'GET'],
			['post', 'POST'],
			['delete', 'DELETE'],
			['put', 'PUT'],
			['patch', 'PATCH'],
			['options', 'OPTIONS'],
			['head', 'HEAD'],
			['all', 'GET'],
			['all', 'POST'],
			['all', 'DELETE'],
			['all', 'PUT'],
			['all', 'PATCH'],
			['all', 'OPTIONS'],
			['all', 'HEAD']
		];
	}

	#[DataProvider('shorthandMethodProvider')]
	public function testShorthandMethods(string $shorthandFunction, $httpMethod)
	{
		$_SERVER['REQUEST_METHOD'] = $httpMethod;

		$router = new Router();
		$router->{$shorthandFunction}('/', function() {});
		$router->run();

		$this->assertEquals(200, http_response_code());
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
			['/*', '/foo/bar', 200],
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
		$router = new Router();
		$router->run();

		$this->assertEquals(404, http_response_code());
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