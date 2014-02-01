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
$app['debug'] = true;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/*******
 * Paths
 */

// "Static" content.
$app->get('/', function() use ($app) {
  return $app['twig']->render('home.twig', array(
    'auth' => FALSE,
  ));
});

// Login/Register/Reset.
$app->get('/login', function() use ($app) {
    return $app['twig']->render('login.twig', array(
        'auth' => FALSE,
    ));
});

$app->post('/login', function(Request $request) use ($app) {
    // Do login. ILL DO IT ~Jamie <3
    initDatabase($app);
    $sql = "select * from users where username = ? and password = ?";
    $result = $app['db']->fetchAssoc($sql, array($request->request->get('username'), $request->request->get('password')));
    return $result;
});

$app->get('/signup',function() use($app) {
	return $app['twig']->render('signup.twig');
});

$app->post('/register', function(Request $request) use ($app) {
    initDatabase($app);
    // @TODO: Save new user account and automatic log in.
});

$app->post('/register/check', function(Request $request) use ($app) {
    initDatabase($app);
    $sql = "select * from users where username = ?";
    $result = $app['db']->fetchRow($sql, array($request->request->get('username')));

    return empty($result);
});

// Set preferences (automatically run on first start).
$app->get('/preferences',function() use($app) {
    return $app['twig']->render('preferences.twig', array(
        'auth' => false
    ));
});

/************
 * Do things!
 */
$app->run();
