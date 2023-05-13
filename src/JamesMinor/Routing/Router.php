<?php

namespace src\JamesMinor\Routing;

use DomainException;
use Stringable;

class Router
{
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
		$this->addRoute(['GET'], $pattern, $callback);
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
		$this->addRoute(['POST'], $pattern, $callback);
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
		$this->addRoute(['PUT'], $pattern, $callback);
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
		$this->addRoute(['DELETE'], $pattern, $callback);
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
		$this->addRoute($methods, $pattern, $callback);
	}

	/**
	 * Runs the router over every saved route. <b>NOTE:</b> If multiple route matches are found,
	 * all of them will be called sequentially.
	 *
	 * @return void
	 */
	public function run(): void
	{
		// Getting the request URI, minus any URL parameters.
		if(str_contains($_SERVER['REQUEST_URI'], '?'))
		{
			$requestURI = substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?'));
		}
		else
		{
			$requestURI = $_SERVER['REQUEST_URI'];
		}

		// Iterating over each route, checking for matches.
		$foundMatchingRoute = false;
		foreach($this->routes as $route)
		{
			// Checking if the route method matches the server request method.
			if($route['method'] !== $_SERVER['REQUEST_METHOD'])
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
			}
			else
			{
				continue;
			}

			// Calling the route callback (if it exists).
			if(isset($route['callback']) && is_callable($route['callback']))
			{
				$route['callback']($this->getRouteParameterArray($route['pattern'], $requestURI));
				$foundMatchingRoute = true;
			}
		}

		// Exiting the function if a route for the requested URI exists.
		if($foundMatchingRoute)
		{
			return;
		}

		// Calling the 404 callback if the requested route does not exist.
		if(is_callable($this->http404Callback))
		{
			http_response_code(404);
			call_user_func($this->http404Callback);
		}
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
	 * Adds a route to the routes array.
	 *
	 * @param array $methods Array of HTTP methods that the route will respond to.
	 * @param Stringable|string $pattern The pattern corresponding to the route, e.g. <b>/articles/{slug}</b>.
	 * @param callable $callback The route callback function.
	 * @return void
	 */
	private function addRoute(array $methods, Stringable|string $pattern, callable $callback): void
	{
		// Array of supported HTTP methods.
		$supportedMethods = array(
			'GET',
			'POST',
			'PUT',
			'DELETE'
		);

		// Iterating over each passed method.
		foreach($methods as $method)
		{
			// Checking if the passed method is supported.
			if(!in_array(strtoupper($method), $supportedMethods))
			{
				throw new DomainException('Method "' . $method . '" is not a supported HTTP method type.');
			}

			// Adding the route to the routes array.
			$this->routes[] = array(
				'method' => $method,
				'pattern' => $pattern,
				'callback' => $callback
			);
		}
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
		$regex .= '\/';

		$tokens = explode('/', $pattern);
		foreach($tokens as $token)
		{
			// Checking if the token is a dynamic parameter or a wildcard, or a static route.
			if(str_starts_with($token, '{') && str_ends_with($token, '}') || $token == '*')
			{
				$regex .= '[\w-]+';
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