<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace App;

use PEAR;


// 一つのsmtpコネクションの中で連続して送信できる数
defined('APP_MAX_SEND_COUNT_PER_SMTP') ? null : define('APP_MAX_SEND_COUNT_PER_SMTP',99);
defined('APP_CONNECTION_TIMEOUT') ? null : define('APP_CONNECTION_TIMEOUT',30);


class SmtpSwitcher{

    /**
     * smtp[local_ip][docomo.ne.jp] = array()
     * SMTP接続オブジェクトを保持
     */
    var $smtps;
    var $common_stmp;

    /**
     * リレー先の設定
     * @var unknown_type
     */
    var $common = array(
        'host' => 'localhost',
        'port' => 25,
        'localhost' => 'localhost',
        'limit_per_smtp' => 99
    );

    var $localhosts = array('localhost');

    var $relay_configure = array(
        'docomo.ne.jp' => array(
            'max_connection' => 1,
            'limit_per_smtp' => 20,
            'wait_time' => 1,
            'block_wait_time' => 900,
            'common_smtp' => false,
        ),
        'softbank.ne.jp' => array(
            'max_connection' => 1,
            'limit_per_smtp' => 1,
            'wait_time' => 1,
            'block_wait_time' => 900,
            'common_smtp' => false,
        ),
        'ezweb.jp' => array(
            'max_connection' => 1,
            'limit_per_smtp' => 1,
            'wait_time' => 1,
            'block_wait_time' => 900,
            'common_smtp' => false,
        ),
    );

    public $netSmtpFactory = null;
    public $all_direct = true;

    function __construct($pool_ips=[]){
        $this->localhosts = empty($pool_ips) ? $this->localhosts : $pool_ips;
    }

    function build(){
        $rs = true;
        foreach($this->localhosts as $ip ){
            foreach($this->relay_configure as $domain => $configure){
                if( !isset($this->smtps[$ip][$domain]) ){
                    $this->smtps[$ip][$domain] = array();    
                }
            }
        }

        if( !isset($this->common_stmp) ){
            $this->common_stmp = $this->createNetSMTP($this->common['host']
                , $this->common['port']
                , $this->common['localhost']);
            $rs = $this->common_stmp->connect(APP_CONNECTION_TIMEOUT);

            if( PEAR::isError($rs) ){ return $rs; }
        }
        return $rs;
    }

    /**
     * emailアドレスを指定して、SMTP接続オブジェクトを返す。
     * @param unknown_type $email
     */
    function select_by_email($email,$auto_connect=true){
        $domain = $this->get_domain_from_email($email);
        return $this->select_by_domain($domain,$auto_connect);
    }

    /**
     * Emailアドレスからドメイン部を抜き出す
     */
    function get_domain_from_email($email){
        $matches = array();
        if(preg_match('/^(.+)@(.+\.[a-zA-Z0-9]+)$/',$email,$matches)){
            return $matches[2];
        }
        return '';
    }

    /**
     * ドメインを指定してSMTP接続オブジェクトを返す。
     */
    function select_by_domain($domain, $auto_connect=true){
        if ( empty($domain) || !is_string($domain) ) { return null; }
        /**
         * ドメインに対する配送設定が存在すれば、直接配送を行うためのSMTP接続オブジェクトを返す。
         */
        foreach($this->localhosts as $ip => $domains){
            if( isset($this->smtps[$ip][$domain]) ){

                $smtps = $this->smtps[$ip][$domain];
                if(!empty($smtps)){
                    foreach($smtps as $index => $smtp){
                        if( isset($smtp->has_connection_error) 
                            && $smtp->has_connection_error ){
                            unset($smtps[$index]);
                            continue;
                        }
                        if( isset($smtp->send_count) ){
                            $per_count = isset($this->relay_configure[$domain]['limit_per_smtp'])
                                            ? $this->relay_configure[$domain]['limit_per_smtp'] 
                                            : APP_MAX_SEND_COUNT_PER_SMTP;
                            if( $smtp->send_count > $per_count ){
                                $rs = $smtp->disconnect();
                                unset($smtps[$index]);
                                if(PEAR::isError($rs)){
                                
                                }
                                continue;
                            }
                        }
                        return $smtp;
                    }
                }

                $_smtp = &$this->create_new_connection($domain,$auto_connect);
                if ( $_smtp ) { return $_smtp; }

                $common_smtp_user = isset($this->relay_configure[$domain]['common_smtp'])
                    ? $this->relay_configure[$domain]['common_smtp'] 
                    : false;
                //
                //直接転送用のsmtp接続が確立できなかった場合
                //リレー先に転送するか
                //
                if ( !$common_smtp_user ) {
                    //転送しない場合、ここで終了
                    return $dummy_smtp = null;
                }
            }else if( $this->all_direct ){
                foreach ($this->localhosts as $_ip ) {
                    $this->smtps[$_ip][$domain] = array();
                }
                $_smtp = $this->create_new_connection($domain, $auto_connect);
                if ( $_smtp ) { return $_smtp; }
            }
        }

        /**
         * 1回のSMTPセッションの中での送信数上限を超えていた場合は、
         * SMTPオブジェクトを生成し直す。
         */
        if ( isset($this->common_smtp->send_count) ) {
            $common_per_limit = isset($this->common['limit_per_smtp']) 
                ? $this->common['limit_per_smtp']
                : APP_MAX_SEND_COUNT_PER_SMTP;
                            
            if ( $this->common_smtp->send_count > $common_per_limit ) {
                if ( PEAR::isError($rs = $this->common_smtp->disconnect()) ) {
                    //切断に失敗した場合の処理
                }
                $this->common_stmp 
                    = &$this->createNetSMTP($this->common['host'], $this->common['port'], $this->common['localhost']);
                
                if ( PEAR::isError($rs = $this->common_stmp->connect(APP_CONNECTION_TIMEOUT)) ) {
                    return $dummy_smtp = null;
                }
            }
        }
        
        return $this->common_stmp;
    }

