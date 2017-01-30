<?php

use Silex\Application;
use Silex\Provider\ServiceControllerServiceProvider;

$app = new Application();
$app->register(new Predis\Silex\ClientServiceProvider());
$app->register(new ServiceControllerServiceProvider());

return $app;
