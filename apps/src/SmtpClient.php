<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * http://www.puni.net/~mimori/rfc/rfc2821a.txt
 * http://ya.maya.st/mail/lwq.html
 * @see http://ya.maya.st/mail/lwq.html#verp
 */

namespace App;

use PEAR;
use Mail;
use Mail_mime;


class SmtpClient extends Mail {
    
    /**
     * smtp接続切り替え
     */
    var $smtpSwitcher = null;
    
    /**
     * 自動でSMTPコネクションを確立するかどうか
     * @var boolean
     */
    var $auto_connect = false;
    
    /**
     * バウンスメールのアドレス
     * @var string $bounce_mail
     */
    var $bounce_email = "";
    
    /**
     * デーモンメールの配送を遅らせる時間
     * @var int 
     */
    var $bounce_mail_wait_time = 0;

    function __construct($params=array()){
        $this->smtpSwitcher =  new SmtpSwitcher($params);
        $netSmtpFactory = NetSmtpFactory::getInstance();
        $this->smtpSwitcher->netSmtpFactory = $netSmtpFactory;
        $this->bounce_email = getenv('BOUNCE_EMAIL');
    }
    
    function send($recipients, $headers, $body){

        $headerElements = $this->prepareHeaders($headers);
        if ( PEAR::isError($headerElements) ) {
            return $headerElements;
        }
        
        list($from, $text_headers) = $headerElements;
        
        $header_length = strlen($text_headers);
        $append_string = "\r\n\r\n";
        if ( $text_headers[$header_length-1] == "\n" ) {
            $append_string = "\r\n";
        }
        
        /* Since few MTAs are going to allow this header to be forged
        * unless it's in the MAIL FROM: exchange, we'll use
        * Return-Path instead of From: if it's set. */
        if (!empty($headers['Return-Path'])) {
            $from = $headers['Return-Path'];
        }

        if (!isset($from)) { return PEAR::raiseError('No from address given'); }
        
        $recipients = $this->parseRecipients($recipients);
        if (PEAR::isError($recipients)) { return $recipients; }
        
        $count_recipients = count($recipients);//rcpt toの数
        $args['verp'] = @$this->verp;
        $smtps = array();
        $timeout = 60;
        switch($count_recipients){
            case 0:
                return ;
            default:
                foreach( $recipients as $recipient ){
                    //
                    $this->debug("connect to: {$recipient}");
                    $smtp = $this->select_smtp($recipient);
                    if ( !$smtp ) {
                        //smtp接続オブジェクトがない場合
                        $this->debug("smtp connection error: {$recipient}");
                        continue;
                    }
                    
                    if (!is_resource($smtp->_socket->fp)) {
                        if( PEAR::isError($rs = $smtp->connect($timeout,false))  ){
                            $this->raiseError($smtp,'connect',$recipient,$headers['Return-Path'],$rs->getMessage());
                            continue;
                        }
                    }
                    
                    if ( PEAR::isError( $res = $smtp->mailFrom($from, $args)) ){
                        $this->raiseError($smtp,'mail_from',$recipient,$headers['Return-Path'],$text_headers . $append_string . $body);
                        continue;
                    }
                    
                    if ( PEAR::isError( $res = $smtp->rcptTo($recipient)) ) {
                        $this->raiseError($smtp,'rcpt_to',$recipient,$headers['Return-Path'],$text_headers . $append_string . $body);
                        continue;
                    }
                    
                    if ( PEAR::isError( $res = $smtp->data($text_headers . $append_string . $body)) ) {
                        $this->raiseError($smtp,'data',$recipient,$headers['Return-Path'],$text_headers . $append_string . $body);
                        continue;
                    }
                    
                    $smtps[] = $smtp;
                }
        }//switch($count_recipients)
        
        return $count_recipients == count($smtps) ? true : false;
    }
    
    function select_smtp($email){
        return $this->smtpSwitcher->select_by_email($email, $this->auto_connect);
    }
    
