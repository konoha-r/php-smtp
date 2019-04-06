<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace App;

use PEAR;

class MailSender{

    public function __construct(){ }

    //function default_run(){ }

    function run(){/*{{{*/
        echo "MailSender start!!\n";
        ob_flush();

        $mailQueue = MailQueue::getInstance();
        $mailQueue->mailQueue->setBufferSize(20);
        $loop = 0;
        $err_count = 0;

        while ( $mail = $mailQueue->get() ) {
            $loop++;
            if ( $err_count > 5 ) { break; }
            if ( $loop > 10 ) {
                echo "Loop Counter Limit {$loop}\n";
                break;
            }

            if( PEAR::isError($mail) ){
                echo "PEAR error : " , $mail->getMessage() , "\n";
                continue ;
            }

            ob_flush();
            $rs = $mailQueue->sendMail($mail);
            if ( PEAR::isError($rs) ) {
                $err_count++;
                echo "send error {$err_count}\n" . $rs->getMessage() . "\n";
                ob_flush();
            }
        }

        echo "MailSender end!!\n";
        ob_flush();
    }/*}}}*/

    function exec_mailq(){/*{{{*/
        $mailQueue = MailQueue::getInstance();
        $mailQueue->mailQueue->setBufferSize(20);
        $mail = $mailQueue->get();
        var_dump($mail);
    }/*}}}*/

    function test(){/*{{{*/
        echo "MailSender test start!!\n";
        ob_flush();
        $mailQueue = MailQueue::getInstance();
        $mailQueue->mailQueue->setBufferSize(20);
        $loop = 0;
        $mail = $mailQueue->get();
        if ( !$mail ) {
            echo "no mail\n";
            exit;
        }
        //$mailQueue->sendMail()

        $hdrs = $mail->getHeaders();
        $body = $mail->getBody();
        echo mb_convert_encoding($body, 'utf-8', 'iso-2022-jp');
        ob_flush();
    }/*}}}*/

}

