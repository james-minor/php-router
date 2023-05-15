<?php

namespace JamesMinor\Routing;

use DomainException;
use Stringable;

/**
 * The Router class handles the routing of requested web URIs to their associated callbacks.
 */
class Router
{
	/**
	 * String array of supported HTTP request methods.
	 */
	public const SUPPORTED_HTTP_METHODS = array(
		'GET',
		'POST',
		'PUT',
		'DELETE'
	);

	/**
	 * @var array Array of callbacks that will be called before a route is searched for.
	 */
	private array $beforeRouterMiddleware;

	/**
	 * @var array Array of callbacks that will be called after a route is searched for.
	 */
	private array $afterRouterMiddleware;

	/**
	 * @var array Multidimensional array of URI routes, see <b>$routes</b> documentation for
	 * more information on array schema. Will call each middleware callback <b>before</b> the corresponding route(s)
	 * callback(s).
	 */
	private array $beforeMiddleware;

	/**
	 * @var array Multidimensional array of URI routes, see <b>$routes</b> documentation for
	 * more information on array schema. Will call each middleware callback <b>after</b> the corresponding route(s)
	 * callback(s).
	 */
	private array $afterMiddleware;

	/**
	 * @var array Multidimensional array of URI routes, each entry is an array with the following keys:
	 *
	 * <table>
	 * <tr>
	 * 	<th>Key</th>
	 * 	<th>Description</th>
	 * </tr>
	 * <tr>
	 * 	<td>method</td>
	 * 	<td>The relevant HTTP method for the route.</td>
	 * </tr>
	 * <tr>
	 * 	<td>pattern</td>
	 * 	<td>The route pattern for the URI route.</td>
	 * </tr>
	 * <tr>
	 * 	<td>callback</td>
	 * 	<td>The callable function for the route object.</td>
	 * </tr>
	 * </table>
	 */
	private array $routes;

	/**
	 * @var callable The callback function that gets called when the Router cannot find a
	 * route matching the request URI.
	 */
	private $http404Callback;

	/**
	 * Default constructor for the Router class.
	 */
	public function __construct()
	{
		$this->beforeRouterMiddleware = array();
		$this->afterRouterMiddleware = array();

		$this->beforeMiddleware = array();
		$this->afterMiddleware = array();

		$this->routes = array();

		$this->http404Callback = function()
		{
			echo '<h1>404</h1>';
			echo '<span>Page not found.</span>';
		};
	}

	/**
	 * Adds a route that responds to GET requests.
	 *
	 * Shorthand function for calling <b>$this->map(['GET'], $pattern, $callback);</b>
	 *
	 * @param Stringable|string $pattern The pattern corresponding to the route.
	 * @param callable $callback The route callback function.
	 * @return void
	 */
	public function get(Stringable|string $pattern, callable $callback): void
	{
		$this->addRoute($this->routes, ['GET'], $pattern, $callback);
	}

	/**
	 * Adds a route that responds to POST requests.
	 *
	 * Shorthand function for calling <b>$this->map(['POST'], $pattern, $callback);</b>
	 *
	 * @param Stringable|string $pattern The pattern corresponding to the route.
	 * @param callable $callback The route callback function.
	 * @return void
	 */
	public function post(Stringable|string $pattern, callable $callback): void
	{
		$this->addRoute($this->routes, ['POST'], $pattern, $callback);
	}

	/**
	 * Adds a route that responds to PUT requests.
	 *
	 * Shorthand function for calling <b>$this->map(['PUT'], $pattern, $callback);</b>
	 *
	 * @param Stringable|string $pattern The pattern corresponding to the route.
	 * @param callable $callback The route callback function.
	 * @return void
	 */
	public function put(Stringable|string $pattern, callable $callback): void
	{
		$this->addRoute($this->routes, ['PUT'], $pattern, $callback);
	}

	/**
	 * Adds a route that responds to DELETE requests.
	 *
	 * Shorthand function for calling <b>$this->map(['DELETE'], $pattern, $callback);</b>
	 *
	 * @param Stringable|string $pattern The pattern corresponding to the route.
	 * @param callable $callback The route callback function.
	 * @return void
	 */
	public function delete(Stringable|string $pattern, callable $callback): void
	{
		$this->addRoute($this->routes, ['DELETE'], $pattern, $callback);
	}

