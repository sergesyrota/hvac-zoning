<?php

require_once __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/app.php';
require __DIR__.'/../config/prod.php';
require __DIR__.'/../src/controllers.php';

echo "test\n";
$app['predis']->set('foo', 'bar');
$app['predis']->select(0);
print_r($app['predis']->get('foo'));
