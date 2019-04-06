<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
namespace App;
use PEAR;

class SmtpServerHandler{

    public $connections;
    public $mailQueue;
    public $debug = 'echo';

    private static $handler;

    public function __construct(){
        $this->mailQueue = MailQueue::getInstance();
    }

    public static function getInstance(){/*{{{*/
        if ( !self::$handler ) { self::$handler = new SmtpServerHandler(); }
        return self::$handler;
    }/*}}}*/

    /**
    * set a reference to the server object
    * 
    * This is done automatically when the handler is passed over to the server
    *
    * @access public
    * @param  object Net_Server_Driver   a reference to the driver object, needed to send data
    *                                    to the clients
    */
    function setServerReference( $server )
    {
        $this->_server = $server;
    }

   /**
    * onStart handler
    *
    * This handler is called, when the server starts.
    *
    * Implement this method to load configuration files.
    *
    * Available in:
    * - Net_Server_Sequential
    * - Net_Server_Fork
    *
    * @access public
    */
    function onStart()
    {
    }

    /**
    * onShutdown handler
    *
    * This handler is called, when the server is stopped.
    *
    * Implement gabage collection in this method, if your server
    * created some temporary files.
    *
    * Available in:
    * - Net_Server_Sequential
    *
    * @access public
    */
    function onShutdown()
    {
    }

    /**
    * onConnect handler
    *
    * This handler is called, when a new client connects. It is
    * called even before the client sent data to the server.
    *
    * You could use this method to send a welcome message to the client.
    *
    * Available in:
    * - Net_Server_Sequential
    * - Net_Server_Fork
    *
    * @access public
    * @param  integer   $clientId   unique id of the client, in Net_Server_Fork, this is always 0
    */
    function onConnect($clientId = 0)
    {
        $clientInfo = $this->_server->getClientInfo($clientId);
        $this->connections[$clientId] = new MailConnection($clientId, $clientInfo);
        $connection_date = date('Ymd H:i:s',$clientInfo['connectOn']);
        $msg = "Accept new connection:{$clientId}"
            ." Host: {$clientInfo['host']}:{$clientInfo['port']} {$connection_date}";
        $this->_debug($msg);
        $this->_server->sendData($clientId, "220 ".APP_MAIL_SERVER_NAME." ESMTP\r\n");
    }

   /**
    * onConnectionRefused handler
    *
    * This handler is called, when a new client tries to connect but is not allowed to.
    *
    * This could happen, if max clients is used. This method currently only may be
    * implemented in Net_Server_Sequential. Will be available in other drivers soon.
    *
    * Available in:
    * - Net_Server_Sequential
    *
    * @access public
    * @param  integer   $clientId   unique id of the client
    */
    function onConnectionRefused($clientId = 0)
    {
    }

   /**
    * onClose handler
    *
    * This handler is called, when a client disconnects from the server.
    *
    * You could implement some garbage collection in this method.
    *
    * Available in:
    * - Net_Server_Sequential
    * - Net_Server_Fork
    *
    * @access public
    * @param  integer   $clientId   unique id of the client, in Net_Server_Fork, this is always 0
    */
    function onClose($clientId = 0)
    {
        if( isset($this->connections[$clientId]) ){
            unset($this->connections[$clientId]);
        }
    }

   /**
    * onReceiveData handler
    *
    * This handler is called, when a client sends data to the server
    *
    * Available in:
    * - Net_Server_Sequential
    * - Net_Server_Fork
    *
    * @access public
    * @param  integer   $clientId   unique id of the client, in Net_Server_Fork, this is always 0
    * @param  string    $data       data that the client sent
    */
    function onReceiveData($clientId = 0, $data = "")
    {
        $connection = $this->connections[$clientId];
        $connection->append($data);
        
        $this->_debug("client:{$clientId}>$data\n");
        
        if ( $connection->availableLineData() ) {
        
            if ( !$connection->isReceivingData() ) {

                $cmd_string  = $connection->readLine();
                $response = $connection->doExec($cmd_string);
                $rs = $this->_server->sendData($clientId, "{$response}\r\n");

                if( PEAR::isError($rs) ){
                    $this->_error($rs->getMessage()." ".__FILE__.__LINE__);
                }
                
            } else {

                $data = $connection->readData();
                if ( $data !== false ) {
                    $mail_from = $connection->mail_from;
                    $rcpts = $connection->rcpt_to_list;
                    $ip = $connection->clientInfo['host'].':'.$connection->clientInfo['port'];
                    
                    $this->_debug("MAIL FROM:<$mail_from>\n"."rcpts: "
                        . join(', ', $rcpts)
                        . "\n-message----\n{$data}\n------");
                    
                    $queue_result = $this->mailQueue->insert($mail_from, $rcpts, $data, $ip);
                    if ( PEAR::isError($queue_result) ){
                        $this->_error($queue_result->getMessage() . " " . __FILE__ . ':' . __LINE__);
                        $this->_server->sendData($clientId, "250 ok " . time() . "\r\n");
                    } else {
                        $this->_server->sendData($clientId, "250 ok " . time() . "\r\n");    
                    }

                    $connection->_init();
                }
            }
            
            $this->_debug($connection->data);
            
            if ( $connection->should_quit() ) {
                $this->_server->closeConnection($clientId);
            }
        }
    }

    private function _debug($string=''){
        switch($this->debug){
            case false:
                return;
            case '1':
            case 'echo':
                echo $string."\n";
                ob_flush();
                break;
            case '2':
            case 'log':
                @error_log(date('Ymd H:i:s')." [debug] $string"."\n",3,APP_RECEIVE_LOG_FILE);
                break;
            default:
        }
    }

    private function _error($string){
        echo "Error: SmtpServerHandler : {$string}\n";
        //@error_log(date('Ymd H:i:s')." [error] $string"."\n",3,APP_RECEIVE_LOG_FILE);
    }

}

