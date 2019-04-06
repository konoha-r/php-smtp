<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Stephan Schmidt <schst@php.net>                             |
// +----------------------------------------------------------------------+

namespace App\Net;
use PEAR;

class Server{

   /**
    * Create a new server
    *
    * Currently two types of servers are supported:
    * - 'sequential', creates a server where one process handles all request from all clients sequentially
    * - 'fork', creates a server where a new process is forked for each client that connects to the server. This only works on *NIX
    *
	* This method will either return a server object or a PEAR error if the server
	* type does not exist.
	*
    * @access public
    * @static
    * @param  string    $type   type of the server
    * @param  string    $host   hostname
    * @param  integer   $port   port
	* @return object Net_Server_Driver  server object of the desired type
	* @throws object PEAR_Error
    */
    public static function create($type, $host, $port)
    {
        if ( !function_exists('socket_create') ) {
            return PEAR::raiseError('Sockets extension not available.');
        }

        $type       = ucfirst(strtolower($type));
        //$driverFile = 'Net/Server/Driver/' . $type . '.php';
        $className  = "App\\Net\\Server\\Driver\\{$type}";

//        if ( !include_once $driverFile ) {
//            return PEAR::raiseError('Unknown server type', NET_SERVER_ERROR_UNKNOWN_DRIVER);
//        }
//        if ( !class_exists("Server\\Driver\\Sequential" )) {
//            die('lost');
//        }

        if ( !class_exists($className )) {
            echo $className."\n";
            return PEAR::raiseError('Driver file is corrupt.', NET_SERVER_ERROR_DRIVER_CORRUPT);
        }

        $server = new $className($host, $port);
        return $server;
    }

}

