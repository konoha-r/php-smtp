<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Base class for all handlers
 *
 * PHP Version 5                                                        |
 * 
 * +----------------------------------------------------------------------+
 * | Copyright (c) 1997-2002 The PHP Group                                |
 * +----------------------------------------------------------------------+
 * | This source file is subject to version 2.0 of the PHP license,       |
 * | that is bundled with this package in the file LICENSE, and is        |
 * | available at through the world-wide-web at                           |
 * | http://www.php.net/license/2_02.txt.                                 |
 * | If you did not receive a copy of the PHP license and are unable to   |
 * | obtain it through the world-wide-web, please send a note to          |
 * | license@php.net so we can mail you a copy immediately.               |
 * +----------------------------------------------------------------------+
 * | Authors: Stephan Schmidt <schst@php.net>                             |
 * +----------------------------------------------------------------------+
 *
 * @category Networking
 * @package  Net_Server
 * @author   Stephan Schmidt <schst@php.net>
 * @license  PHP 2.0
 * @link     http://pear.php.net/package/Net_Server
 */
namespace App\Net\Server;

abstract class Handler{

    protected $_server;

    function setServerReference($server)
    {
        $this->_server = &$server;
    }

    function onStart() { }

    function onShutdown() { }

    function onConnect($clientId = 0) { }

    function onConnectionRefused($clientId = 0) { }

    function onClose($clientId = 0) { }

    function onReceiveData($clientId = 0, $data = "") { }

}
