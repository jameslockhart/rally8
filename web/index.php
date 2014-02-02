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
  'twig.path' => __DIR__.'/../twigs',
));
$app['debug'] = true;

$filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

/*********
 * Globals
 */

session_start(); // @todo: http://silex.sensiolabs.org/doc/providers/session.html

if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    $app["twig"]->addGlobal("auth", true);
}



/**
 * Simple function to redirect to login if the user isn't logged in.
 * @param $app
 */
function gate(&$app) {
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
        return FALSE;
    } else {
        return $app->redirect('/login');
    }
}

/*******
 * Paths
 */

// "Static" content.
$app->get('/', function() use ($app) {
  return $app['twig']->render('home.twig');
});

$app->get('/login', function(Request $request) use ($app) {
    return $app['twig']->render('login.twig', array(
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
        $_SESSION['user_id'] = $result['id'];

        $sql = "select count(*) as count from users_meet_types where user_id = ?";
        $result4 = $app['db']->fetchAssoc($sql, array($result['id']));
        if ($request->request->get('API')) {
            return "True";
        } else {
            if ($result4['count'] == 0) goto a;
            else goto b;

            a: return $app->redirect('/preferences');
            b: return $app->redirect('/dashboard');
        }
    }
    else return ($request->request->get('API')) ? "False" : false;
});

//RESTful login. Uses the sessions, some sort of token would let mobile users hate themselves less in the future. But this will do for now.
$app->post('/api/login',function(Request $request) use ($app){
    init_database($app);
    $sql="select * from users where username = ? and password = ?";
    $username = $request->request->get('username');
    $password = hash_password($request->request->get('password')); 
    $result = $app['db']->fetchAssoc($sql, array($username, $password));
    if (!empty($result)) $_SESSION['user_id'] = $result['id'];
});

$app->get('/signup',function() use($app) {
	return $app['twig']->render('signup.twig');
});

$app->post('/signup', function(Request $request) use ($app) {
    init_database($app);
    $sql = "insert into users (username, password) values (?, ?)";
    $result1 = $app['db']->executeUpdate($sql, array($request->request->get('username'), hash_password($request->request->get('password'))));

    $sql = "select id from users where username = ?";
    $result2 = $app['db']->fetchAssoc($sql, array($request->request->get('username')));

    if (!empty($result2)) $_SESSION['user_id'] = $result2['id'];


    $sql = "insert into profiles (user_id, email) values (?, ?)";
    $result3 = $app['db']->executeUpdate($sql, array($result2['id'], $request->request->get('email')));

    $sql = "select count(*) as count from users_meet_types where user_id = ?";
    $result4 = $app['db']->fetchAssoc($sql, array($result2['id']));

    if ($result4['count'] == 0) goto a;
    else goto b;

    a: return $app->redirect('/preferences');
    b: return $app->redirect('/dashboard');
});

$app->post('/register/check', function(Request $request) use ($app) {
    init_database($app);
    $sql = "select * from users where username = ?";
    $result = $app['db']->fetchRow($sql, array($request->request->get('username')));

    return empty($result);
});

// View preference options.
$app->get('/preferences',function(Request $request) use($app) {
    if (gate($app)) return gate($app);

    init_database($app);
    $sql = "select * from meet_types";

    $stuff = array();
    $result = $app['db']->query($sql);
    while($row = $result->fetch()) {
        $stuff[] = $row;
    }

    return $app['twig']->render('preferences.twig', array(
        'types' => $stuff
    ));
});

// Set preferences.
$app->get('/preferences/{id}',function(Request $request, $id) use($app) {
    if (gate($app)) return gate($app);

    $user_id = (int) $_SESSION['user_id'];
    $meet_type_id = (int) $id;
    init_database($app);
    $sql = "delete from users_meet_types where user_id = ?";
    $app['db']->executeUpdate($sql, array($user_id));
    $sql = "insert into users_meet_types (user_id, meet_type_id) values (?, ?)";
    $result = $app['db']->executeUpdate($sql, array($user_id, $meet_type_id));

    return $app->redirect('/dashboard');
});

$app->get('/list',function() use($app) {
    if (gate($app)) return gate($app);

    return $app['twig']->render('list.twig');
    //@todo: send list.
});

$app->get('/dashboard',function() use($app) {
    if (gate($app)) return gate($app);
    init_database($app);

    $sql       = "select * from users where id = ?";
    $user      = $app['db']->fetchAssoc($sql, array($_SESSION['user_id']));
    $sql       = "select * from profiles where user_id = ?";
    $profile   = $app['db']->fetchAssoc($sql, array($_SESSION['user_id']));
    $sql       = "select * from users_meet_types where user_id = ?";
    $user_meet = $app['db']->fetchAssoc($sql, array($_SESSION['user_id']));
    $sql       = "select * from meet_types where id = ?";
    $meet_type = $app['db']->fetchAssoc($sql, array($user_meet['meet_type_id']));

    return $app['twig']->render('dashboard.twig', array(
        'user' => $user,
        'meet_type' => $meet_type,
        'profile' => $profile,
    ));
    //@todo: send list.
});

/************
 * Do things!
 */
$app->run();
