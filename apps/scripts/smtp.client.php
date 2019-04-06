<?php
require_once dirname(__DIR__).'/config/config.php';

use App\Dns;
use App\MailSender;

if ( !class_exists('App\MailSender') ){
    die('class not found');
}else{
    echo "class found \n";
}

//Dns::set_name_servers(['192.168.1.1']);
Dns::set_name_servers(['192.168.0.254']);
$server = new MailSender();
$server->run();

