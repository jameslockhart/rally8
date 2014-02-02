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

$filename = __DIR__.preg_replace('#(\?.*)$#', '', $_SERVER['REQUEST_URI']);
if (php_sapi_name() === 'cli-server' && is_file($filename)) {
    return false;
}

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

        $sql = "select count(*) as count from users_meet_types where user_id = ?";
        $result4 = $app['db']->fetchAssoc($sql, array($result['id']));
        if ($request->request->get('API'))
        {
            return "True";
        }
        else
        {
            if ($result4['count'] == 0) goto a;
            else goto b;

            a: return $app->redirect('/preferences');
            b: return $app->redirect('/dashboard');
        }
    } else
    if ($request->request->get('API'))
    {
        return "False";
    }
    else
    {
        return false; //I know I don't need the braces for one statement after an else, but don't they look a ton neater compared to random dangling else statements?
    }    
});

//RESTful login. Uses the sessions, some sort of token would let mobile users hate themselves less in the future. But this will do for now.
$app->post('/api/login',function(Request $request) use ($app){
    init_database($app);
    $sql="select * from users where username = ? and password = ?";
    $username = $request->request->get('username');
    $password = hash_password($request->request->get('password')); 
    $result = $app['db']->fetchAssoc($sql, array($username, $password));
    if (!empty($result))
    {
        session_start();
        $_SESSION['user_id'] = $result['id'];

    }
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

    $sql = "insert into profiles (user_id, email) values (?, ?)";
    $result3 = $app['db']->executeUpdate($sql, array($result2['id'], $request->request->get('email')));

    $sql = "select count(*) as count from users_meet_types where user_id = ?";
    $result4 = $app['db']->fetchAssoc($sql, $result2['id']);

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

// Set preferences (automatically run on first start).
$app->get('/preferences',function(Request $request) use($app) {
    init_database($app);
    $sql = "select * from meet_types";

    $stuff = array();
    $result = $app['db']->query($sql);
    while($row = $result->fetch()) {
        $stuff[] = $row;
    }

    return $app['twig']->render('preferences.twig', array(
        'auth' => false,
        'types' => $stuff
    ));
});

$app->get('/preferences/{id}',function(Request $request, $id) use($app) {
    session_start()
    $id = (int) $id;
    init_database($app);
    $sql = "insert into profiles (user_id, meet_type_id) values (?, ?)";
    $result = $app['db']->executeUpdate($sql, array($result2['id'], $request->request->get('email')));
});

$app->get('/list',function() use($app) {
    return $app['twig']->render('list.twig');
    //@todo: send list.
});

/************
 * Do things!
 */
$app->run();
