<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
namespace App;

defined('APP_MAIL_SERVER_NAME') ? null : define('APP_MAIL_SERVER_NAME','localhost');


class MailConnection{

    const STAT_0 = 0;
    const STAT_DATA = 1;

	public $clientId;
	public $clientInfo;
	public $data;
	public $stat;

	/**
	 * MAIL From
	 */
	public $mail_from;

	/**
	 * receipents
	 */
	public $rcpt_to_list;

	/**
	 * メールトランザクション完了フラグ
	 */
	public $transaction_end = false;

	/**
	 * 終了フラグ
	 */
	public $_do_quit = false;

	/**
	 * 
	 * false = nodebug
	 * 1 || echo = echo 
	 * 2 || log  = file
	 */
	public $debug = 'echo';

	public function __construct($clientId, $clientInfo=[]){
		$this->clientId = $clientId;
		$this->clientInfo = $clientInfo;
		$this->_init();
	}

	public function _init(){
	    $this->stat = 0;
		$this->mail_from = false;
		$this->rcpt_to_list = [];
		$this->transaction_end = false;
	}

	public function append($data){ $this->data .= $data; }

	/**
	 * readable line data
	 * @return bool
	 */
	function availableLineData(){
		if( $this->stat === self::STAT_DATA ){
			return strpos($this->data, "\r\n.\r\n", 0) === false ? false : true;
		}
		return strpos($this->data, "\r\n", 0) === false ? false : true;
	}
	
	/**
	 * read line from $data
	 * @return string
	 */
	public function readLine(){
		
		$line = '';
		if( ($offset = strpos($this->data, "\r\n", 0)) !== false){
		    $line = substr($this->data,0,$offset);
		    $this->data = substr($this->data,$offset+2);
		    $this->_debug("line:$line");
		    $this->_debug("left>\n{$this->data}");
		    ob_flush();
		}
		
		return $line;
	}

	function doExec($line_string){
	    if ( strlen($line_string) < 4 ) {
	        return $this->_exec_unimplemented($line_string);
	    }
	    
		$cmd = substr($line_string,0,4);
		$cmd = strtoupper($cmd);
		switch($cmd){
		    case 'HELO':
		        $response = $this->_exec_helo($line_string);
		        break;
		    case 'EHLO':
		        $response = $this->_exec_ehlo($line_string);
		        break;
		    case 'MAIL':
		        $response = $this->_exec_mail($line_string);
		        break;
		    case 'RCPT':
		        $response = $this->_exec_rcpt($line_string);
		        break;
		    case 'DATA':
		        $response = $this->_exec_data($line_string);
		        break;
		    case 'QUIT':
		        $response = $this->_exec_quit($line_string);
		        break;
		    case 'REST':
		        $response = $this->_exec_rset($line_string);
		        break;
		    case 'HELP':
		        $response = $this->_exec_help($line_string);
		        break;
		    case 'NOOP';
		        $response = $this->_exec_noop($line_string);
		        break;
		    default:
		        $response = $this->_exec_unimplemented($line_string);
		}
		
		return $response;
	}

	private function _exec_unimplemented($line_string){
	    return '502 unimplemented (#0.0.1)'; 
	}

	function _exec_helo($line_string){
	    return "250 ".APP_MAIL_SERVER_NAME;
	}

	function _exec_ehlo($line_string){
	    return "250-".APP_MAIL_SERVER_NAME."\r\n"
	            ."250-PIPELINING\r\n"
	            ."250-8BITMIME\r\n"
	            ."250 SIZE 0";
	}

	/*
	 * MAIL FROM: <user1@example.com> SIZE=100000
	 */
	function _exec_mail($line_string){
	    if ( preg_match("/^MAIL FROM:[ ]*<(.*)>([ ]+.*)?$/i", $line_string, $matches ) ) {
	        $this->_init();
	        $mail_from = $matches[1];
	        $this->mail_from = $mail_from;
	        return "250 ok";
	    }
	    return $this->_exec_unimplemented($line_string);
	}

	/**
	 * RCPT TO: <user1@example.com> SIZE=100000
	 */
	function _exec_rcpt($line_string){
	    if ( !isset($this->mail_from) || $this->mail_from === false ) {
	        return "503 MAIL FROM before RCPT";
	    }
	    if (preg_match("/^RCPT TO:[ ]*<(.*)>[ ]*$/i",$line_string,$matches) ){
	        $rcpt_to = $matches[1];
	        $this->rcpt_to_list[] = $rcpt_to;
	        return "250 ok";
	    }
	    return $this->_exec_unimplemented($line_string);
	}

	private function _exec_data($line_string){

	    if ( !isset($this->mail_from) || $this->mail_from === false ) {
	        return "503 MAIL FROM before RCPT";
	    }
	    if ( empty($this->rcpt_to_list) ) {
	        return "503 RCPT before DATA";
	    }

	    $this->stat = self::STAT_DATA;
	    return "354 go ahead";
	}

	private function _exec_quit($line_string){
	    $this->_do_quit = true;
	    return "221 ".APP_MAIL_SERVER_NAME;
	}

	private function _exec_rset($line_string){
	    $this->_init();
	    return "250 ok";
	}

	private function _exec_noop($line_string){
	    return "250 ok";
	}

	private function _exec_help($line_string){
	    return "214 https://github.com/konoha-r/php-smtp";
	}

	public function readData(){
	    $data = false;
		if ( ($offset = strpos($this->data, "\r\n.\r\n", 0)) !== false ) {
		    $data = substr($this->data, 0, $offset);
		    $this->data = substr($this->data, $offset + 5);
		    $this->stat = self::STAT_0;
		    $this->transaction_end = true;
		}
		return $data;
	}

	public function resetData(){ $this->data = ''; }

    public function isReceivingData(){ return $this->stat === self::STAT_DATA; }
//	public function isDataStat(){ return $this->stat === self::STAT_DATA; }

	function should_quit(){ return $this->_do_quit; }

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
	            //@error_log($string."\n", 3, APP_CONNECTION_LOG_FILE);
	            break;
	        default:
	    }
	}

}
