<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace App;

use PEAR;
use App\Net\Server;

class SmtpServer{
    
    private $type;
    private $host;
    private $port;
    
    public function __construct($host='127.0.0.1', $port=50025, $type='sequential'){
        $this->host = $host;
        $this->port = $port;
        $this->type = $type;
    }
    
    public function run(){/*{{{*/
        $server = Server::create($this->type, $this->host, $this->port);

        if( PEAR::isError($server) ){
            self::crash($pearError);
        }

        $this->showStartMessage();

        $callBackHandler = new SmtpServerHandler();
        $server->setCallbackObject($callBackHandler);
        $server->start();
    }/*}}}*/

    private function showStartMessage(){
        echo "SMTP Server({$this->host}:{$this->port}) start...\n";
    }

    private static function crash($pearError){
        echo "Cause error!!\n";
        echo "Shutdown ...\n";
        echo ">>".$server->getMessage()."\n";
        exit;
    }

}

