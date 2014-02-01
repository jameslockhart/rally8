<?php

function init_database(&$app) {
    $host = '127.0.0.1';
    $port = '3306';
    $user = 'root';
    $password = '';
    $database = 'rally8';

    $app->register(new Silex\Provider\DoctrineServiceProvider(), array(
        'db.options' => array(
            'driver' => 'pdo_mysql',
            'dbname' => $database,
            'host' => $host,
            'password' => $password,
            'user' => $user,
            'port' => $port
        ),
    ));
}

function hash_password($password) {
    $salt = '089u fds0[8 f08[ydsa85t$#@^T5423fdsaF$W T^$AGrat43q^lk41yi34kmh,brntwfg bvhdc869435l¬fdtyh=9]p0ui832i   oj';
    return sha1($salt.$password.$salt);
}