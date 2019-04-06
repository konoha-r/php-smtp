<?php
require_once dirname(__DIR__).'/config/config.php';
use App\SmtpServer;

$server = new SmtpServer();
$server->run();