	/**
	 * Allows the mapping of multiple HTTP method types to one route.
	 *
	 * @param array $methods Array of HTTP methods to respond to, e.g. ['GET', 'POST'].
	 * @param Stringable|string $pattern The pattern corresponding to the route, e.g. <b>/articles/{slug}</b>.
	 * @param callable $callback The route callback function.
	 * @return void
	 */
	public function map(array $methods, Stringable|string $pattern, callable $callback): void
	{
		$this->addRoute($this->routes, $methods, $pattern, $callback);
	}

	/**
	 * Runs the router over every saved route. <b>NOTE:</b> If multiple route matches are found,
	 * all of them will be called sequentially.
	 *
	 * @return void
	 */
	public function run(): void
	{
		// Calling before router middleware.
		foreach($this->beforeRouterMiddleware as $middleware)
		{
			$middleware();
		}

		// Parsing the requested URI.
		if(!isset($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] == '')
		{
			$requestURI = '/';
		}
		elseif(str_contains($_SERVER['REQUEST_URI'], '?'))
		{
			$requestURI = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?'));
		}
		else
		{
			$requestURI = $_SERVER['REQUEST_URI'];
		}

		// Iterating over each route before middleware.
		$this->iterateOverRouteArray($this->beforeMiddleware, $requestURI);

		// Iterating over each route.
		$foundMatchingRoute = $this->iterateOverRouteArray($this->routes, $requestURI);

		// Iterating over each route after middleware.
		$this->iterateOverRouteArray($this->afterMiddleware, $requestURI);

		// Calling the 404 callback if the requested route does not exist.
		if(!$foundMatchingRoute && is_callable($this->http404Callback))
		{
			http_response_code(404);
			call_user_func($this->http404Callback);
		}

		// Calling after router middleware.
		foreach($this->afterRouterMiddleware as $middleware)
		{
			$middleware();
		}
	}

	/**
	 * Iterates over an array following the routes array schema, and if any matches are found
	 * calls that routes corresponding callback.
	 *
	 * @param array $routes The array of URI routes.
	 * @param string $requestURI The requested URI from the end-user.
	 * @return bool True if any routes were found, otherwise returns false.
	 */
	private function iterateOverRouteArray(array $routes, string $requestURI): bool
	{
		// Validating that the request method is not null.
		if(!isset($_SERVER['REQUEST_METHOD']))
		{
			$requestMethod = 'GET';
		}
		else
		{
			$requestMethod = $_SERVER['REQUEST_METHOD'];
		}

		// Iterating over each route, checking for matches.
		$foundMatchingRoute = false;
		foreach($routes as $route)
		{
			// Checking if the route request method matches the server request method.
			if($route['method'] !== $requestMethod)
			{
				continue;
			}

			// Checking if the requested URI matches the route pattern.
			if(isset($route['pattern']))
			{
				$patternRegex = $this->convertPatternToRegex($route['pattern']);

				if(!preg_match($patternRegex, $requestURI))
				{
					continue;
				}
				else
				{
					// Calling the route callback (if it exists).
					if(isset($route['callback']) && is_callable($route['callback']))
					{
						$route['callback']($this->getRouteParameterArray($route['pattern'], $requestURI));
						$foundMatchingRoute = true;
					}
				}
			}
		}

		return $foundMatchingRoute;
	}

	/**
	 * Sets the callback function for if the Router cannot find a route.
	 *
	 * @param callable $callback The 404 callback function.
	 * @return void
	 */
	public function setHttp404Callback(callable $callback): void
	{
		$this->http404Callback = $callback;
	}

	/**
	 * Adds a route to an array matching the routes array schema.
	 *
	 * @param array $target The target array to append to.
	 * @param array $methods Array of HTTP methods that the route will respond to.
	 * @param Stringable|string $pattern The pattern corresponding to the route, e.g. <b>/articles/{slug}</b>.
	 * @param callable $callback The route callback function.
	 * @throws DomainException If the passed methods array is empty.
	 * @throws DomainException If the passed methods array contains a non-supported HTTP method.
	 * @return void
	 */
	private function addRoute(array &$target, array $methods, Stringable|string $pattern, callable $callback): void
	{
		// Validating the methods array is not empty.
		if(empty($methods))
		{
			throw new DomainException('Cannot add a route with no methods.');
		}

		// Iterating over each passed method.
		foreach($methods as $method)
		{
			// Checking if the passed method is supported.
			if(!in_array(strtoupper($method), self::SUPPORTED_HTTP_METHODS))
			{
				throw new DomainException('Method "' . $method . '" is not a supported HTTP method type.');
			}

			// Adding the route to the routes array.
			$target[] = array(
				'method' => $method,
				'pattern' => $pattern,
				'callback' => $callback
			);
		}
	}