    function raiseError($smtp, $cmd, $rcpt_to, $return_path, $original_message=null){
        $response = $smtp->getResponse();
        list($error_code,$error_message) = $response;
        switch($cmd){
            case 'mail_from':
                break;
            case 'rcpt_to':
                switch($error_code){
                    case '550':
                        $_data = $this->create_550_mail($error_code,$rcpt_to,$return_path,$original_message);
                        $this->send_daemon_mail($return_path,$_data);
                        $this->debug("550 error",array('original_message'=>$original_message));
                        break;
                    default :
                }
                break;
            case 'data':
                switch($error_code){
                    case '503'://command outof sequence 503
                        break;
                    case '554'://no valid recipients
                        break;
                    default:
                }
                break;
            default:
                break;
        }

        $this->debug("raise error", [
            'cmd' => $cmd,
            'error_code' => $error_code,
            'error_message' => $error_message,
            'rcpt_to' => $rcpt_to,
            'return_path' => $return_path,
            'org' => $original_message, 
        ]);
    }//raiseError

    function debug($message, $vars=array()){
        echo $message, "\n";
        echo var_export($vars, true), "\n";
//        $logger = Logger::getInstance();
//        $logger->debug($message,$vars);
    }

    function send_daemon_mail($to, $data){
        if( empty($to) || $to == '<>' ){ return false; }
        $mailQueue = MailQueue::getInstance();
        $mailQueue->insert( $this->bounce_email
            , $to
            , $data
            , '127.0.0.1'
            , $this->bounce_mail_wait_time
        );
    }

    function create_bounce_mail($error_code, $error_message, $rcpt_to, $return_path, $original_message){
        $text = <<<EOF
Delivery to the following receipient failed permanently.
    {$rcpt_to}
    
error code:{$error_code}
error message:{$error_message}

----------- original message ------------

EOF;
        $text = mb_convert_encoding($text, "JIS", 'utf-8');
        $hdr = array(
            'From' => $this->mailer_daemon_email,
            'Subject' => "Delivery Status Notification (Failure)",
            'Return-Path' => '<>',
            'To' => $return_path
        );
        
        $subject = mb_encode_mimeheader($hdr['Subject']);
        $subject = str_replace("\x0D\x0A", "\n", $subject);
        $subject = str_replace("\x0D", "\n", $subject);
        $subject = str_replace("\x0A", "\n", $subject);
        $hdr['Subject'] = $subject;
        
        $mime = new Mail_mime();
        $mime->setTXTBody("\n".$text);
        $header = $mime->headers($hdr);
        $body = $mime->get().$original_message;
        return $headerElements."\r\n".$body;
    }

    public function create_550_mail($error_message,$rcpt_to,$return_path,$original_message){
        $text = <<<EOF
Delivery to the following receipient failed permanently.
    {$rcpt_to}

error message:{$error_message}

----------- original message ------------

EOF;
        $text = mb_convert_encoding($text, "JIS", 'utf-8');
        $hdr = array(
            'From' => $this->mailer_daemon_email,
            'Subject' => "Delivery Status Notification (Failure)",
            'Return-Path' => '<>',
            'To' => $return_path
        );

        $subject = mb_encode_mimeheader($hdr['Subject']);
        $subject = str_replace("\x0D\x0A", "\n", $subject);
        $subject = str_replace("\x0D", "\n", $subject);
        $subject = str_replace("\x0A", "\n", $subject);
        $hdr['Subject'] = $subject;
        
        $mime = new Mail_mime();
        $mime->setTXTBody("\n".$text);
        $header = $mime->headers($hdr);
        $body = $mime->get().$original_message;
        
        //$mail = Mail::factory('smtp');
        //$headerElements = $mail->prepareHeaders($header);
        //list($from,$text_headers) = $headerElements;  

        return $headerElements."\r\n".$body;
    }

    public function shutdown(){ }
    
}
