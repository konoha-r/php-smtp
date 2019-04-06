<?php
require_once dirname(dirname(__DIR__)).'/vendor/autoload.php';

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container; 
use Illuminate\Database\Capsule\Manager as DB;

Dotenv::create(dirname(dirname(__DIR__)))->safeLoad();


$capsule = new Capsule();
$capsule->addConnection([
    'driver'    => getenv('DB_DRIVER'),
    'host'      => getenv('DB_HOST'),
    'database'  => getenv('DB_DATABASE'),
    'username'  => getenv('DB_USER'),
    'password'  => getenv('DB_PASSWORD'),
    'charset'   => getenv('DB_CHARSET'),
    'collation' => getenv('DB_COLLATION'),
    'prefix'    => getenv('DB_PREFIX'),
]);

// Set the event dispatcher used by Eloquent models... (optional)
$capsule->setEventDispatcher(new Dispatcher(new Container()));

//use Illuminate\Database\Eloquent\Model as Eloquent;

$capsule->getConnection()->enableQueryLog();
// Make this Capsule instance available globally via static methods... (optional)
$capsule->setAsGlobal();

// Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
$capsule->bootEloquent();

//$events = new Dispatcher;
//$events->listen('illuminate.query', function($query, $bindings, $time, $name)
//{
//
//    log_message('info', $query);
//    log_message('info', "bindings _{\n". var_export($bindings, true) . "\n}\n" );
//    log_message('info', "query-time: {$time}");
//});
//$capsule->setEventDispatcher($events);

//DB::listen(function ($query) {
//    //$name = get_class($query);
//    //log_message('info', $query->sql);
//    //log_message('info', "bindings _{\n". var_export($query->bindings, true) . "\n}\n" );
//    //log_message('info', "query-time: {$query->time}");
//});

