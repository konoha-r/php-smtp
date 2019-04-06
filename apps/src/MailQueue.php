<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
namespace App;

use PEAR;
use App\Mail\Queue;

class MailQueue{

    private static $singleton;

    private function __construct($container_options=[], $mail_options=[]){/*{{{*/
        
        $container_options['dsn'] = getenv('DSN');
        $container_options['table'] = 'mail_queue';
        $container_options['type'] = 'db';
        $mail_options['driver'] = 'smtp';
        $mail_options['host'] = 'localhost';
        $mail_options['port']      = 25;
        $mail_options['localhost'] = 'localhost';
        $mail_options['auth']      = false;
        $mail_options['username']  = '';
        $mail_options['password']  = '';
        
        $this->mailQueue = Queue::factory($container_options, $mail_options);
        $this->mailQueue->send_mail = null;
    }/*}}}*/

    public static function getInstance($container_options=array(), $mail_options=array()){
        if ( !self::$singleton ){ 
            self::$singleton = new MailQueue($container_options, $mail_options); 
        }
        return self::$singleton;
    }

    /**
     * @param unknown_type $mail_from
     * @param unknown_type $receipts
     * @param unknown_type $data
     * @param unknown_type $ip
     * @param unknown_type $sec_to_send
     * @param unknown_type $delete_after_send
     * @param unknown_type $id_user
     * @return unknown
     */
    public function insert($mail_from, $receipts, $data, $ip, $sec_to_send=0, $delete_after_send=true, $id_user=MAILQUEUE_SYSTEM){
        
        $time_to_send = date("Y-m-d H:i:s", time() + $sec_to_send);
        $mailArr = MailParser::getSendArray($data);
        if( PEAR::isError($mailArr) ){ return $mailArr; }
        
        return $this->mailQueue->container->put(
            $time_to_send,
            $id_user,
            $ip,
            $mail_from,
            serialize($receipts),
            serialize($mailArr['headers']),
            serialize($mailArr['body']),
            $delete_after_send
        );
    }

    public function sendMail($mail, $set_as_sent=true){
        if( !isset($this->mailQueue->send_mail) ){
            $this->mailQueue->send_mail = new SmtpClient();
        }
        return $this->mailQueue->sendMail($mail, $set_as_sent);
    }

    public function get(){ return $this->mailQueue->get(); }

    public function setBufferSize($size){
        return $this->mailQueue->setBufferSize($size);
    }

}
