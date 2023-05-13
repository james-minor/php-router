<?php

require_once 'src/JamesMinor/Routing/Router.php';

$router = new src\JamesMinor\Routing\Router();

$router->get('/articles', function()
{
	echo 'articles homepage! hi!';
});

$router->get('/articles/{slug}', function(array $parameters)
{
	echo 'Matched, article slug: ' . $parameters['slug'];
});

$router->run();