	/**
	 * Adds middleware that will be called before the router searches for a route.
	 *
	 * @param callable $callback The middleware callback.
	 * @return void
	 */
	public function addBeforeRouterMiddleware(callable $callback): void
	{
		$this->beforeRouterMiddleware[] = $callback;
	}

	/**
	 * Adds middleware that will be called after the router searches for a route.
	 *
	 * @param callable $callback The middleware callback.
	 * @return void
	 */
	public function addAfterRouterMiddleware(callable $callback): void
	{
		$this->afterRouterMiddleware[] = $callback;
	}

	/**
	 * Adds middleware to be called before the corresponding route callback(s).
	 *
	 * @param array $methods Array of HTTP methods to respond to, e.g. ['GET', 'POST'].
	 * @param Stringable|string $pattern The pattern corresponding to the middleware, e.g. <b>/articles/{slug}</b>.
	 * @param callable $callback The middleware callback function.
	 */
	public function addBeforeMiddleware(array $methods, Stringable|string $pattern, callable $callback): void
	{
		$this->addRoute($this->beforeMiddleware, $methods, $pattern, $callback);
	}

	/**
	 * Adds middleware to be called after the corresponding route callback(s).
	 *
	 * @param array $methods Array of HTTP methods to respond to, e.g. ['GET', 'POST'].
	 * @param Stringable|string $pattern The pattern corresponding to the middleware, e.g. <b>/articles/{slug}</b>.
	 * @param callable $callback The middleware callback function.
	 */
	public function addAfterMiddleware(array $methods, Stringable|string $pattern, callable $callback): void
	{
		$this->addRoute($this->afterMiddleware, $methods, $pattern, $callback);
	}

	/**
	 * Converts a passed pattern to a regex string.
	 *
	 * @param Stringable|string $pattern The route pattern to convert to a regular expression.
	 * @return string A regular expression string.
	 */
	private function convertPatternToRegex(Stringable|string $pattern): string
	{
		// Removing the starting and ending slashes, since they cause issues when exploding the pattern.
		if(str_starts_with($pattern, '/'))
		{
			$pattern = substr($pattern, 1);
		}
		if(str_ends_with($pattern, '/'))
		{
			$pattern = substr($pattern, 0, -1);
		}

		// Initializing the regex string.
		$regex = '/^';

		$tokens = explode('/', $pattern);
		foreach($tokens as $token)
		{
			// Checking if the token is a dynamic parameter or a wildcard, or a static route.
			if(str_starts_with($token, '{') && str_ends_with($token, '}'))
			{
				$regex .= '[\w-]+';
			}
			elseif(str_contains($token, '*'))
			{
				$regex .= str_replace('*', '[\w\/-]+', $token);
			}
			else
			{
				$regex .= $token;
			}

			$regex .= '\/';
		}

		$regex .= '?$/';  // Appending the regex suffix.
		return $regex;
	}

	/**
	 * Converts a passed route pattern and request URI to an array of key-value pairs, allowing for
	 * easy access to route parameter values.
	 *
	 * @param Stringable|string $pattern The route pattern.
	 * @param string $requestURI The requested URI.
	 * @return array A one-dimensional associative array where keys are the pattern parameter names,
	 * and values are pulled from the requested URI. <b>Note:</b> If there are no pattern parameters
	 * in the route pattern, will return an empty array.
	 */
	private function getRouteParameterArray(Stringable|string $pattern, string $requestURI): array
	{
		$parameters = array();

		// Iterating over each token in the route pattern to get the parameter keys.
		$tokens = explode('/', $pattern);
		foreach($tokens as $index => $token)
		{
			if(str_starts_with($token, '{') && str_ends_with($token, '}'))
			{
				$parameters[$index] = array(
					'key' => substr($token, 1, -1),
				);
			}
		}

		// Iterating over each token in the requested URI to get the parameter values.
		$tokens = explode('/', $requestURI);
		foreach($tokens as $index => $token)
		{
			if(isset($parameters[$index]))
			{
				$parameters[$index]['value'] = $token;
			}
		}

		// Converting the parameters array into an associative array to return.
		$keyValuePairs = array();
		foreach($parameters as $parameter)
		{
			$keyValuePairs[$parameter['key']] = $parameter['value'];
		}

		return $keyValuePairs;
	}
}