    /**
     * ドメインのMXレコード検索し、smtpサーバを検索し
     * 新しいsmtp接続オブジェクトを返す。
     * @param unknown_type $domain
     * @return class Net_SMTP
     */
    function create_new_connection($domain, $auto_connect=true){
        $ip = $this->new_connection_ip($domain);
        if ( !$ip ) { return false; }
        $hosts = $this->domain2hosts($domain);
        if ( empty($hosts) ) { return $smtp = null; }
        
        $hosts = $this->hosts_sort_by_priority($hosts);
        $smtp = null;
        //接続可能なsmtpサーバを検索
        foreach( $hosts as $index => $_host){
            $smtp = $this->createNetSMTP($_host['exchange'],25,$ip);
            if ( PEAR::isError($smtp) ) {
                $smtp = null;
                continue;
            }
            if($auto_connect){
                $rs = $smtp->connect(30);
                
                //-------------------------------------------/
                // キャリアブロックなどが発生した場合、
                // ここで、エラーが発生するものと推測される。
                //-------------------------------------------/
                if(PEAR::isError($smtp)){
                    //var_dump($smtp->getMessage().__LINE__);
                    $smtp = null;
                    continue;
                }
                break;
            }else{
                break;
            }
        }
        if(!$smtp){ return $stmp = null; }
        
        $this->smtps[$ip][$domain][] = &$smtp;
        return $smtp;   
    }

    /**
     * MXレコード検索
     */
    function domain2hosts($domain){
        $records = Dns::get_record($domain, 'MX');
        return $records;
    }

    /**
     * レコードをpreferenceの高い順（数値的には小さい順）に並び替える。
     * バブルソート...(´･ω･`)
     */
    function hosts_sort_by_priority($hosts){
        $count = count($hosts);
        for($index=0;$index<$count-1;$index++){
            for($i=$count-1;$i>$index;$i--){
                if( isset($hosts[$i]['preference']) ){
                    if( isset($hosts[$i-1]['preference']) ){
                        if( $hosts[$i]['preference'] < $hosts[$i-1]['preference'] ){
                            $tmp = $hosts[$i];
                            $hosts[$i] = $hosts[$i-1];
                            $hosts[$i-1] = $tmp;
                        }
                    }else{
                        $tmp = $hosts[$i];
                        $hosts[$i] = $hosts[$i-1];
                        $hosts[$i-1] = $tmp;
                    }
                }
            }
        }
        return $hosts;
    }

    function new_connection_ip($domain){
        if( !isset($this->relay_configure[$domain]) ){ return $this->localhosts[0]; }
        $max_connection = isset( $this->relay_configure[$domain]['max_connection'] ) 
            ? (int)$this->relay_configure[$domain]['max_connection']
            : 1;

        foreach($this->localhosts as $ip){
            $now_connection_num = count($this->smtps[$ip][$domain]);
            if( $now_connection_num < $max_connection ){
                return $ip;
            }
        }
        return null;
    }

    function createNetSMTP($host, $port, $localhost='localhost'){
        return $this->netSmtpFactory->create($host, $port, $localhost);
    }

}
