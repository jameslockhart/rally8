<?php

/**********
 * Includes
 */
require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__ . '/../vendor/database.php';

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

            a: return $app->redirect('/dashboard/preferences');
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

    a: return $app->redirect('/dashboard/preferences');
    b: return $app->redirect('/dashboard');
});

$app->post('/register/check', function(Request $request) use ($app) {
    init_database($app);
    $sql = "select * from users where username = ?";
    $result = $app['db']->fetchRow($sql, array($request->request->get('username')));

    return empty($result);
});

$app->get('/dashboard/history',function() use($app) {
    if (gate($app)) return gate($app);
    $user_id = (int) $_SESSION['user_id'];
    init_database($app);


    $sql = "select distinct(m.id), u1.username as user1, u2.username as user2, mt.name as activity, m.datetime as datetime from matches m join users u1 on u1.id = m.user_1 join users u2 on u2.id = m.user_2 join meet_types mt on mt.id = m.meet_type_id where user_1 = $user_id OR user_2 = $user_id";
    $stuff = array();
    $result = $app['db']->query($sql);
    while($row = $result->fetch()) {
        $stuff[] = $row;
    }

    return $app['twig']->render('history.twig', array(
      'history' => $stuff
    ));

});

// View preference options.
$app->get('/dashboard/preferences',function(Request $request) use($app) {
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
$app->get('/dashboard/preferences/{id}',function(Request $request, $id) use($app) {
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

$app->get('/dashboard',function() use($app) {
    if (gate($app)) return gate($app);
    init_database($app);
    $ages = array(
      'G' => 'All ages',
      18 => '18-27',
      28 => '28-37',
      38 => '38-47',
      48 => '48-57',
      200 => '58+',
    );
    $age = (isset($_SESSION['pref_age'])) ? $_SESSION['pref_age'] : 'G';

    $genders = array(
        'A' => 'All genders',
        'M' => 'Male',
        'F' => 'Female',
    );
    $gender = (isset($_SESSION['pref_gender'])) ? $_SESSION['pref_gender'] : 'A';

    $sql       = "select * from users where id = ?";
    $user      = $app['db']->fetchAssoc($sql, array($_SESSION['user_id']));

    $sql       = "select * from profiles where user_id = ?";
    $profile   = $app['db']->fetchAssoc($sql, array($_SESSION['user_id']));

    $sql       = "select * from users_meet_types where user_id = ?";
    $user_meet = $app['db']->fetchAssoc($sql, array($_SESSION['user_id']));

    if (!$user_meet) return $app->redirect('/dashboard/preferences');
    $sql       = "select * from meet_types where id = ?";
    $meet_type = $app['db']->fetchAssoc($sql, array($user_meet['meet_type_id']));

    if (isset($_SESSION['pref_age']) && $_SESSION['pref_age'] == 55) $_SESSION['pref_age'] = 200;

    // grab matches for this user
    if (isset($_SESSION['pref_age']))
        $age_bracket = $_SESSION['pref_age'] + 9;

    $sql = "
    select users.id, username, pic_url, bio, liner, interested_in, gender, meet_type_id, age,
      (select count(*) from matches where user_1={$_SESSION['user_id']} and user_2=users.id) as matched
    from users, profiles, users_meet_types
    where users.id=profiles.user_id
    and users.id <> {$_SESSION['user_id']}
    and users_meet_types.user_id=users.id
    and users_meet_types.meet_type_id={$meet_type['id']}".
        (isset($_SESSION['pref_gender']) ? " and gender = '{$_SESSION['pref_gender']}'" : "").
        (isset($_SESSION['pref_age']) ? " and age <= {$age_bracket} and age > {$_SESSION['pref_age']}" : "");

    $result = $app['db']->query($sql);

    $females = $males =  0;

    $matches = array();
    while ($row = $result->fetch()) {
        if ($row['pic_url'] == null) {
            $row['pic_url'] = "default.jpg";
        }
        $matches[] = $row;

        if ($row['gender'] == 'M') $males++;
        elseif ($row['gender'] == 'F') $females++;
    }

    return $app['twig']->render('dashboard.twig', array(
        'user'      => $user,
        'meet_type' => $meet_type,
        'profile'   => $profile,
        'ages'      => $ages,
        'age'       => $age,
        'genders'   => $genders,
        'gender'    => $gender,
        'matches'   => $matches,
        'males'     => $males,
        'females'   => $females,
        'total_matches' => count($matches)
    ));
});

$app->get('/inbox',function() use($app) {
    if (gate($app)) return gate($app);

    init_database($app);
    $sql = "select * from conversations, users, profiles where user_2=users.id and profiles.user_id=user_2 and user_1={$_SESSION['user_id']}";
    $conversations = array();
    $result = $app['db']->query($sql);

    while($row = $result->fetch()) {
        if ($row['pic_url'] == null) {
            $row['pic_url'] = "default.jpg";
        }

        // get the last message FIX THIS AWFUL IDEA FOR THE LOVE OF CHOCOLATE AND ALL THAT IS PRETTY
        $message_sql = "select * from messages where conversation_id=? order by id desc limit 0,1";
        $message = $app['db']->fetchAssoc($message_sql, array($row['id']));

        if (!empty($message)) {
            $message['no_message'] = 0;
            $row['message'] = $message;
        } else {
            $row['message'] = array('message' => 'No messages! what are you waiting for?', 'no_message' => true);
        }
        $conversations[] = $row;
    }

    return $app['twig']->render('inbox.twig', array(
        'conversations' => $conversations
    ));

});

$app->get('/dashboard/profile',function() use($app) {
    if (gate($app)) return gate($app);
    init_database($app);
    $sql     = "select * from profiles where user_id = ?";
    $profile = $app['db']->fetchAssoc($sql, array($_SESSION['user_id']));

    return $app['twig']->render('profile.twig', array(
        'profile' => $profile,
    ));
});



$app->post('/dashboard/profile', function(Request $request) use ($app) {
    if (gate($app)) return gate($app);
    init_database($app);

    $user_id = (int) $_SESSION['user_id'];
    $sql     = "select * from profiles where user_id = ?";
    $orig    = $app['db']->fetchAssoc($sql, array($_SESSION['user_id']));
    $profile = $request->request->get('profile');
    $profile = array_merge($orig, $profile);

    $password  = $request->request->get('password');
    $password2 = $request->request->get('password2');
    if (!empty($password) && !empty($password2)) {
        if ($password == $password2 && strlen($password) > 7) {
            $sql = "update users set password = ? where id = ?";
            $app['db']->executeUpdate($sql, array(hash_password($password), $user_id));
        }
    }

    if (isset($_FILES['photo_upload']['tmp_name'])) {
        $rand = uniqid($user_id . '_');
        $ext = @end(explode(".", $_FILES['photo_upload']['name']));
        if ($ext == "jpg" || $ext == "jpeg" || $ext == "png" || $ext == "gif") {
            $uploadfile = __DIR__ . "/images/users/$rand.$ext";
            move_uploaded_file($_FILES['photo_upload']['tmp_name'], $uploadfile);
            $profile['pic_url'] = "$rand.$ext";
        }
    }


    $sql = "update profiles set pic_url = ?, bio = ?, liner = ?, email = ?, gender = ?, age = ? where user_id = ?";
    $app['db']->executeUpdate($sql, array($profile['pic_url'], $profile['bio'], $profile['liner'], $profile['email'], $profile['gender'], $profile['age'], $user_id));

    return $app->redirect('/dashboard/profile');
});

$app->get('/dashboard/{thing}', function($thing) use ($app) {
    if (gate($app)) return gate($app);
    init_database($app);

    switch ($thing) {
        case "M":
            $_SESSION['pref_gender'] = 'M';
            break;
        case "F":
            $_SESSION['pref_gender'] = 'F';
            break;
        case "A":
            unset($_SESSION['pref_gender']);
            break;
        case "G":
            unset($_SESSION['pref_age']);
            break;
        case "18":
        case "23":
        case "28":
        case "35":
        case "46":
        case "55":
            $_SESSION['pref_age'] = (int) $thing;
            break;
    }

    return $app->redirect('/dashboard');
});

$app->get('/mailer', function() use ($app) {
    require_once '../vendor/swiftmailer/swiftmailer/lib/swift_required.php';

    // Create the Transport
    $transport = Swift_SmtpTransport::newInstance('', 25)
        ->setUsername('rally8')
        ->setPassword('a password')
    ;

    // Create the Mailer using your created Transport
    $mailer = Swift_Mailer::newInstance($transport);

    // Create a message
    $message = Swift_Message::newInstance('Wonderful Subject')
        ->setFrom(array('noreply@rally8.com' => 'Rally8'))
        ->setTo(array('lockhart92@gmail.com' => 'James'))
        ->setBody('You should feel good')
    ;

    // Send the message
    $result = $mailer->send($message);
});

$app->get('/invite/{user_id}/{meet_type_id}', function($user_id, $meet_type_id) use ($app) {
    init_database($app);
    $sql = "insert into matches (user_1, user_2, meet_type_id,  datetime) values (?, ?, ?, now())";
    $result1 = $app['db']->executeUpdate($sql, array($_SESSION['user_id'], $user_id, $meet_type_id));
    return $app->redirect('/dashboard');
});

$app->get('/message/{conversation_id}', function($conversation_id) use ($app) {
    init_database($app);

    $sql = "select * from conversations where id = ?";
    $conversation = $app['db']->fetchAssoc($sql, array($conversation_id));

    $sql = "select * from messages where conversation_id={$conversation_id} order by id desc";
    $messages = array();
    $result = $app['db']->query($sql);
    while($row = $result->fetch()) {
        $messages[] = $row;
    }

    $sql = "select * from users,profiles where id = ? and users.id=profiles.user_id";
    $you = $app['db']->fetchAssoc($sql, array($_SESSION['user_id']));

    $sql = "select * from users,profiles where id = ? and users.id=profiles.user_id";
    $them = $app['db']->fetchAssoc($sql, array($conversation['user_2']));

    if ($you['pic_url'] == null) {
        $you['pic_url'] = "default.jpg";
    }
    if ($them['pic_url'] == null) {
        $them['pic_url'] = "default.jpg";
    }

    return $app['twig']->render('messages.twig', array(
        'messages' => $messages,
        'you' => $you,
        'them' => $them,
        'conversation_id' => $conversation_id
    ));
});

$app->post('/sendmessage/{conversation_id}', function(Request $request, $conversation_id) use ($app) {
    init_database($app);
    $message = $request->request->get('message');
    $sql = "insert into messages (message, datetime, conversation_id, user_id) values ('{$message}', now(), {$conversation_id}, {$_SESSION['user_id']})";
    $stuff = array();
    $result = $app['db']->query($sql);

    return $app->redirect('/message/'.$conversation_id);
});

/************
 * Do things!
 */
$app->run();
