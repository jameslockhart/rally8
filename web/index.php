<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/Database.php';

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

$app->get('/login', function() use ($app) {
    return $app['twig']->render('login.twig', array(
        'auth' => FALSE,
    ));
});

$app->post('/login', function(Request $request) use ($app) {
    // Do login. ILL DO IT ~Jamie <3
    initDatabase($app);
    $sql = "select * from users where username = ? and password = ?";
    $post = $app['db']->fetchAssoc($sql, array($request->request->get('username'), $request->request->get('password')));
    die(print_r($post));
});

$app->get('/signup',function() use($app) {
	return $app['twig']->render('signup.twig');
});
$app->post('/register', function() use ($app) {
    // Do register.
});

$app->post('/register/check', function() use ($app) {
    return FALSE;
});

/************
 * Do things!
 */
$app->run();
