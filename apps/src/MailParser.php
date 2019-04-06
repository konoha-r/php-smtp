<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace App;

use Mail;
use Mail_mimeDecode;
use PEAR;

class MailParser
{
    public $params = [
        'include_bodies' => true,
        'decode_bodies'  => true,
        'decode_headers' => true,
        'crlf' => "\n"
    ];
    
    function __construct($config=array()) {
    }

    public static function parse($content){
        $params =  [
            'include_bodies' => true,
            'decode_bodies'  => true,
            'decode_headers' => true,
            'crlf' => "\n"
        ];
        
        $decoder = new Mail_mimeDecode($content);
        $mailInfo = $decoder->decode($params);
        
        $unknowns = [];
        $images = [];
        $mail = [];
        $mail['headers'] = $mailInfo->headers;
        switch(strtolower($mailInfo->ctype_primary)){
            case 'text':
                $mail['body'] = $mailInfo->body;;
                break;
            case 'multipart':
                foreach($mailInfo->parts as $part){
                    switch(strtolower($part->ctype_primary)){
                        case 'text':
                            $mail['body'] = $part->body;
                            break;
                        case 'image':
                            $type = strtolower($part->ctype_secondary);
                            switch($type){
                                case 'jpeg':
                                case 'gif':
                                case 'png':
                                    $images[] = $part;
                                default:
                            }
                            break;
                        default:
                            $unknowns[] = $part;            
                    }
                }
            default:
        }
        
        $mail['images'] = $images;
        $mail['unknowns'] = $unknowns;
        return $mail;
    }

    public static function getSendArray($content){/*{{{*/
        $decoder = new Mail_mimeDecode($content); // MIME分解
        $parts = $decoder->getSendArray();  // それぞれのパーツを格納
        if( PEAR::isError($parts) ){ return $parts; }
        return array( 'recepients' => $parts[0], 'headers' => $parts[1], 'body' => $parts[2]); 
    }/*}}}*/

}

