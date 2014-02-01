<?php

require_once __DIR__.'/../vendor/autoload.php';

/**********
 * Includes
 */

$app = new Silex\Application();
$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path' => __DIR__.'/twigs',
));

/*******
 * Paths
 */

$app->get('/', function() use ($app) {
  return $app['twig']->render('home.twig', array(
    'auth' => FALSE,
  ));
});

$app->post('/login', function() use ($app) {
    // Do login.
});

$app->post('/register', function() use ($app) {
    // Do register.
});

/************
 * Do things!
 */
$app->run();
