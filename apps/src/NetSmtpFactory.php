<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace App;

use Net_SMTP;

class NetSmtpFactory {

    private static $instance;
    
    private function __construct(){ }
    
    public static function getInstance(){
        if( !self::$instance ){ self::$instance = new NetSmtpFactory(); }
        return self::$instance;
    }
    
    public function create($host, $port, $localhost='localhost'){
        return $net_smtp = new Net_SMTP($host, $port, $localhost);
    }

}
