<?php

/**********
 * Includes
 */
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../vendor/Database.php';

/*********
 * Uses
 */
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/*********
 * App Setup
 */

$app = new Silex\Application();
$app->register(new Silex\Provider\TwigServiceProvider(), array(
  'twig.path' => __DIR__.'/twigs',
));
$app['debug'] = true;

/*******
 * Paths
 */

// "Static" content.
$app->get('/', function() use ($app) {
  return $app['twig']->render('home.twig', array(
    'auth' => FALSE,
  ));
});

$app->get('/login', function(Request $request) use ($app) {
    return $app['twig']->render('login.twig', array(
        'auth' => FALSE,
        'username' => $request->request->get('username')
    ));
});

$app->post('/login', function(Request $request) use ($app) {
    // Do login. ILL DO IT ~Jamie <3
    init_database($app);
    $sql = "select * from users where username = ? and password = ?";
    $username = $request->request->get('username');
    $password = hash_password($request->request->get('password'));

    $result = $app['db']->fetchAssoc($sql, array($username, $password));

    if (!empty($result)) {
        session_start();
        $_SESSION['user_id'] = $result['id'];
        return $app->redirect('/dashboard');
    } else return false;
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
$app->get('/list',function() use($app) {
    return $app['twig']->render('list.twig');
    //@todo: send list.
});

$app->get('/app',function() use($app) {
    return $app['twig']->render('app.twig');
});

/************
 * Do things!
 */
$app->run();
